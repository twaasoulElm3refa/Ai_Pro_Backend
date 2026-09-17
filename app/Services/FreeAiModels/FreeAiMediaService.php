<?php

namespace App\Services\FreeAiModels;

use App\Models\ModelsConverstaions;
use App\Models\ModelsCostLogger;
use App\Models\ModelsMessage;
use App\Models\ModelsMessageFile;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\ModelCatalogService;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;
use Brick\Math\RoundingMode;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FreeAiMediaService
{
    private const POINTS_PER_USD = 1_000_000;

    public const OPERATIONS = [
        'image_generation',
        'background_remove',
        'image_upscale',
        'image_edit',
        'remove_element',
        'restore',
        'outpaint',
        'resize',
        'video_generation',
    ];

    public const FILE_OPERATIONS = [
        'background_remove',
        'image_upscale',
        'image_edit',
        'remove_element',
        'restore',
        'outpaint',
        'resize',
    ];

    public function __construct(private readonly ModelCatalogService $catalogs) {}

    public function execute(
        ModelsConverstaions $conversation,
        string $operation,
        array $parameters,
        string $message,
        string $requestId,
        ?UploadedFile $file = null
    ): array {
        try {
            return Cache::lock("free-ai-media-user-{$conversation->user_id}", 330)
                ->block(3, fn () => $this->executeLocked(
                    $conversation,
                    $operation,
                    $parameters,
                    $message,
                    $requestId,
                    $file
                ));
        } catch (LockTimeoutException) {
            throw new FreeAiChatException('A previous media request is still being processed.', 429, '3');
        }
    }

    private function executeLocked(
        ModelsConverstaions $conversation,
        string $operation,
        array $parameters,
        string $message,
        string $requestId,
        ?UploadedFile $file
    ): array {
        $conversation->refresh();
        if ($conversation->trashed() || $conversation->is_archived) {
            throw new FreeAiChatException('Conversation is unavailable.', 404);
        }
        if ($conversation->selected_model_source !== 'general_media'
            || $conversation->catalog_operation !== $operation
            || ! in_array($operation, self::OPERATIONS, true)) {
            throw new FreeAiChatException('The conversation model is unavailable for this media operation.', 422);
        }
        if (in_array($operation, self::FILE_OPERATIONS, true) && ! $file) {
            throw new FreeAiChatException('An image file is required for this operation.', 422);
        }

        $selection = $this->validatedSelection($conversation, $operation);
        $parameters = $this->validatedParameters($parameters, $selection['parameter_schema']);
        $originalFilename = $file
            ? basename(str_replace('\\', '/', $file->getClientOriginalName()))
            : null;
        $displayMessage = trim($message) !== '' ? trim($message) : str_replace('_', ' ', $operation);
        $requestMetadata = [
            'status' => 'pending',
            'tool_type' => 'general_media',
            'operation' => $operation,
            'parameters' => $parameters,
            ...($originalFilename ? ['original_filename' => $originalFilename] : []),
        ];

        $previous = $conversation->messages()
            ->where('request_id', $requestId)
            ->where('role', 'user')
            ->first();
        if ($previous) {
            if ($previous->content !== $displayMessage
                || data_get($previous->metadata, 'operation') !== $operation
                || data_get($previous->metadata, 'parameters') !== $parameters
                || data_get($previous->metadata, 'original_filename') !== $originalFilename) {
                throw new FreeAiChatException('Request ID was already used for another media request.', 409);
            }
            $assistant = $conversation->messages()
                ->where('request_id', $requestId)
                ->where('role', 'assistant')
                ->first();
            if ($assistant) {
                return $this->result($previous, $assistant, $this->walletSnapshot($conversation->user_id));
            }
            throw new FreeAiChatException('The previous request did not finish. Please send a new request.', 409);
        }

        $wallet = Wallet::query()->where('user_id', $conversation->user_id)->first();
        if (! $wallet || ! $wallet->is_active || $wallet->balance <= 0 || $wallet->payback_balance > 0) {
            throw new FreeAiChatException('Your wallet balance is insufficient.', 402);
        }

        $userMessage = $conversation->messages()->create([
            'user_id' => $conversation->user_id,
            'role' => 'user',
            'content' => $displayMessage,
            'request_id' => $requestId,
            'metadata' => $requestMetadata,
        ]);
        if (! $conversation->title) {
            $conversation->update(['title' => mb_substr($displayMessage, 0, 80)]);
        }

        $key = trim((string) config('services.aiarabic.internal_api_key'));
        if ($key === '') {
            $this->updateMessageStatus($userMessage, 'failed', 'configuration');
            throw new FreeAiChatException('AI service is not configured.', 503);
        }

        $providerPayload = [
            'user_id' => (int) $conversation->user_id,
            'model_id' => (int) $conversation->model_id,
            'selected_model_id' => (int) $selection['id'],
            'conversation_uuid' => $conversation->uuid,
            'user_message' => trim($message),
            'state' => [
                'operation' => $operation,
                'parameters' => $parameters,
            ],
            'debug' => true,
            'request_id' => $requestId,
        ];
        $url = rtrim((string) config('services.aiarabic.base_url', 'https://api.aiarabic.com'), '/')
            .'/tasks/general-media';
        $http = Http::withHeaders(['x-internal-api-key' => $key])
            ->acceptJson()
            ->asMultipart()
            ->connectTimeout(10)
            ->timeout(300);
        $handle = null;
        if ($file) {
            $handle = @fopen($file->getRealPath(), 'r');
            if ($handle === false) {
                $this->updateMessageStatus($userMessage, 'failed', 'file');
                throw new FreeAiChatException('The uploaded media file could not be read.', 422);
            }
            $http = $http->attach(
                'file',
                $handle,
                $originalFilename,
                ['Content-Type' => $file->getMimeType() ?: 'application/octet-stream']
            );
        }

        $started = microtime(true);
        try {
            $response = $http->post($url, [[
                'name' => 'payload',
                'contents' => json_encode(
                    $providerPayload,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                ),
            ]]);
        } catch (ConnectionException $exception) {
            $this->updateMessageStatus($userMessage, 'failed', 'connection');
            Log::warning('Free AI media connection failed.', [
                'user_id' => $conversation->user_id,
                'conversation_id' => $conversation->id,
                'operation' => $operation,
                'request_id' => $requestId,
                'exception' => $exception::class,
            ]);
            throw new FreeAiChatException('Unable to connect to the media service.', 504);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        $elapsed = (int) round((microtime(true) - $started) * 1000);
        $payload = $response->json();
        if (! $response->successful() || ! is_array($payload) || ($payload['success'] ?? false) !== true) {
            $this->updateMessageStatus($userMessage, 'failed', 'provider');
            throw new FreeAiChatException(
                'The media service could not complete the request.',
                $response->status() === 429 ? 429 : 502,
                $response->status() === 429 ? $response->header('Retry-After') : null
            );
        }
        if (($payload['tool'] ?? null) !== 'general_media') {
            $this->updateMessageStatus($userMessage, 'failed', 'invalid_response');
            throw new FreeAiChatException('The media service returned an invalid response.', 502);
        }

        $providerFiles = $payload['files'] ?? [];
        if (! is_array($providerFiles)) {
            $this->updateMessageStatus($userMessage, 'failed', 'invalid_response');
            throw new FreeAiChatException('The media service returned invalid files.', 502);
        }
        try {
            $files = $this->normalizeFiles($providerFiles);
        } catch (FreeAiChatException $exception) {
            $this->updateMessageStatus($userMessage, 'failed', 'invalid_files');
            throw $exception;
        }
        $content = is_string($payload['content'] ?? null) ? trim($payload['content']) : '';
        if ($content === '' && $files === []) {
            $this->updateMessageStatus($userMessage, 'failed', 'empty_response');
            throw new FreeAiChatException('The media service returned an empty response.', 502);
        }
        if ($content === '') {
            $content = 'Media generated successfully.';
        }

        [$input, $output, $reasoning, $total, $totalCost] = $this->usage($payload, $userMessage);
        $provider = trim((string) ($payload['provider'] ?? $selection['provider'])) ?: $selection['provider'];
        $providerModelId = trim((string) (
            $payload['provider_model_id'] ?? $payload['model'] ?? $selection['provider_model_id']
        )) ?: $selection['provider_model_id'];
        $providerMetadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $mediaMetadata = [
            'operation' => $operation,
            'model' => $providerModelId,
            'provider' => $provider,
            'size' => $providerMetadata['size'] ?? $providerMetadata['requested_size'] ?? ($parameters['size'] ?? null),
            'quality' => $providerMetadata['quality'] ?? ($parameters['quality'] ?? null),
            'actual_size' => $providerMetadata['actual_size'] ?? null,
            'provider_cost_usd' => $providerMetadata['provider_cost_usd'] ?? data_get($payload, 'cost.total_cost'),
            'task_uuid' => $providerMetadata['task_uuid'] ?? ($payload['task_uuid'] ?? null),
            'files' => $files,
        ];
        $assistantMetadata = [
            'tool_type' => 'general_media',
            ...$mediaMetadata,
            'parameters' => $parameters,
            'provider_metadata' => $providerMetadata,
        ];

        return DB::transaction(function () use (
            $conversation,
            $userMessage,
            $requestId,
            $content,
            $assistantMetadata,
            $provider,
            $providerModelId,
            $input,
            $output,
            $reasoning,
            $total,
            $totalCost,
            $elapsed,
            $parameters,
            $files,
            $mediaMetadata,
            $payload
        ): array {
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
                'metadata' => $assistantMetadata,
            ]);
            $assistant->files()->createMany($files);
            $logger = ModelsCostLogger::create([
                'user_id' => $conversation->user_id,
                'models_conversation_id' => $conversation->id,
                'model_id' => $conversation->model_id,
                'assistant_message_id' => $assistant->id,
                'provider' => $provider,
                'provider_model_id' => $providerModelId,
                'request_id' => $requestId,
                'input_tokens' => $input,
                'output_tokens' => $output,
                'reasoning_tokens' => $reasoning,
                'total_tokens' => $total,
                'input_cost' => $this->cost(data_get($payload, 'usage.input_cost')),
                'output_cost' => $this->cost(data_get($payload, 'usage.output_cost')),
                'total_cost' => $totalCost,
                'response_time_ms' => $elapsed,
                'metadata' => [
                    'tool_type' => 'general_media',
                    ...$mediaMetadata,
                    'parameters' => $parameters,
                    'response' => $payload,
                ],
            ]);
            WalletTransaction::create([
                'user_id' => $conversation->user_id,
                'wallet_id' => $wallet->id,
                'payment_id' => null,
                'models_cost_logger_id' => $logger->id,
                'points' => $total,
                'type' => 'debit',
                'description' => 'Free AI media usage',
                'balance_before' => $before,
                'balance_after' => $wallet->balance,
                'payback_before' => $paybackBefore,
                'payback_after' => $wallet->payback_balance,
                'slug' => 'free-ai-media-'.$requestId,
            ]);
            $this->updateMessageStatus($userMessage, 'completed');
            $conversation->touch();

            DB::afterCommit(function () use ($conversation): void {
                try {
                    Cache::tags(['wallet', 'transactions', "user_{$conversation->user_id}"])->flush();
                } catch (Throwable $exception) {
                    Log::warning('Free AI media wallet cache invalidation failed.', [
                        'user_id' => $conversation->user_id,
                        'exception' => $exception::class,
                    ]);
                }
            });

            return $this->result($userMessage, $assistant->load('files'), [
                'balance' => (int) $wallet->balance,
                'payback_balance' => (int) $wallet->payback_balance,
            ]);
        }, 3);
    }

    private function validatedSelection(ModelsConverstaions $conversation, string $operation): array
    {
        if (! $conversation->selected_model_id) {
            throw new FreeAiChatException('Select an available model first.', 422);
        }
        try {
            $items = $this->catalogs->getModels('general_media', $operation)['items'] ?? [];
        } catch (Throwable $exception) {
            Log::warning('Free AI media catalog unavailable.', [
                'operation' => $operation,
                'exception' => $exception::class,
            ]);
            throw new FreeAiChatException('The media model catalog is currently unavailable.', 502);
        }
        foreach ($items as $item) {
            if ((string) ($item['id'] ?? '') !== (string) $conversation->selected_model_id) {
                continue;
            }
            if ((string) ($item['provider_model_id'] ?? '') !== (string) $conversation->provider_model_id) {
                continue;
            }
            if (($item['tool_key'] ?? null) !== 'general_media' || ($item['operation'] ?? null) !== $operation) {
                continue;
            }
            if (! filter_var($item['is_available'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }
            $provider = trim((string) ($item['provider'] ?? ''));
            $providerModelId = trim((string) ($item['provider_model_id'] ?? ''));
            if ($provider === '' || $providerModelId === '') {
                continue;
            }
            $conversation->update(['provider' => $provider, 'tool_key' => 'general_media']);

            return [
                'id' => (int) $item['id'],
                'provider' => $provider,
                'provider_model_id' => $providerModelId,
                'parameter_schema' => is_array($item['parameter_schema'] ?? null)
                    ? $item['parameter_schema']
                    : [],
            ];
        }
        throw new FreeAiChatException('The selected model is unavailable for this media operation.', 422);
    }

    private function validatedParameters(array $parameters, array $schema): array
    {
        $validated = [];
        foreach ($schema as $name => $definition) {
            if (! is_string($name) || ! is_array($definition)) {
                continue;
            }
            $hasValue = array_key_exists($name, $parameters);
            $value = $hasValue ? $parameters[$name] : ($definition['default'] ?? null);
            $nullable = filter_var($definition['nullable'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $required = filter_var($definition['required'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($value === null || $value === '') {
                if ($required && ! $nullable) {
                    throw new FreeAiChatException("The {$name} parameter is required.", 422);
                }
                if ($nullable) {
                    $validated[$name] = null;
                }

                continue;
            }

            $type = is_array($definition['values'] ?? null) || is_array($definition['enum'] ?? null)
                ? 'enum'
                : strtolower((string) ($definition['type'] ?? 'string'));
            if ($type === 'enum') {
                $values = is_array($definition['values'] ?? null)
                    ? $definition['values']
                    : (is_array($definition['enum'] ?? null) ? $definition['enum'] : []);
                if (! in_array($value, $values, true)) {
                    throw new FreeAiChatException("The {$name} parameter is invalid.", 422);
                }
            } elseif ($type === 'integer') {
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    throw new FreeAiChatException("The {$name} parameter must be an integer.", 422);
                }
                $value = (int) $value;
                $this->assertRange($name, $value, $definition);
            } elseif (in_array($type, ['number', 'float'], true)) {
                if (is_bool($value) || ! is_numeric($value)) {
                    throw new FreeAiChatException("The {$name} parameter must be a number.", 422);
                }
                $value = (float) $value;
                $this->assertRange($name, $value, $definition);
            } elseif ($type === 'boolean') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($value === null) {
                    throw new FreeAiChatException("The {$name} parameter must be true or false.", 422);
                }
            } elseif (! is_scalar($value)) {
                throw new FreeAiChatException("The {$name} parameter is invalid.", 422);
            } else {
                $value = (string) $value;
            }
            $validated[$name] = $value;
        }

        return $validated;
    }

    private function assertRange(string $name, int|float $value, array $definition): void
    {
        $minimum = $definition['minimum'] ?? $definition['min'] ?? null;
        $maximum = $definition['maximum'] ?? $definition['max'] ?? null;
        if (is_numeric($minimum) && $value < (float) $minimum) {
            throw new FreeAiChatException("The {$name} parameter is below its minimum.", 422);
        }
        if (is_numeric($maximum) && $value > (float) $maximum) {
            throw new FreeAiChatException("The {$name} parameter exceeds its maximum.", 422);
        }
    }

    private function usage(array $payload, ModelsMessage $userMessage): array
    {
        $usage = data_get($payload, 'provider_usage', data_get($payload, 'metadata.provider_usage'));
        if (is_array($usage)) {
            $input = $this->tokenCount($usage['prompt_tokens'] ?? 0);
            $output = $this->tokenCount($usage['completion_tokens'] ?? 0);
            $reasoning = $this->tokenCount(
                $usage['reasoning_tokens'] ?? data_get($usage, 'completion_tokens_details.reasoning_tokens', 0)
            );
            if ($input === null || $output === null || $reasoning === null) {
                $this->updateMessageStatus($userMessage, 'failed', 'invalid_usage');
                throw new FreeAiChatException('The media service returned invalid usage data.', 502);
            }
            $total = $input + $output + $reasoning;
            if ($total > 0) {
                return [$input, $output, $reasoning, $total, $this->cost(data_get($payload, 'cost.total_cost'))];
            }
        }

        $rawCost = data_get($payload, 'cost.total_cost');
        if (is_bool($rawCost) || ! is_scalar($rawCost) || ! is_numeric(trim((string) $rawCost))) {
            $this->updateMessageStatus($userMessage, 'failed', 'invalid_cost');
            throw new FreeAiChatException('The media service returned invalid cost data.', 502);
        }
        try {
            $cost = BigDecimal::of(trim((string) $rawCost));
            if ($cost->isNegative()) {
                $this->updateMessageStatus($userMessage, 'failed', 'invalid_cost');
                throw new FreeAiChatException('The media service returned invalid cost data.', 502);
            }
            $total = $cost->multipliedBy(self::POINTS_PER_USD)->toScale(0, RoundingMode::HALF_UP)->toInt();
            $storedCost = $cost->toScale(8, RoundingMode::HALF_UP)->__toString();
        } catch (MathException) {
            $this->updateMessageStatus($userMessage, 'failed', 'invalid_cost');
            throw new FreeAiChatException('The media service returned invalid cost data.', 502);
        }

        return [0, 0, 0, $total, $storedCost];
    }

    private function tokenCount(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }
        if (! is_string($value) || ! ctype_digit($value)) {
            return null;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT);

        return $parsed === false || $parsed < 0 ? null : $parsed;
    }

    private function cost(mixed $value): string
    {
        return is_numeric($value) && (float) $value >= 0
            ? number_format((float) $value, 8, '.', '')
            : '0.00000000';
    }

    private function normalizeFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $file) {
            if (! is_array($file)) {
                throw new FreeAiChatException('The media service returned invalid files.', 502);
            }

            $fileId = trim((string) ($file['file_id'] ?? ''));
            $filename = basename(str_replace('\\', '/', trim((string) ($file['filename'] ?? ''))));
            $contentType = strtolower(trim((string) ($file['content_type'] ?? '')));
            $rawUrl = trim((string) ($file['download_url'] ?? $file['url'] ?? ''));
            if ($fileId === '' || mb_strlen($fileId) > 128
                || $filename === '' || mb_strlen($filename) > 255
                || $contentType === '' || mb_strlen($contentType) > 150
                || $rawUrl === '') {
                throw new FreeAiChatException('The media service returned invalid files.', 502);
            }

            $size = $file['size_bytes'] ?? null;
            if ($size !== null && (filter_var($size, FILTER_VALIDATE_INT) === false || (int) $size < 0)) {
                throw new FreeAiChatException('The media service returned invalid files.', 502);
            }

            if (! $this->isValidDownloadUrl($rawUrl)) {
                throw new FreeAiChatException('The media service returned invalid files.', 502);
            }

            $knownKeys = array_flip(['file_id', 'filename', 'content_type', 'download_url', 'url', 'size_bytes', 'metadata']);
            $extra = array_diff_key($file, $knownKeys);
            $fileMetadata = is_array($file['metadata'] ?? null) ? $file['metadata'] : [];
            if ($extra !== []) {
                $fileMetadata['provider'] = $extra;
            }

            $normalized[] = [
                'file_id' => $fileId,
                'filename' => $filename,
                'content_type' => $contentType,
                'download_url' => $rawUrl,
                'size_bytes' => $size === null ? null : (int) $size,
                'metadata' => $fileMetadata === [] ? null : $fileMetadata,
            ];
        }

        return $normalized;
    }

    private function isValidDownloadUrl(string $url): bool
    {
        if ($url === '' || mb_strlen($url) > 2048 || str_contains($url, "\r") || str_contains($url, "\n")) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return str_starts_with($url, '/tasks/generated-files/download/');
        }

        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && trim((string) ($parts['host'] ?? '')) !== '';
    }

    private function updateMessageStatus(ModelsMessage $message, string $status, ?string $reason = null): void
    {
        $metadata = [...($message->metadata ?? []), 'status' => $status];
        if ($reason !== null) {
            $metadata['reason'] = $reason;
        }
        $message->update(['metadata' => $metadata]);
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
        $publicMessage = static function (ModelsMessage $message): array {
            if (! $message->relationLoaded('files')) {
                $message->load('files');
            }

            return [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
                'request_id' => $message->request_id,
                'metadata' => $message->metadata,
                'attachments' => $message->files
                    ->map(static fn (ModelsMessageFile $file): array => $file->attachmentPayload())
                    ->values()
                    ->all(),
                'created_at' => $message->created_at?->toISOString(),
            ];
        };

        return [
            'user_message' => $publicMessage($user),
            'assistant_message' => $publicMessage($assistant),
            'wallet' => $wallet,
        ];
    }
}
