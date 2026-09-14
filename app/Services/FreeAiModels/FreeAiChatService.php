<?php

namespace App\Services\FreeAiModels;

use App\Models\ModelsConverstaions;
use App\Models\ModelsCostLogger;
use App\Models\ModelsMessage;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\ModelCatalogService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FreeAiChatService
{
    public function __construct(private readonly ModelCatalogService $catalogs) {}

    public function send(ModelsConverstaions $conversation, string $message, string $requestId): array
    {
        try {
            return Cache::lock("free-ai-chat-user-{$conversation->user_id}", 150)
                ->block(3, fn () => $this->sendLocked($conversation, $message, $requestId));
        } catch (LockTimeoutException) {
            throw new FreeAiChatException('A previous message is still being processed.', 429);
        }
    }

    private function sendLocked(ModelsConverstaions $conversation, string $message, string $requestId): array
    {
        $conversation->refresh();
        if ($conversation->trashed() || $conversation->is_archived) {
            throw new FreeAiChatException('Conversation is unavailable.', 404);
        }

        $previous = $conversation->messages()->where('request_id', $requestId)->where('role', 'user')->first();
        if ($previous) {
            if ($previous->content !== $message) {
                throw new FreeAiChatException('Request ID was already used for another message.', 409);
            }
            $assistant = $conversation->messages()->where('request_id', $requestId)->where('role', 'assistant')->first();
            if ($assistant) return $this->result($previous, $assistant, $this->walletSnapshot($conversation->user_id));

            throw new FreeAiChatException('The previous request did not finish. Please send a new message.', 409);
        }

        $wallet = Wallet::query()->where('user_id', $conversation->user_id)->first();
        $estimate = max(1, intdiv(strlen($message) + 3, 4)) + 1;
        if (! $wallet || ! $wallet->is_active || $wallet->balance < $estimate || $wallet->payback_balance > 0) {
            throw new FreeAiChatException('رصيدك لا يكفي لإرسال الطلب، يرجى شحن المحفظة', 402);
        }

        $selection = $this->validatedSelection($conversation);

        $userMessage = $conversation->messages()->create([
            'user_id' => $conversation->user_id,
            'role' => 'user',
            'content' => $message,
            'request_id' => $requestId,
            'metadata' => ['status' => 'pending'],
        ]);
        if (! $conversation->title) {
            $conversation->update(['title' => mb_substr($message, 0, 80)]);
        }

        $key = trim((string) config('services.aiarabic.internal_api_key'));
        if ($key === '') {
            $userMessage->update(['metadata' => ['status' => 'failed']]);
            throw new FreeAiChatException('AI service is not configured.', 503);
        }

        $body = [
            'user_id' => (int) $conversation->user_id,
            'model_id' => (int) $conversation->model_id,
            'selected_model_id' => (int) $selection['id'],
            'conversation_uuid' => $conversation->uuid,
            'user_message' => $message,
            'state' => ['parameters' => ['quality_mode' => 'balanced']],
            'debug' => true,
        ];
        $url = rtrim((string) config('services.aiarabic.base_url', 'https://api.aiarabic.com'), '/').'/tasks/general-chat';
        $started = microtime(true);
        try {
            $response = Http::acceptJson()->asJson()
                ->withHeaders(['x-internal-api-key' => $key])
                ->connectTimeout(10)->timeout(60)->post($url, $body);
        } catch (ConnectionException $exception) {
            $userMessage->update(['metadata' => ['status' => 'failed', 'reason' => 'connection']]);
            Log::warning('Free AI chat connection failed.', [
                'user_id' => $conversation->user_id,
                'conversation_id' => $conversation->id,
                'request_id' => $requestId,
                'exception' => $exception::class,
            ]);
            throw new FreeAiChatException('تعذر الاتصال بخدمة الذكاء الاصطناعي. حاول مجددًا.', 504);
        }

        $elapsed = (int) round((microtime(true) - $started) * 1000);
        $payload = $response->json();
        if (! $response->successful() || ! is_array($payload) || ($payload['success'] ?? false) !== true) {
            $userMessage->update(['metadata' => ['status' => 'failed', 'reason' => 'provider']]);
            Log::warning('Free AI chat provider failed.', [
                'user_id' => $conversation->user_id,
                'conversation_id' => $conversation->id,
                'request_id' => $requestId,
                'provider_status' => $response->status(),
            ]);
            throw new FreeAiChatException('تعذر الحصول على الرد من خدمة الذكاء الاصطناعي.', $response->status() === 429 ? 429 : 502);
        }

        $content = $payload['content'] ?? null;
        $providerUsage = data_get($payload, 'metadata.provider_usage');
        $input = $this->tokenCount(data_get($providerUsage, 'prompt_tokens'));
        $output = $this->tokenCount(data_get($providerUsage, 'completion_tokens'));
        $reasoning = $this->tokenCount(data_get($providerUsage, 'completion_tokens_details.reasoning_tokens', 0));
        if (! is_string($content) || trim($content) === '' || $input === null || $output === null || $reasoning === null) {
            $userMessage->update(['metadata' => ['status' => 'failed', 'reason' => 'invalid_response']]);
            throw new FreeAiChatException('استجابة خدمة الذكاء الاصطناعي غير صالحة.', 502);
        }
        $total = $input + $output + $reasoning;
        if ($total <= 0 || $total > PHP_INT_MAX) {
            $userMessage->update(['metadata' => ['status' => 'failed', 'reason' => 'invalid_usage']]);
            throw new FreeAiChatException('بيانات استهلاك التوكنز غير صالحة.', 502);
        }

        $result = DB::transaction(function () use ($conversation, $userMessage, $requestId, $content, $payload, $selection, $elapsed, $input, $output, $reasoning, $total) {
            $wallet = Wallet::query()->where('user_id', $conversation->user_id)->lockForUpdate()->firstOrFail();
            $before = max(0, (int) $wallet->balance);
            $paybackBefore = max(0, (int) $wallet->payback_balance);
            $deducted = min($before, $total);
            $wallet->balance = $before - $deducted;
            $wallet->payback_balance = $paybackBefore + ($total - $deducted);
            $wallet->save();

            $assistant = $conversation->messages()->create([
                'user_id' => $conversation->user_id,
                'role' => 'assistant',
                'content' => $content,
                'request_id' => $requestId,
            ]);
            $logger = ModelsCostLogger::create([
                'user_id' => $conversation->user_id,
                'models_conversation_id' => $conversation->id,
                'model_id' => $conversation->model_id,
                'assistant_message_id' => $assistant->id,
                'provider' => $selection['provider'],
                'provider_model_id' => $selection['provider_model_id'],
                'request_id' => $requestId,
                'input_tokens' => $input,
                'output_tokens' => $output,
                'reasoning_tokens' => $reasoning,
                'total_tokens' => $total,
                'input_cost' => $this->cost(data_get($payload, 'usage.input_cost')),
                'output_cost' => $this->cost(data_get($payload, 'usage.output_cost')),
                'total_cost' => $this->cost(data_get($payload, 'usage.total_cost')),
                'response_time_ms' => $elapsed,
                'metadata' => ['response' => $payload],
            ]);
            WalletTransaction::create([
                'user_id' => $conversation->user_id,
                'wallet_id' => $wallet->id,
                'payment_id' => null,
                'models_cost_logger_id' => $logger->id,
                'points' => $total,
                'type' => 'debit',
                'description' => 'Free AI chat usage',
                'balance_before' => $before,
                'balance_after' => $wallet->balance,
                'payback_before' => $paybackBefore,
                'payback_after' => $wallet->payback_balance,
                'slug' => 'free-ai-chat-'.$requestId,
            ]);
            $userMessage->update(['metadata' => ['status' => 'completed']]);
            $conversation->touch();

            DB::afterCommit(function () use ($conversation) {
                try {
                    Cache::tags(['wallet', 'transactions', "user_{$conversation->user_id}"])->flush();
                } catch (Throwable $exception) {
                    Log::warning('Free AI chat wallet cache invalidation failed.', [
                        'user_id' => $conversation->user_id,
                        'exception' => $exception::class,
                    ]);
                }
            });

            return $this->result($userMessage, $assistant, [
                'balance' => (int) $wallet->balance,
                'payback_balance' => (int) $wallet->payback_balance,
            ]);
        }, 3);

        return $result;
    }

    private function validatedSelection(ModelsConverstaions $conversation): array
    {
        if ($conversation->selected_model_source !== 'general_chat' || ! $conversation->selected_model_id) {
            throw new FreeAiChatException('اختر موديل محادثة متاحًا أولًا.', 422);
        }
        try {
            $items = $this->catalogs->getModels('general_chat')['items'] ?? [];
        } catch (Throwable $exception) {
            Log::warning('Free AI chat catalog unavailable.', ['exception' => $exception::class]);
            throw new FreeAiChatException('قائمة الموديلات غير متاحة حاليًا.', 502);
        }
        foreach ($items as $item) {
            if ((string) ($item['id'] ?? '') !== (string) $conversation->selected_model_id) continue;
            if ((string) ($item['provider_model_id'] ?? '') !== (string) $conversation->provider_model_id) continue;
            if (($item['tool_key'] ?? null) !== 'general_chat' || ($item['operation'] ?? null) !== 'text_generation') continue;
            if (! filter_var($item['is_available'] ?? true, FILTER_VALIDATE_BOOLEAN)) continue;
            $provider = trim((string) ($item['provider'] ?? ''));
            if ($provider === '') continue;
            $conversation->update(['provider' => $provider, 'tool_key' => 'general_chat']);
            return [
                'id' => (int) $item['id'],
                'provider' => $provider,
                'provider_model_id' => (string) $item['provider_model_id'],
            ];
        }
        throw new FreeAiChatException('الموديل المختار غير متاح للمحادثة.', 422);
    }

    private function tokenCount(mixed $value): ?int
    {
        if (is_int($value)) return $value >= 0 ? $value : null;
        if (! is_string($value) || ! ctype_digit($value)) return null;
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        return $parsed === false || $parsed < 0 ? null : $parsed;
    }

    private function cost(mixed $value): string
    {
        return is_numeric($value) && (float) $value >= 0
            ? number_format((float) $value, 8, '.', '')
            : '0.00000000';
    }

    private function walletSnapshot(int $userId): array
    {
        $wallet = Wallet::query()->where('user_id', $userId)->first();
        return [
            'balance' => (int) ($wallet?->balance ?? 0),
            'payback_balance' => (int) ($wallet?->payback_balance ?? 0),
        ];
    }

    private function result(ModelsMessage $user, ModelsMessage $assistant, array $wallet): array
    {
        $publicMessage = static fn (ModelsMessage $message): array => [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'request_id' => $message->request_id,
            'created_at' => $message->created_at?->toISOString(),
        ];

        return [
            'user_message' => $publicMessage($user),
            'assistant_message' => $publicMessage($assistant),
            'wallet' => $wallet,
        ];
    }
}
