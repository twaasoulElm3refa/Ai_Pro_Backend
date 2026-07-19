<?php

namespace App\Services;

use App\Interfaces\PaymentInterface;
use App\Jobs\SendDepositFailedMailJob;
use App\Jobs\SendDepositSuccessMailJob;
use App\Models\Payment;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PayPalWalletServices implements PaymentInterface
{
    private ?PayPalClient $provider = null;

    public function __construct(private readonly PayPalClientFactory $clientFactory) {}

    private function client(): PayPalClient
    {
        return $this->provider ??= $this->clientFactory->make();
    }

    public function pay(array $data): array
    {
        $userId = (int) ($data['user_id'] ?? 0);
        $amount = $this->normalizeMoney((string) ($data['amount'] ?? ''));
        $currency = strtoupper((string) config('paypal.currency', 'USD'));
        $idempotencyKey = (string) ($data['idempotency_key'] ?? '');

        if ($userId <= 0 || $idempotencyKey === '') {
            throw new DomainException('Authenticated user and idempotency key are required.');
        }

        $key = hash('sha256', $userId.'|'.$idempotencyKey);
        $order = $this->resolveLocalPayment($data, $key, $userId, $amount, $currency);
        $this->assertSameIdempotentPayload($order, $userId, $amount, $currency);

        if ($order->status === Payment::STATUS_COMPLETED) {
            return ['order' => $order, 'approval_url' => null];
        }
        if ($order->status === Payment::STATUS_FAILED) {
            throw new DomainException('This idempotency key belongs to a failed payment. Use a new key.');
        }
        if ($order->status === Payment::STATUS_APPROVED) {
            return ['order' => $order, 'approval_url' => null];
        }
        if ($order->paypal_order_id) {
            return ['order' => $order, 'approval_url' => $this->approvalUrlForExistingOrder($order)];
        }

        $paypalOrder = $this->client()
            ->withIdempotencyKey('wallet-create-'.$order->id)
            ->createOrder([
                'intent' => 'CAPTURE',
                'application_context' => [
                    'return_url' => route('wallet.success', ['lang' => $data['locale'] ?? 'en']),
                    'cancel_url' => route('wallet.cancel', [
                        'lang' => $data['locale'] ?? 'en',
                        'order_id' => $order->id,
                    ]),
                ],
                'purchase_units' => [[
                    'reference_id' => (string) $order->id,
                    'custom_id' => $this->expectedCustomId($order),
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $amount,
                    ],
                    'description' => $order->description,
                ]],
            ]);

        if (! isset($paypalOrder['id']) || ($paypalOrder['status'] ?? null) !== 'CREATED') {
            throw new RuntimeException('PayPal order creation did not return CREATED.');
        }

        $order = DB::transaction(function () use ($order, $paypalOrder) {
            $locked = Payment::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->paypal_order_id && $locked->paypal_order_id !== $paypalOrder['id']) {
                throw new RuntimeException('A different PayPal order is already attached to this payment.');
            }
            $locked->update([
                'paypal_order_id' => $paypalOrder['id'],
                'gateway_response' => $paypalOrder,
            ]);

            return $locked->fresh();
        }, 3);

        Log::info('wallet_paypal_order_created', ['payment_id' => $order->id]);

        return ['order' => $order, 'approval_url' => $this->extractApprovalUrl($paypalOrder)];
    }

    private function resolveLocalPayment(
        array $data,
        string $key,
        int $userId,
        string $amount,
        string $currency
    ): Payment {
        try {
            return DB::transaction(function () use ($data, $key, $userId, $amount, $currency) {
                $existing = Payment::where('idempotency_key', $key)->lockForUpdate()->first();
                if ($existing) {
                    return $existing;
                }

                return Payment::create([
                    'idempotency_key' => $key,
                    'user_id' => $userId,
                    'amount' => $amount,
                    'currency' => $currency,
                    'description' => ($data['description'] ?? null) ?: 'Wallet Deposit',
                    'payment_method' => 'paypal',
                    'type' => 'wallet_deposit',
                    'status' => Payment::STATUS_PENDING,
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            return Payment::where('idempotency_key', $key)->firstOrFail();
        }
    }

    private function assertSameIdempotentPayload(
        Payment $order,
        int $userId,
        string $amount,
        string $currency
    ): void {
        if ((int) $order->user_id !== $userId
            || $order->type !== 'wallet_deposit'
            || $this->moneyToMinorUnits((string) $order->amount) !== $this->moneyToMinorUnits($amount)
            || strtoupper((string) $order->currency) !== $currency) {
            throw new DomainException('The idempotency key was already used with different payment data.');
        }
    }

    private function approvalUrlForExistingOrder(Payment $order): ?string
    {
        $details = $this->client()->showOrderDetails($order->paypal_order_id);
        if (($details['status'] ?? null) === 'APPROVED') {
            $this->markApproved($order->id);
            return null;
        }
        if (($details['status'] ?? null) !== 'CREATED') {
            throw new DomainException('The existing PayPal order can no longer be approved.');
        }

        return $this->extractApprovalUrl($details);
    }

    public function success(string $token): array
    {
        $order = DB::transaction(function () use ($token) {
            $locked = Payment::where('paypal_order_id', $token)
                ->where('type', 'wallet_deposit')
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === Payment::STATUS_PENDING) {
                $locked->update(['status' => Payment::STATUS_APPROVED]);
            }

            if (! in_array($locked->status, [Payment::STATUS_APPROVED, Payment::STATUS_COMPLETED], true)) {
                throw new DomainException('Payment is in a terminal state.');
            }

            return $locked->fresh();
        }, 3);

        return [
            'success' => true,
            'message' => 'Awaiting verified PayPal capture confirmation.',
            'order_id' => $order->id,
            'order' => $order,
        ];
    }

    public function cancel(): array
    {
        return ['success' => false, 'message' => 'Payment cancelled by user.'];
    }

    public function handleWebhook(array $payload): void
    {
        $resource = $payload['resource'] ?? [];

        match ($payload['event_type'] ?? null) {
            'CHECKOUT.ORDER.APPROVED' => $this->handleApprovedOrder($resource),
            'PAYMENT.CAPTURE.COMPLETED' => $this->finalizeCompletedWalletCapture($resource),
            'PAYMENT.CAPTURE.DECLINED' => $this->handleDeclinedCapture($resource),
            default => throw new RuntimeException('Unsupported wallet PayPal event type.'),
        };
    }

    private function handleApprovedOrder(array $resource): void
    {
        $paypalOrderId = $this->extractPaypalOrderId($resource);
        $order = Payment::where('paypal_order_id', $paypalOrderId)
            ->where('type', 'wallet_deposit')
            ->firstOrFail();

        if (($resource['status'] ?? null) !== 'APPROVED') {
            $this->validationFailure($order, 'approved_event_status_mismatch');
        }
        $this->validateOrderIdentityAndMoney($order, $resource);

        if ($order->status === Payment::STATUS_COMPLETED && $order->wallet_credited) {
            return;
        }
        if ($order->status === Payment::STATUS_COMPLETED || $order->wallet_credited) {
            $this->validationFailure($order, 'completed_payment_ledger_mismatch');
        }
        if ($order->status === Payment::STATUS_FAILED) {
            $this->validationFailure($order, 'approved_event_for_failed_payment');
        }

        $this->markApproved($order->id);

        $captureResponse = $this->client()
            ->withIdempotencyKey('wallet-capture-'.$order->id)
            ->capturePaymentOrder($order->paypal_order_id);

        $status = $this->extractCaptureStatus($captureResponse);
        if ($status === 'COMPLETED') {
            $this->finalizeCompletedWalletCapture($captureResponse);
        } elseif (! in_array($status, ['PENDING', null], true)) {
            throw new RuntimeException('PayPal capture returned an unexpected status.');
        }
    }

    private function markApproved(int $paymentId): void
    {
        DB::transaction(function () use ($paymentId) {
            $order = Payment::whereKey($paymentId)->lockForUpdate()->firstOrFail();
            if ($order->status === Payment::STATUS_PENDING) {
                $order->update(['status' => Payment::STATUS_APPROVED]);
            }
        }, 3);
    }

    public function finalizeCompletedWalletCapture(array $resource): Payment
    {
        $paypalOrderId = $this->extractPaypalOrderId($resource);
        $captureId = $this->extractCaptureId($resource);
        if (! $paypalOrderId || ! $captureId) {
            throw new RuntimeException('PayPal capture identifiers are missing.');
        }

        Log::info('wallet_credit_started', ['paypal_order_id' => $paypalOrderId]);

        $result = DB::transaction(function () use ($resource, $paypalOrderId, $captureId) {
            $order = Payment::where('paypal_order_id', $paypalOrderId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateCompletedCapture($order, $resource, $captureId);

            if ($order->status === Payment::STATUS_COMPLETED || $order->wallet_credited) {
                if ($order->transaction_id !== $captureId || ! $order->walletTransaction()->exists()) {
                    $this->validationFailure($order, 'completed_payment_ledger_mismatch');
                }
                return ['order' => $order, 'credited' => false];
            }

            if (Payment::where('transaction_id', $captureId)->whereKeyNot($order->id)->exists()) {
                $this->validationFailure($order, 'capture_id_already_used');
            }

            Wallet::query()->insertOrIgnore([
                'user_id' => $order->user_id,
                'uuid' => (string) Str::uuid(),
                'balance' => 0,
                'payback_balance' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $wallet = Wallet::withTrashed()
                ->where('user_id', $order->user_id)
                ->lockForUpdate()
                ->firstOrFail();
            if ($wallet->trashed()) {
                $wallet->restore();
            }

            $points = $this->amountToPoints((string) $order->amount);
            $before = (int) $wallet->balance;
            if ($points <= 0 || $before > PHP_INT_MAX - $points) {
                $this->validationFailure($order, 'wallet_balance_overflow');
            }

            $wallet->forceFill(['balance' => $before + $points])->save();
            $wallet->refresh();
            $after = (int) $wallet->balance;

            WalletTransaction::create([
                'user_id' => $order->user_id,
                'wallet_id' => $wallet->id,
                'payment_id' => $order->id,
                'points' => $points,
                'type' => 'credit',
                'description' => 'PayPal wallet deposit',
                'balance_before' => $before,
                'balance_after' => $after,
                'slug' => $order->idempotency_key,
            ]);

            $capture = $this->captureResource($resource);
            $order->update([
                'status' => Payment::STATUS_COMPLETED,
                'wallet_credited' => true,
                'transaction_id' => $captureId,
                'payer_email' => data_get($resource, 'payer.email_address', $order->payer_email),
                'gateway_response' => $resource,
                'paid_at' => isset($capture['update_time'])
                    ? CarbonImmutable::parse($capture['update_time'])
                    : now(),
                'mail_sent' => false,
            ]);

            DB::afterCommit(function () use ($order) {
                try {
                    Cache::tags(['wallet', 'transactions', "user_{$order->user_id}"])->flush();
                } catch (\Throwable $e) {
                    Log::warning('wallet_cache_flush_failed', [
                        'payment_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                try {
                    SendDepositSuccessMailJob::dispatch($order->id)->afterCommit();
                    Log::info('email_queued', ['payment_id' => $order->id, 'kind' => 'deposit_success']);
                } catch (\Throwable $e) {
                    Log::error('email_failed', [
                        'payment_id' => $order->id,
                        'kind' => 'deposit_success_queue',
                        'error' => $e->getMessage(),
                    ]);
                }
            });

            return ['order' => $order->fresh(), 'credited' => true];
        }, 3);

        if (! $result['credited'] && ! $result['order']->mail_sent) {
            try {
                SendDepositSuccessMailJob::dispatch($result['order']->id)->afterCommit();
            } catch (\Throwable $e) {
                Log::error('email_failed', [
                    'payment_id' => $result['order']->id,
                    'kind' => 'deposit_success_requeue',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('wallet_credit_committed', [
            'payment_id' => $result['order']->id,
            'credited_now' => $result['credited'],
        ]);

        return $result['order'];
    }

    private function validateCompletedCapture(Payment $order, array $resource, string $captureId): void
    {
        if ($order->type !== 'wallet_deposit' || ! $order->user_id || ! $order->user()->exists()) {
            $this->validationFailure($order, 'invalid_wallet_payment_owner_or_type');
        }
        if ($this->extractCaptureStatus($resource) !== 'COMPLETED') {
            $this->validationFailure($order, 'capture_status_not_completed');
        }
        if ($this->extractPaypalOrderId($resource) !== $order->paypal_order_id) {
            $this->validationFailure($order, 'paypal_order_id_mismatch');
        }
        if ($captureId === $order->paypal_order_id) {
            $this->validationFailure($order, 'capture_id_is_order_id');
        }
        $this->validateCaptureIdentityAndMoney($order, $resource);
    }

    private function validateOrderIdentityAndMoney(Payment $order, array $resource): void
    {
        if (($resource['id'] ?? null) !== $order->paypal_order_id) {
            $this->validationFailure($order, 'approved_order_id_mismatch');
        }
        $this->validateCaptureIdentityAndMoney($order, $resource);
    }

    private function validateCaptureIdentityAndMoney(Payment $order, array $resource): void
    {
        $customId = $this->extractCustomId($resource);
        $referenceId = $this->extractReferenceId($resource);
        $amount = $this->extractAmount($resource);
        $currency = $this->extractCurrency($resource);
        $merchantId = $this->extractMerchantId($resource);
        $expectedMerchantId = (string) config('paypal.merchant_id', '');

        if ($customId !== $this->expectedCustomId($order)) {
            $this->validationFailure($order, 'custom_id_mismatch');
        }
        if ($referenceId !== null && $referenceId !== (string) $order->id) {
            $this->validationFailure($order, 'reference_id_mismatch');
        }
        if ($amount === null
            || $this->moneyToMinorUnits($amount) !== $this->moneyToMinorUnits((string) $order->amount)) {
            $this->validationFailure($order, 'amount_mismatch');
        }
        if ($currency === null || strtoupper($currency) !== strtoupper((string) $order->currency)) {
            $this->validationFailure($order, 'currency_mismatch');
        }
        if ($expectedMerchantId === '' || $merchantId === null
            || ! hash_equals($expectedMerchantId, $merchantId)) {
            $this->validationFailure($order, 'merchant_id_mismatch_or_missing');
        }
    }

    private function handleDeclinedCapture(array $resource): void
    {
        $paypalOrderId = $this->extractPaypalOrderId($resource);
        $order = Payment::where('paypal_order_id', $paypalOrderId)
            ->where('type', 'wallet_deposit')
            ->firstOrFail();

        if ($this->extractCaptureStatus($resource) !== 'DECLINED') {
            $this->validationFailure($order, 'declined_capture_status_mismatch');
        }
        $this->validateCaptureIdentityAndMoney($order, $resource);

        $order = DB::transaction(function () use ($order, $resource) {
            $locked = Payment::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== Payment::STATUS_COMPLETED && ! $locked->wallet_credited) {
                $locked->update([
                    'status' => Payment::STATUS_FAILED,
                    'gateway_response' => $resource,
                    'mail_sent' => false,
                ]);
            }

            DB::afterCommit(function () use ($locked) {
                if ($locked->status !== Payment::STATUS_FAILED) {
                    return;
                }
                try {
                    SendDepositFailedMailJob::dispatch($locked->id)->afterCommit();
                    Log::info('email_queued', ['payment_id' => $locked->id, 'kind' => 'deposit_failed']);
                } catch (\Throwable $e) {
                    Log::error('email_failed', [
                        'payment_id' => $locked->id,
                        'kind' => 'deposit_failed_queue',
                        'error' => $e->getMessage(),
                    ]);
                }
            });

            return $locked->fresh();
        }, 3);
    }

    public function reconcile(Payment $order): string
    {
        if (! $order->paypal_order_id || $order->type !== 'wallet_deposit') {
            return 'ignored';
        }

        $details = $this->client()->showOrderDetails($order->paypal_order_id);
        $status = $details['status'] ?? null;
        if ($status === 'COMPLETED' && $this->extractCaptureId($details)) {
            $this->finalizeCompletedWalletCapture($details);
            return 'completed';
        }
        if ($status === 'APPROVED') {
            $this->validateOrderIdentityAndMoney($order, $details);
            $this->markApproved($order->id);
            return 'approved';
        }

        return 'pending';
    }

    public function extractPaypalOrderId(array $resource): ?string
    {
        if (isset($resource['purchase_units'][0])) {
            return isset($resource['id']) ? (string) $resource['id'] : null;
        }

        return data_get($resource, 'supplementary_data.related_ids.order_id');
    }

    public function extractCaptureId(array $resource): ?string
    {
        $nested = data_get($resource, 'purchase_units.0.payments.captures.0.id');
        if ($nested) {
            return (string) $nested;
        }
        if (data_get($resource, 'supplementary_data.related_ids.order_id')) {
            return isset($resource['id']) ? (string) $resource['id'] : null;
        }

        return null;
    }

    public function extractAmount(array $resource): ?string
    {
        $capture = $this->captureResource($resource);
        $value = data_get($capture, 'amount.value') ?? data_get($resource, 'purchase_units.0.amount.value');

        return $value === null ? null : (string) $value;
    }

    public function extractCurrency(array $resource): ?string
    {
        $capture = $this->captureResource($resource);
        $value = data_get($capture, 'amount.currency_code')
            ?? data_get($resource, 'purchase_units.0.amount.currency_code');

        return $value === null ? null : (string) $value;
    }

    public function extractCustomId(array $resource): ?string
    {
        $capture = $this->captureResource($resource);
        $value = $capture['custom_id'] ?? data_get($resource, 'purchase_units.0.custom_id');

        return $value === null ? null : (string) $value;
    }

    public function extractReferenceId(array $resource): ?string
    {
        $value = data_get($resource, 'purchase_units.0.reference_id') ?? ($resource['reference_id'] ?? null);

        return $value === null ? null : (string) $value;
    }

    public function extractMerchantId(array $resource): ?string
    {
        $capture = $this->captureResource($resource);
        $value = data_get($capture, 'payee.merchant_id')
            ?? data_get($resource, 'purchase_units.0.payee.merchant_id');

        return $value === null ? null : (string) $value;
    }

    public function extractCaptureStatus(array $resource): ?string
    {
        $capture = $this->captureResource($resource);
        $status = $capture['status'] ?? null;

        return $status === null ? null : strtoupper((string) $status);
    }

    private function captureResource(array $resource): array
    {
        $capture = data_get($resource, 'purchase_units.0.payments.captures.0');

        return is_array($capture) ? $capture : $resource;
    }

    private function expectedCustomId(Payment $order): string
    {
        return 'wallet_topup:'.$order->id;
    }

    private function extractApprovalUrl(array $order): string
    {
        $link = collect($order['links'] ?? [])->firstWhere('rel', 'approve');
        $url = is_array($link) ? ($link['href'] ?? null) : null;
        if (! is_string($url) || $url === '') {
            throw new RuntimeException('PayPal approval URL is missing.');
        }

        return $url;
    }

    private function normalizeMoney(string $value): string
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new DomainException('Amount must have no more than two decimal places.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0');

        return ($whole === '' ? '0' : $whole).'.'.str_pad($fraction, 2, '0');
    }

    private function moneyToMinorUnits(string $value): int
    {
        [$whole, $fraction] = explode('.', $this->normalizeMoney($value));

        return ((int) $whole * 100) + (int) $fraction;
    }

    private function amountToPoints(string $amount): int
    {
        $minorUnits = $this->moneyToMinorUnits($amount);
        $pointsPerUsd = (int) config('wallet.points_per_usd', 1_000_000);
        if ($pointsPerUsd <= 0 || $pointsPerUsd % 100 !== 0) {
            throw new RuntimeException('WALLET_POINTS_PER_USD must be positive and divisible by 100.');
        }
        if ($minorUnits > intdiv(PHP_INT_MAX, intdiv($pointsPerUsd, 100))) {
            throw new RuntimeException('Deposit points overflow.');
        }

        return $minorUnits * intdiv($pointsPerUsd, 100);
    }

    private function validationFailure(Payment $order, string $reason): never
    {
        Log::warning('payment_validation_failed', [
            'payment_id' => $order->id,
            'reason' => $reason,
        ]);

        throw new RuntimeException('PayPal payment validation failed: '.$reason);
    }
}
