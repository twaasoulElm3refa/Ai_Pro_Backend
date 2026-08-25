<?php

namespace App\Services;

use App\Models\CostLogger;
use App\Models\Wallet;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ProviderCostBillingService
{
    private const POINTS_PER_USD = 1_000_000;

    private const SUPPORTED_SUB_TOOL_IDS = [21, 22, 23];

    private const SPEECH_TO_TEXT_SUB_TOOL_ID = 26;

    /**
     * Charge a successful provider response using cost.total_cost only.
     *
     * This method may be called from an existing transaction. Laravel keeps the
     * nested transaction on the same connection, so the wallet, cost log and
     * caller's persisted result are committed or rolled back together.
     */
    public function chargeSuccessfulResponse(
        int $userId,
        int $conversationId,
        int $subToolId,
        array $providerResponse,
        ?string $providerRequestId = null,
        ?string $modelKey = null
    ): array {
        if (! in_array($subToolId, self::SUPPORTED_SUB_TOOL_IDS, true)) {
            throw new InvalidArgumentException(
                "Provider cost billing is not enabled for sub tool {$subToolId}."
            );
        }

        $validatedCost = $this->validatedCost($providerResponse, $userId, $conversationId, $subToolId);

        if ($validatedCost === null) {
            return $this->skippedResult();
        }

        [$totalCost, $currency] = $validatedCost;

        return $this->chargeValidatedCost(
            $userId,
            $conversationId,
            $subToolId,
            $totalCost,
            $currency,
            'cost.total_cost',
            $providerRequestId,
            $modelKey
        );
    }

    /**
     * Charge speech-to-text using metadata.provider_cost_usd exclusively.
     */
    public function chargeSpeechToTextResponse(
        int $userId,
        int $conversationId,
        int $subToolId,
        array $providerResponse,
        string $requestKey,
        ?string $modelKey = null
    ): array {
        if ($subToolId !== self::SPEECH_TO_TEXT_SUB_TOOL_ID) {
            throw new InvalidArgumentException(
                "Speech-to-text provider cost billing is not enabled for sub tool {$subToolId}."
            );
        }

        $validatedCost = $this->validatedSpeechToTextCost(
            $providerResponse,
            $userId,
            $conversationId,
            $subToolId
        );

        if ($validatedCost === null) {
            return $this->skippedResult('metadata.provider_cost_usd');
        }

        return $this->chargeValidatedCost(
            $userId,
            $conversationId,
            $subToolId,
            $validatedCost,
            'USD',
            'metadata.provider_cost_usd',
            $requestKey,
            $modelKey,
            true
        );
    }

    private function chargeValidatedCost(
        int $userId,
        int $conversationId,
        int $subToolId,
        BigDecimal $totalCost,
        string $currency,
        string $source,
        ?string $providerRequestId,
        ?string $modelKey,
        bool $deduplicateByRequestId = false
    ): array {

        try {
            $pointsToDeduct = $totalCost
                ->multipliedBy(self::POINTS_PER_USD)
                ->toScale(0, RoundingMode::HALF_UP)
                ->toInt();
        } catch (MathException) {
            $this->warnSkipped(
                "{$source} cannot be converted safely to wallet points",
                $userId,
                $conversationId,
                $subToolId,
                $source
            );

            return $this->skippedResult($source);
        }

        return DB::transaction(function () use (
            $userId,
            $conversationId,
            $subToolId,
            $providerRequestId,
            $modelKey,
            $totalCost,
            $currency,
            $pointsToDeduct,
            $source,
            $deduplicateByRequestId
        ): array {
            if ($deduplicateByRequestId && $providerRequestId !== null) {
                $alreadyCharged = CostLogger::query()
                    ->where('user_id', $userId)
                    ->where('conversation_id', $conversationId)
                    ->where('sub_tool_id', $subToolId)
                    ->where('provider_request_id', $providerRequestId)
                    ->lockForUpdate()
                    ->first();

                if ($alreadyCharged) {
                    Log::warning('Provider cost billing duplicate request skipped.', [
                        'user_id' => $userId,
                        'conversation_id' => $conversationId,
                        'sub_tool_id' => $subToolId,
                        'provider_request_id' => $providerRequestId,
                        'source' => $source,
                    ]);

                    return [
                        ...$this->skippedResult($source),
                        'status' => 'already_charged',
                        'total_cost' => $totalCost->__toString(),
                        'currency' => $currency,
                        'points_to_deduct' => $pointsToDeduct,
                    ];
                }
            }

            $wallet = Wallet::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                $wallet = Wallet::create([
                    'user_id' => $userId,
                    'uuid' => (string) Str::uuid(),
                    'balance' => 0,
                    'payback_balance' => 0,
                    'is_active' => true,
                ]);
            }

            $walletBefore = (int) $wallet->balance;
            $paybackBefore = (int) ($wallet->payback_balance ?? 0);
            $pointsDeducted = min(max($walletBefore, 0), $pointsToDeduct);
            $pointsAddedToPayback = $pointsToDeduct - $pointsDeducted;

            if ($pointsToDeduct > 0) {
                $wallet->balance = $walletBefore - $pointsDeducted;
                $wallet->payback_balance = $paybackBefore + $pointsAddedToPayback;
                $wallet->save();
            }

            CostLogger::create([
                'conversation_id' => $conversationId,
                'user_id' => $userId,
                'sub_tool_id' => $subToolId,
                'input_tokens' => 0,
                'output_tokens' => 0,
                'total_tokens' => 0,
                'input_cost' => 0,
                'output_cost' => 0,
                'web_search_cost' => 0,
                'total_cost' => $totalCost->toScale(8, RoundingMode::HALF_UP)->__toString(),
                'currency' => $currency,
                'provider_request_id' => $providerRequestId,
                'model_key' => $modelKey,
            ]);

            return [
                'status' => $pointsToDeduct === 0 ? 'zero_cost' : 'charged',
                'source' => $source,
                'total_cost' => $totalCost->__toString(),
                'currency' => $currency,
                'points_per_usd' => self::POINTS_PER_USD,
                'points_to_deduct' => $pointsToDeduct,
                'points_deducted' => $pointsDeducted,
                'points_added_to_payback' => $pointsAddedToPayback,
                'wallet_before' => $walletBefore,
                'wallet_after' => $walletBefore - $pointsDeducted,
                'payback_before' => $paybackBefore,
                'payback_after' => $paybackBefore + $pointsAddedToPayback,
            ];
        });
    }

    private function validatedCost(
        array $providerResponse,
        int $userId,
        int $conversationId,
        int $subToolId
    ): ?array {
        if (! array_key_exists('cost', $providerResponse) || ! is_array($providerResponse['cost'])) {
            $this->warnSkipped('cost is missing or invalid', $userId, $conversationId, $subToolId);

            return null;
        }

        $cost = $providerResponse['cost'];

        if (! array_key_exists('total_cost', $cost)) {
            $this->warnSkipped('cost.total_cost is missing', $userId, $conversationId, $subToolId);

            return null;
        }

        $rawTotalCost = $cost['total_cost'];

        if (is_bool($rawTotalCost) || ! is_scalar($rawTotalCost) || ! is_numeric(trim((string) $rawTotalCost))) {
            $this->warnSkipped('cost.total_cost is not numeric', $userId, $conversationId, $subToolId);

            return null;
        }

        try {
            $totalCost = BigDecimal::of(trim((string) $rawTotalCost));
        } catch (MathException) {
            $this->warnSkipped('cost.total_cost is invalid', $userId, $conversationId, $subToolId);

            return null;
        }

        if ($totalCost->isNegative()) {
            $this->warnSkipped('cost.total_cost is negative', $userId, $conversationId, $subToolId);

            return null;
        }

        $rawCurrency = $cost['currency'] ?? 'USD';
        $currency = strtoupper(trim(is_scalar($rawCurrency) ? (string) $rawCurrency : ''));

        if ($currency !== 'USD') {
            $this->warnSkipped('cost.currency is not USD', $userId, $conversationId, $subToolId);

            return null;
        }

        return [$totalCost, $currency];
    }

    private function validatedSpeechToTextCost(
        array $providerResponse,
        int $userId,
        int $conversationId,
        int $subToolId
    ): ?BigDecimal {
        $source = 'metadata.provider_cost_usd';
        $metadata = $providerResponse['metadata'] ?? null;

        if (! is_array($metadata) || ! array_key_exists('provider_cost_usd', $metadata)) {
            $this->warnSkipped(
                'metadata.provider_cost_usd is missing; no speech-to-text points were deducted',
                $userId,
                $conversationId,
                $subToolId,
                $source
            );

            return null;
        }

        $rawProviderCost = $metadata['provider_cost_usd'];
        if (
            is_bool($rawProviderCost)
            || ! is_scalar($rawProviderCost)
            || ! is_numeric(trim((string) $rawProviderCost))
        ) {
            $this->warnSkipped(
                'metadata.provider_cost_usd is not numeric; no speech-to-text points were deducted',
                $userId,
                $conversationId,
                $subToolId,
                $source
            );

            return null;
        }

        try {
            $providerCost = BigDecimal::of(trim((string) $rawProviderCost));
        } catch (MathException) {
            $this->warnSkipped(
                'metadata.provider_cost_usd is invalid; no speech-to-text points were deducted',
                $userId,
                $conversationId,
                $subToolId,
                $source
            );

            return null;
        }

        if ($providerCost->isNegative()) {
            $this->warnSkipped(
                'metadata.provider_cost_usd is negative; no speech-to-text points were deducted',
                $userId,
                $conversationId,
                $subToolId,
                $source
            );

            return null;
        }

        return $providerCost;
    }

    private function skippedResult(string $source = 'cost.total_cost'): array
    {
        return [
            'status' => 'skipped_invalid_cost',
            'source' => $source,
            'total_cost' => null,
            'currency' => null,
            'points_per_usd' => self::POINTS_PER_USD,
            'points_to_deduct' => 0,
            'points_deducted' => 0,
            'points_added_to_payback' => 0,
            'wallet_before' => null,
            'wallet_after' => null,
            'payback_before' => null,
            'payback_after' => null,
        ];
    }

    private function warnSkipped(
        string $reason,
        int $userId,
        int $conversationId,
        int $subToolId,
        string $source = 'cost.total_cost'
    ): void {
        $message = $source === 'metadata.provider_cost_usd'
            ? 'Speech-to-text provider cost billing skipped.'
            : 'Provider cost billing skipped.';

        Log::warning($message, [
            'reason' => $reason,
            'user_id' => $userId,
            'conversation_id' => $conversationId,
            'sub_tool_id' => $subToolId,
            'source' => $source,
        ]);
    }
}
