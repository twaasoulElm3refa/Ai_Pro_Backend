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

    public function send(
        ModelsConverstaions $conversation,
        string $message,
        string $requestId,
        ?string $programmingLanguage = null
    ): array
    {
        try {
            return Cache::lock("free-ai-chat-user-{$conversation->user_id}", 150)
                ->block(3, fn () => $this->sendLocked($conversation, $message, $requestId, $programmingLanguage));
        } catch (LockTimeoutException) {
            throw new FreeAiChatException('A previous message is still being processed.', 429, '3');
        }
    }

    private function sendLocked(
        ModelsConverstaions $conversation,
        string $message,
        string $requestId,
        ?string $programmingLanguage
    ): array
    {
        $conversation->refresh();
        if ($conversation->trashed() || $conversation->is_archived) {
            throw new FreeAiChatException('Conversation is unavailable.', 404);
        }

        $source = config("model_catalogs.free_ai_tools.{$conversation->model?->slug}");
        if (! in_array($source, ['general_chat', 'general_code'], true)
            || $conversation->selected_model_source !== $source) {
            throw new FreeAiChatException('The conversation model is unavailable for this tool.', 422);
        }
        if ($source === 'general_code' && (! is_string($programmingLanguage) || trim($programmingLanguage) === '')) {
            throw new FreeAiChatException('Select a programming language or framework.', 422);
        }
        $parameters = $source === 'general_code'
            ? [
                'task_mode' => 'generate',
                'programming_language' => trim($programmingLanguage),
                'include_explanation' => true,
                'include_tests' => true,
            ]
            : ['quality_mode' => 'balanced'];
        $codeMetadata = $source === 'general_code' ? $parameters : [];

        $previous = $conversation->messages()->where('request_id', $requestId)->where('role', 'user')->first();
        if ($previous) {
            if ($previous->content !== $message) {
                throw new FreeAiChatException('Request ID was already used for another message.', 409);
            }
            if ($source === 'general_code'
                && data_get($previous->metadata, 'programming_language') !== $parameters['programming_language']) {
                throw new FreeAiChatException('Request ID was already used with other code options.', 409);
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

        $selection = $this->validatedSelection($conversation, $source);

        $userMessage = $conversation->messages()->create([
            'user_id' => $conversation->user_id,
            'role' => 'user',
            'content' => $message,
            'request_id' => $requestId,
            'metadata' => ['status' => 'pending', ...$codeMetadata],
        ]);
        if (! $conversation->title) {
            $conversation->update(['title' => mb_substr($message, 0, 80)]);
        }

        $key = trim((string) config('services.aiarabic.internal_api_key'));
        if ($key === '') {
            $this->updateMessageStatus($userMessage, 'failed');
            throw new FreeAiChatException('AI service is not configured.', 503);
        }

        $body = [
            'user_id' => (int) $conversation->user_id,
            'model_id' => (int) $conversation->model_id,
            'selected_model_id' => (int) $selection['id'],
            'conversation_uuid' => $conversation->uuid,
            'user_message' => $message,
            'state' => ['parameters' => $parameters],
        ];
        if ($source === 'general_chat') $body['debug'] = true;
        $endpoint = $source === 'general_code' ? 'general-code' : 'general-chat';
        $url = rtrim((string) config('services.aiarabic.base_url', 'https://api.aiarabic.com'), '/').'/tasks/'.$endpoint;
        $started = microtime(true);
        try {
            $response = Http::acceptJson()->asJson()
                ->withHeaders(['x-internal-api-key' => $key])
                ->connectTimeout(10)->timeout(60)->post($url, $body);
        } catch (ConnectionException $exception) {
            $this->updateMessageStatus($userMessage, 'failed', 'connection');
            Log::warning('Free AI model connection failed.', [
                'tool_type' => $source,
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
            $this->updateMessageStatus($userMessage, 'failed', 'provider');
            Log::warning('Free AI model provider failed.', [
                'tool_type' => $source,
                'user_id' => $conversation->user_id,
                'conversation_id' => $conversation->id,
                'request_id' => $requestId,
                'provider_status' => $response->status(),
            ]);
            throw new FreeAiChatException(
                'تعذر الحصول على الرد من خدمة الذكاء الاصطناعي.',
                $response->status() === 429 ? 429 : 502,
                $response->status() === 429 ? $response->header('Retry-After') : null
            );
        }

        $content = $payload['content'] ?? null;
        $providerUsage = data_get($payload, 'metadata.provider_usage');
        $input = $this->tokenCount(data_get($providerUsage, 'prompt_tokens'));
        $output = $this->tokenCount(data_get($providerUsage, 'completion_tokens'));
        $reasoning = $this->tokenCount(data_get($providerUsage, 'completion_tokens_details.reasoning_tokens', 0));
        if (! is_string($content) || trim($content) === '' || $input === null || $output === null || $reasoning === null) {
            $this->updateMessageStatus($userMessage, 'failed', 'invalid_response');
            throw new FreeAiChatException('استجابة خدمة الذكاء الاصطناعي غير صالحة.', 502);
        }
        $total = $input + $output + $reasoning;
        if ($total <= 0 || $total > PHP_INT_MAX) {
            $this->updateMessageStatus($userMessage, 'failed', 'invalid_usage');
            throw new FreeAiChatException('بيانات استهلاك التوكنز غير صالحة.', 502);
        }

        $result = DB::transaction(function () use ($conversation, $userMessage, $requestId, $content, $payload, $selection, $elapsed, $input, $output, $reasoning, $total, $source, $codeMetadata) {
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
                ...($codeMetadata ? ['metadata' => $codeMetadata] : []),
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
                'metadata' => ['response' => $payload, ...($codeMetadata ? ['tool_type' => $source, 'parameters' => $codeMetadata] : [])],
            ]);
            WalletTransaction::create([
                'user_id' => $conversation->user_id,
                'wallet_id' => $wallet->id,
                'payment_id' => null,
                'models_cost_logger_id' => $logger->id,
                'points' => $total,
                'type' => 'debit',
                'description' => $source === 'general_code' ? 'Free AI code usage' : 'Free AI chat usage',
                'balance_before' => $before,
                'balance_after' => $wallet->balance,
                'payback_before' => $paybackBefore,
                'payback_after' => $wallet->payback_balance,
                'slug' => 'free-ai-'.($source === 'general_code' ? 'code' : 'chat').'-'.$requestId,
            ]);
            $this->updateMessageStatus($userMessage, 'completed');
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

    private function validatedSelection(ModelsConverstaions $conversation, string $source): array
    {
        if ($conversation->selected_model_source !== $source || ! $conversation->selected_model_id) {
            throw new FreeAiChatException('اختر موديلًا متاحًا أولًا.', 422);
        }
        try {
            $items = $this->catalogs->getModels($source)['items'] ?? [];
        } catch (Throwable $exception) {
            Log::warning('Free AI model catalog unavailable.', ['source' => $source, 'exception' => $exception::class]);
            throw new FreeAiChatException('قائمة الموديلات غير متاحة حاليًا.', 502);
        }
        foreach ($items as $item) {
            if ((string) ($item['id'] ?? '') !== (string) $conversation->selected_model_id) continue;
            if ((string) ($item['provider_model_id'] ?? '') !== (string) $conversation->provider_model_id) continue;
            if (($item['tool_key'] ?? null) !== $source || ($item['operation'] ?? null) !== 'text_generation') continue;
            if (! filter_var($item['is_available'] ?? true, FILTER_VALIDATE_BOOLEAN)) continue;
            $provider = trim((string) ($item['provider'] ?? ''));
            if ($provider === '') continue;
            $conversation->update(['provider' => $provider, 'tool_key' => $source]);
            return [
                'id' => (int) $item['id'],
                'provider' => $provider,
                'provider_model_id' => (string) $item['provider_model_id'],
            ];
        }
        throw new FreeAiChatException('الموديل المختار غير متاح لهذه الأداة.', 422);
    }

    private function updateMessageStatus(ModelsMessage $message, string $status, ?string $reason = null): void
    {
        $metadata = [...($message->metadata ?? []), 'status' => $status];
        if ($reason !== null) $metadata['reason'] = $reason;
        $message->update(['metadata' => $metadata]);
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
