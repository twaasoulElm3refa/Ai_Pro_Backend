<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\CostLogger;
use App\Models\GeneratedImage;
use App\Models\Message;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GeneratedImageService
{
    public const SUB_TOOL_ID = 21;

    public const TOOL_KEY = 'ai_image_generator';

    private const DEFAULT_ENDPOINT = 'tasks/image-generator/chat';

    private const ALLOWED_DOWNLOAD_PATH = '#^/tasks/generated-files/download/[A-Za-z0-9-]+$#';

    private const ALLOWED_MIME_TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    private const MAX_IMAGE_BYTES = 12 * 1024 * 1024;

    public function __construct(
        private readonly AiArabicWriterService $writerService,
        private readonly ConversationMessageCacheService $messageCache
    ) {}

    public static function supports(int $subToolId, ?string $toolKey = null): bool
    {
        return $subToolId === self::SUB_TOOL_ID
            || strtolower(trim((string) $toolKey)) === self::TOOL_KEY;
    }

    public function handle(
        Conversation $conversation,
        array $data,
        string $prompt,
        int $userId
    ): array {
        if ((int) $conversation->user_id !== $userId) {
            abort(403, 'Conversation does not belong to the authenticated user.');
        }

        if ((int) $conversation->sub_tool_id !== self::SUB_TOOL_ID) {
            abort(422, 'This conversation is not an image generation conversation.');
        }

        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''))
            ?: (string) Str::uuid();
        $lock = Cache::lock(
            "image-generator:{$userId}:{$conversation->id}:{$idempotencyKey}",
            360
        );

        return $lock->block(30, fn (): array => $this->handleLocked(
            $conversation,
            $data,
            $prompt,
            $userId,
            $idempotencyKey
        ));
    }

    private function handleLocked(
        Conversation $conversation,
        array $data,
        string $prompt,
        int $userId,
        string $idempotencyKey
    ): array {
        $existingUserMessage = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'user')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingUserMessage) {
            $existingAssistant = Message::query()
                ->where('reply_to_message_id', $existingUserMessage->id)
                ->where('role', 'assistant')
                ->first();

            if ($existingAssistant) {
                return $this->responseFromAssistant($existingAssistant, $conversation, $userId);
            }
        }

        $state = $this->normalizeState(is_array($data['state'] ?? null) ? $data['state'] : []);
        $requestPayload = [
            'user_id' => $userId,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => (string) $conversation->uuid,
            'user_message' => $prompt,
            'state' => $state,
            'debug' => (bool) ($data['debug'] ?? false),
        ];

        $userMessage = $existingUserMessage ?: Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $prompt,
            'idempotency_key' => $idempotencyKey,
            'is_error' => false,
            'metadata' => [
                'type' => 'image_generation_request',
                'tool' => self::TOOL_KEY,
                'tool_key' => self::TOOL_KEY,
                'sub_tool_id' => self::SUB_TOOL_ID,
                'conversation_uuid' => (string) $conversation->uuid,
                'state' => $state,
                'request_payload' => $requestPayload,
                'regenerate' => (bool) ($data['regenerate'] ?? false),
            ],
        ]);

        $userMessage->setRelation('conversation', $conversation);
        if ($userMessage->wasRecentlyCreated) {
            $this->messageCache->updateAfterMessage($userMessage);
            $this->clearConversationCache($userId);
        }

        try {
            $providerResponse = $this->writerService->generateReplyWithUsage(
                $requestPayload,
                $this->resolveEndpoint($conversation)
            );
            $providerResponse = is_array($providerResponse)
                ? $providerResponse
                : ['reply' => (string) $providerResponse];
            $providerPayload = $this->providerPayload($providerResponse);
            $files = $this->providerFiles($providerResponse, $providerPayload);

            if ($files === []) {
                throw new RuntimeException('The image service returned no generated files.');
            }

            [$storedFiles, $failedFiles] = $this->downloadFiles(
                $files,
                $userId,
                (string) $conversation->uuid
            );

            if ($storedFiles === []) {
                throw new RuntimeException('The generated images could not be downloaded.');
            }

            $assistantMessage = $this->persistResult(
                $conversation,
                $userMessage,
                $requestPayload,
                $state,
                $providerResponse,
                $providerPayload,
                $storedFiles,
                $failedFiles,
                $userId
            );

            return $this->responseFromAssistant($assistantMessage, $conversation, $userId);
        } catch (Throwable $exception) {
            Log::warning('Image generation failed.', [
                'user_id' => $userId,
                'conversation_id' => $conversation->id,
                'sub_tool_id' => self::SUB_TOOL_ID,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $assistantMessage = Message::updateOrCreate(
                ['reply_to_message_id' => $userMessage->id],
                [
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => 'Images could not be generated right now. Please try again.',
                    'is_error' => true,
                    'metadata' => [
                        'success' => false,
                        'type' => 'error',
                        'tool' => self::TOOL_KEY,
                        'sub_tool_id' => self::SUB_TOOL_ID,
                        'conversation_uuid' => (string) $conversation->uuid,
                        'message' => 'Images could not be generated right now. Please try again.',
                        'state' => $state,
                        'request_prompt' => $prompt,
                        'images' => [],
                        'count' => 0,
                    ],
                ]
            );

            $assistantMessage->setRelation('conversation', $conversation);
            $this->refreshMessageCache($assistantMessage, $conversation);

            return $this->responseFromAssistant($assistantMessage, $conversation, $userId);
        }
    }

    private function persistResult(
        Conversation $conversation,
        Message $userMessage,
        array $requestPayload,
        array $state,
        array $providerResponse,
        array $providerPayload,
        array $storedFiles,
        array $failedFiles,
        int $userId
    ): Message {
        $requestId = $this->stringValue(
            $providerResponse['request_id']
                ?? $providerPayload['request_id']
                ?? null
        );
        $provider = $this->stringValue(
            $providerResponse['provider']
                ?? $providerPayload['provider']
                ?? null
        );
        $model = $this->stringValue(
            $providerPayload['model']
                ?? $providerResponse['model_key']
                ?? $providerPayload['model_key']
                ?? null
        );
        $message = $this->stringValue(
            $providerPayload['message']
                ?? $providerResponse['reply']
                ?? null
        ) ?: 'Images generated successfully.';
        $generation = array_filter([
            'provider' => $provider,
            'model' => $model,
            'size' => data_get($providerPayload, 'metadata.size', $state['size']),
            'quality' => data_get($providerPayload, 'metadata.quality', $state['quality']),
            'seeds' => data_get($providerPayload, 'metadata.seeds'),
            'task_uuid' => data_get($providerPayload, 'metadata.task_uuid'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
        $publicImages = collect($storedFiles)
            ->map(fn (array $file): array => $this->publicImageData($file))
            ->values()
            ->all();
        $responseState = $state;
        $responseState['last_output'] = [
            'request_id' => $requestId,
            'image_ids' => array_column($publicImages, 'id'),
        ];
        $usage = $this->normalizeUsage($providerResponse['usage'] ?? $providerPayload['usage'] ?? []);
        $cost = $this->normalizeCost(
            $providerResponse['cost'] ?? $providerPayload['cost'] ?? [],
            data_get($providerPayload, 'metadata.provider_cost_usd')
        );
        $storedPaths = array_column($storedFiles, 'path');

        try {
            $assistantMessage = DB::transaction(function () use (
                $conversation,
                $userMessage,
                $requestPayload,
                $responseState,
                $publicImages,
                $storedFiles,
                $failedFiles,
                $message,
                $provider,
                $model,
                $requestId,
                $generation,
                $usage,
                $cost,
                $userId
            ): Message {
                $assistantMessage = Message::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $message,
                    'is_error' => false,
                    'reply_to_message_id' => $userMessage->id,
                    'metadata' => [
                        'success' => true,
                        'type' => 'image_generation',
                        'tool' => self::TOOL_KEY,
                        'provider' => $provider,
                        'model_key' => $model,
                        'request_id' => $requestId,
                        'user_id' => $userId,
                        'sub_tool_id' => self::SUB_TOOL_ID,
                        'conversation_uuid' => (string) $conversation->uuid,
                        'message' => $message,
                        'state' => $responseState,
                        'request_prompt' => $requestPayload['user_message'],
                        'request_payload' => $requestPayload,
                        'images' => $publicImages,
                        'count' => count($publicImages),
                        'failed_files' => $failedFiles,
                        'generation' => $generation,
                        'usage' => $usage,
                        'cost' => $cost,
                    ],
                ]);

                foreach ($storedFiles as $file) {
                    GeneratedImage::create([
                        'public_id' => $file['public_id'],
                        'user_id' => $userId,
                        'conversation_id' => $conversation->id,
                        'message_id' => $assistantMessage->id,
                        'sub_tool_id' => self::SUB_TOOL_ID,
                        'source_file_id' => $file['source_file_id'],
                        'filename' => $file['filename'],
                        'path' => $file['path'],
                        'disk' => $file['disk'],
                        'content_type' => $file['content_type'],
                        'size_bytes' => $file['size_bytes'],
                        'metadata' => [
                            'request_id' => $requestId,
                            'provider' => $provider,
                            'model' => $model,
                        ],
                    ]);
                }

                if (
                    (int) ($usage['total_tokens'] ?? 0) > 0
                    || (float) ($cost['total_cost'] ?? 0) > 0
                ) {
                    CostLogger::create([
                        'conversation_id' => $conversation->id,
                        'user_id' => $userId,
                        'sub_tool_id' => self::SUB_TOOL_ID,
                        'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
                        'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
                        'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
                        'input_cost' => (float) ($cost['input_cost'] ?? 0),
                        'output_cost' => (float) ($cost['output_cost'] ?? 0),
                        'web_search_cost' => (float) ($cost['web_search_cost'] ?? 0),
                        'total_cost' => (float) ($cost['total_cost'] ?? 0),
                        'currency' => (string) ($cost['currency'] ?? 'USD'),
                        'provider_request_id' => $requestId,
                        'model_key' => $model,
                    ]);
                }

                return $assistantMessage;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        $assistantMessage->setRelation('conversation', $conversation);
        $this->refreshMessageCache($assistantMessage, $conversation);
        $this->clearConversationCache($userId);

        return $assistantMessage;
    }

    private function downloadFiles(array $files, int $userId, string $conversationUuid): array
    {
        $stored = [];
        $failed = [];

        foreach (array_slice($files, 0, 4) as $index => $file) {
            try {
                if (! is_array($file)) {
                    throw new RuntimeException('Invalid generated file metadata.');
                }

                $stored[] = $this->downloadFile($file, $userId, $conversationUuid, $index);
            } catch (Throwable $exception) {
                $failed[] = [
                    'source_file_id' => $this->stringValue(
                        is_array($file) ? ($file['file_id'] ?? null) : null
                    ),
                    'message' => 'One generated image could not be saved.',
                ];

                Log::warning('Generated image download failed.', [
                    'user_id' => $userId,
                    'conversation_uuid' => $conversationUuid,
                    'source_file_id' => is_array($file) ? ($file['file_id'] ?? null) : null,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return [$stored, $failed];
    }

    private function downloadFile(
        array $file,
        int $userId,
        string $conversationUuid,
        int $index
    ): array {
        $downloadUrl = trim((string) ($file['download_url'] ?? ''));
        $targetUrl = $this->trustedDownloadUrl($downloadUrl);
        $declaredSize = (int) ($file['size_bytes'] ?? 0);

        if ($declaredSize > self::MAX_IMAGE_BYTES) {
            throw new RuntimeException('Generated image exceeds the maximum allowed size.');
        }

        $apiKey = trim((string) (
            config('services.ai.internal_api_key')
                ?: config('services.aiarabic.internal_api_key')
        ));

        if ($apiKey === '') {
            throw new RuntimeException('The internal AI API key is not configured.');
        }

        $response = Http::withHeaders([
            'x-internal-api-key' => $apiKey,
            'Accept' => implode(', ', array_keys(self::ALLOWED_MIME_TYPES)),
        ])
            ->connectTimeout(10)
            ->timeout(120)
            ->retry(2, 500, null, false)
            ->get($targetUrl);

        $this->validateDownloadResponse($response);

        $body = $response->body();
        $size = strlen($body);

        if ($size === 0 || $size > self::MAX_IMAGE_BYTES) {
            throw new RuntimeException('Generated image has an invalid size.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = strtolower((string) $finfo->buffer($body));

        if (! array_key_exists($detectedMime, self::ALLOWED_MIME_TYPES)) {
            throw new RuntimeException('Generated file is not a supported image.');
        }

        $declaredMime = strtolower(trim((string) ($file['content_type'] ?? '')));
        if ($declaredMime !== '' && ! array_key_exists($declaredMime, self::ALLOWED_MIME_TYPES)) {
            throw new RuntimeException('Generated file metadata contains an unsupported image type.');
        }

        $publicId = (string) Str::uuid();
        $extension = self::ALLOWED_MIME_TYPES[$detectedMime];
        $filename = 'ai-image-'.($index + 1).'.'.$extension;
        $path = "generated-images/{$userId}/{$conversationUuid}/{$publicId}.{$extension}";

        if (! Storage::disk('local')->put($path, $body)) {
            throw new RuntimeException('Generated image could not be saved.');
        }

        return [
            'public_id' => $publicId,
            'source_file_id' => $this->stringValue($file['file_id'] ?? null),
            'filename' => $filename,
            'content_type' => $detectedMime,
            'size_bytes' => $size,
            'path' => $path,
            'disk' => 'local',
        ];
    }

    private function trustedDownloadUrl(string $downloadUrl): string
    {
        if ($downloadUrl === '') {
            throw new RuntimeException('Generated file URL is missing.');
        }

        $baseUrl = rtrim((string) (
            config('services.ai.base_url')
                ?: config('services.aiarabic.base_url')
                ?: config('services.aiarabic.url')
        ), '/');
        $baseParts = parse_url($baseUrl);

        if (
            ! is_array($baseParts)
            || ! in_array(strtolower((string) ($baseParts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($baseParts['host'])
        ) {
            throw new RuntimeException('The configured AI service URL is invalid.');
        }

        if (str_starts_with($downloadUrl, '/')) {
            if (! preg_match(self::ALLOWED_DOWNLOAD_PATH, $downloadUrl)) {
                throw new RuntimeException('Generated file path is not allowed.');
            }

            return $baseUrl.$downloadUrl;
        }

        $urlParts = parse_url($downloadUrl);
        if (! is_array($urlParts)) {
            throw new RuntimeException('Generated file URL is invalid.');
        }

        $path = (string) ($urlParts['path'] ?? '');
        $sameOrigin = strtolower((string) ($urlParts['scheme'] ?? '')) === strtolower((string) $baseParts['scheme'])
            && strtolower((string) ($urlParts['host'] ?? '')) === strtolower((string) $baseParts['host'])
            && (int) ($urlParts['port'] ?? $this->defaultPort($urlParts['scheme'] ?? null))
                === (int) ($baseParts['port'] ?? $this->defaultPort($baseParts['scheme'] ?? null));

        if (! $sameOrigin || ! preg_match(self::ALLOWED_DOWNLOAD_PATH, $path)) {
            throw new RuntimeException('Generated file URL is not trusted.');
        }

        return $downloadUrl;
    }

    private function validateDownloadResponse(Response $response): void
    {
        if (! $response->successful()) {
            throw new RuntimeException('Generated image download request failed.');
        }

        $contentLength = (int) ($response->header('Content-Length') ?? 0);
        if ($contentLength > self::MAX_IMAGE_BYTES) {
            throw new RuntimeException('Generated image exceeds the maximum allowed size.');
        }
    }

    private function providerPayload(array $response): array
    {
        $raw = is_array($response['raw'] ?? null) ? $response['raw'] : [];

        if (is_array($raw['data'] ?? null)) {
            return $raw['data'];
        }

        return $raw !== [] ? $raw : $response;
    }

    private function providerFiles(array $response, array $providerPayload): array
    {
        $files = $providerPayload['files']
            ?? $response['files']
            ?? data_get($response, 'raw.files')
            ?? data_get($response, 'raw.data.files')
            ?? [];

        return is_array($files) ? array_values($files) : [];
    }

    private function resolveEndpoint(Conversation $conversation): string
    {
        $conversation->loadMissing('subTool');

        return trim((string) (
            data_get($conversation, 'subTool.config.endpoint')
                ?: data_get($conversation, 'subTool.endpoint')
                ?: self::DEFAULT_ENDPOINT
        ));
    }

    private function normalizeState(array $state): array
    {
        return [
            'provider' => $this->stringValue($state['provider'] ?? null),
            'negative_prompt' => trim((string) ($state['negative_prompt'] ?? '')),
            'size' => (string) ($state['size'] ?? '1024x1024'),
            'quality' => (string) ($state['quality'] ?? 'medium'),
            'results_count' => max(1, min(4, (int) ($state['results_count'] ?? 1))),
            'output_format' => strtolower((string) ($state['output_format'] ?? 'png')),
            'seed' => is_numeric($state['seed'] ?? null) ? (int) $state['seed'] : null,
            'extra_options' => is_array($state['extra_options'] ?? null)
                ? array_values($state['extra_options'])
                : [],
            'last_output' => $state['last_output'] ?? null,
        ];
    }

    private function publicImageData(array $file): array
    {
        $id = (string) $file['public_id'];

        return [
            'id' => $id,
            'filename' => $file['filename'],
            'content_type' => $file['content_type'],
            'size_bytes' => $file['size_bytes'],
            'preview_url' => "/api/v1/generated-images/{$id}/preview",
            'download_url' => "/api/v1/generated-images/{$id}/download",
            'source_file_id' => $file['source_file_id'],
        ];
    }

    private function responseFromAssistant(
        Message $assistantMessage,
        Conversation $conversation,
        int $userId
    ): array {
        $metadata = is_array($assistantMessage->metadata) ? $assistantMessage->metadata : [];
        $images = is_array($metadata['images'] ?? null) ? $metadata['images'] : [];

        return [
            'success' => (bool) ($metadata['success'] ?? ! $assistantMessage->is_error),
            'type' => (string) ($metadata['type'] ?? ($assistantMessage->is_error ? 'error' : 'image_generation')),
            'tool' => self::TOOL_KEY,
            'provider' => $metadata['provider'] ?? null,
            'model_key' => $metadata['model_key'] ?? null,
            'user_id' => $userId,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => (string) $conversation->uuid,
            'message' => (string) ($metadata['message'] ?? $assistantMessage->content),
            'state' => is_array($metadata['state'] ?? null) ? $metadata['state'] : null,
            'images' => $images,
            'count' => count($images),
            'metadata' => $metadata['generation'] ?? [],
            'failed_files' => $metadata['failed_files'] ?? [],
            'request_id' => $metadata['request_id'] ?? null,
            'usage' => $metadata['usage'] ?? [],
            'cost' => $metadata['cost'] ?? [],
            'assistant_message_id' => $assistantMessage->id,
        ];
    }

    private function refreshMessageCache(Message $message, Conversation $conversation): void
    {
        if ($message->wasRecentlyCreated) {
            $this->messageCache->updateAfterMessage($message);

            return;
        }

        $this->messageCache->forget((string) $conversation->uuid);
        $this->messageCache->remember($conversation);
    }

    private function clearConversationCache(int $userId): void
    {
        Cache::tags(['conversations', "user_{$userId}"])->flush();
    }

    private function normalizeUsage(mixed $usage): array
    {
        $usage = is_array($usage) ? $usage : [];

        return [
            'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
            'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
            'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
        ];
    }

    private function normalizeCost(mixed $cost, mixed $providerCost): array
    {
        $cost = is_array($cost) ? $cost : [];

        return [
            'input_cost' => (float) ($cost['input_cost'] ?? 0),
            'output_cost' => (float) ($cost['output_cost'] ?? 0),
            'web_search_cost' => (float) ($cost['web_search_cost'] ?? 0),
            'total_cost' => (float) ($cost['total_cost'] ?? (is_numeric($providerCost) ? $providerCost : 0)),
            'currency' => strtoupper((string) ($cost['currency'] ?? 'USD')),
        ];
    }

    private function stringValue(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function defaultPort(mixed $scheme): int
    {
        return strtolower((string) $scheme) === 'https' ? 443 : 80;
    }
}
