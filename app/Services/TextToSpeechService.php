<?php

namespace App\Services;

use App\Models\Conversation;
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

class TextToSpeechService
{
    public const SUB_TOOL_ID = 27;

    public const TOOL_KEY = 'text_to_speech';

    public const MODEL_KEY = 'text_to_speech';

    public const ENDPOINT = 'tasks/text-to-speech';

    public const DEFAULT_PROVIDER = 'openrouter';

    public const DEFAULT_MODEL = 'fish-audio/s2.1-pro-free:free';

    private const ALLOWED_DOWNLOAD_PATH = '#^/tasks/generated-files/download/[A-Za-z0-9-]+$#';

    private const MAX_AUDIO_BYTES = 50 * 1024 * 1024;

    private const DEFAULT_STATE = [
        'provider' => null,
        'model' => null,
        'voice' => 'alloy',
        'response_format' => 'mp3',
        'speed' => 1.0,
        'extra_options' => [],
        'last_output' => null,
    ];

    public function __construct(
        private readonly AiArabicWriterService $writerService,
        private readonly ConversationMessageCacheService $messageCache
    ) {}

    public static function supports(int $subToolId, ?string $toolKey = null, ?string $modelKey = null): bool
    {
        $toolKey = strtolower(trim((string) $toolKey));
        $modelKey = strtolower(trim((string) $modelKey));

        return $subToolId === self::SUB_TOOL_ID
            && ($toolKey === '' || $toolKey === self::TOOL_KEY)
            && ($modelKey === '' || $modelKey === self::MODEL_KEY);
    }

    public function handle(Conversation $conversation, array $data, string $text, int $userId): array
    {
        if ((int) $conversation->user_id !== $userId) {
            abort(403, 'Conversation does not belong to the authenticated user.');
        }

        if ((int) $conversation->sub_tool_id !== self::SUB_TOOL_ID) {
            abort(422, 'This conversation is not a text-to-speech conversation.');
        }

        $text = trim($text);
        if ($text === '') {
            abort(422, 'Text to convert to speech is required.');
        }

        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? '')) ?: (string) Str::uuid();
        $lock = Cache::lock("text-to-speech:{$userId}:{$conversation->id}:{$idempotencyKey}", 360);

        return $lock->block(30, fn (): array => $this->handleLocked(
            $conversation,
            $data,
            $text,
            $userId,
            $idempotencyKey
        ));
    }

    private function handleLocked(
        Conversation $conversation,
        array $data,
        string $text,
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
        $providerRequest = [
            'user_id' => $userId,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => (string) $conversation->uuid,
            'user_message' => $text,
            'state' => $state,
            'debug' => (bool) ($data['debug'] ?? false),
        ];

        $userMessage = $existingUserMessage ?: Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $text,
            'idempotency_key' => $idempotencyKey,
            'is_error' => false,
            'metadata' => [
                'type' => 'text_to_speech_request',
                'tool' => self::TOOL_KEY,
                'tool_key' => self::TOOL_KEY,
                'model_key' => self::MODEL_KEY,
                'sub_tool_id' => self::SUB_TOOL_ID,
                'conversation_uuid' => (string) $conversation->uuid,
                'request_prompt' => $text,
                'state' => $state,
                'request_payload' => $providerRequest,
            ],
        ]);

        $userMessage->setRelation('conversation', $conversation);
        if ($userMessage->wasRecentlyCreated) {
            $this->messageCache->updateAfterMessage($userMessage);
            $this->clearCache($userId);
        }

        try {
            $normalizedResponse = $this->writerService->generateReplyWithUsage(
                $providerRequest,
                $this->resolveEndpoint($conversation)
            );
            $normalizedResponse = is_array($normalizedResponse)
                ? $normalizedResponse
                : ['reply' => (string) $normalizedResponse];
            $providerPayload = $this->providerPayload($normalizedResponse);
            $this->assertValidProviderResponse($providerPayload);
            $providerFiles = $this->providerFiles($normalizedResponse, $providerPayload);

            if ($providerFiles === []) {
                throw new RuntimeException('Text-to-speech provider returned no generated audio files.');
            }

            [$storedFiles, $failedFiles] = $this->downloadFiles(
                $providerFiles,
                $userId,
                (string) $conversation->uuid
            );

            if ($storedFiles === []) {
                throw new RuntimeException('The generated audio could not be downloaded.');
            }

            return $this->persistSuccess(
                $conversation,
                $userMessage,
                $providerRequest,
                $normalizedResponse,
                $providerPayload,
                $storedFiles,
                $failedFiles,
                $userId
            );
        } catch (Throwable $exception) {
            Log::warning('Text-to-speech request failed.', [
                'user_id' => $userId,
                'conversation_id' => $conversation->id,
                'sub_tool_id' => self::SUB_TOOL_ID,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->persistError($conversation, $userMessage, $state, $userId);
        }
    }

    private function assertValidProviderResponse(array $response): void
    {
        if (($response['success'] ?? false) !== true) {
            throw new RuntimeException('Text-to-speech provider returned an unsuccessful response.');
        }

        if (strtolower(trim((string) ($response['tool'] ?? ''))) !== self::TOOL_KEY) {
            throw new RuntimeException('Text-to-speech provider returned an unexpected tool.');
        }

        if ((int) ($response['sub_tool_id'] ?? 0) !== self::SUB_TOOL_ID) {
            throw new RuntimeException('Text-to-speech provider returned an unexpected sub tool.');
        }
    }

    private function downloadFiles(array $files, int $userId, string $conversationUuid): array
    {
        $stored = [];
        $failed = [];

        foreach (array_slice($files, 0, 8) as $index => $file) {
            try {
                if (! is_array($file)) {
                    throw new RuntimeException('Invalid generated audio metadata.');
                }

                $stored[] = $this->downloadFile($file, $userId, $conversationUuid, $index);
            } catch (Throwable $exception) {
                $failed[] = [
                    'source_file_id' => $this->scalarString(is_array($file) ? ($file['file_id'] ?? null) : null),
                    'message' => 'One generated audio file could not be saved.',
                ];

                Log::warning('Generated audio download failed.', [
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

    private function downloadFile(array $file, int $userId, string $conversationUuid, int $index): array
    {
        $sourceFileId = $this->scalarString($file['file_id'] ?? null);
        if ($sourceFileId === null) {
            throw new RuntimeException('Generated audio file ID is missing.');
        }

        $targetUrl = $this->trustedDownloadUrl(trim((string) ($file['download_url'] ?? '')));
        $declaredMime = strtolower(trim((string) ($file['content_type'] ?? '')));
        if (! in_array($declaredMime, ['audio/mpeg', 'audio/mp3'], true)) {
            throw new RuntimeException('Generated file metadata contains an unsupported audio type.');
        }

        $declaredSize = (int) ($file['size_bytes'] ?? 0);
        if ($declaredSize > self::MAX_AUDIO_BYTES) {
            throw new RuntimeException('Generated audio exceeds the maximum allowed size.');
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
            'Accept' => 'audio/mpeg',
        ])
            ->connectTimeout(10)
            ->timeout(180)
            ->retry(2, 500, null, false)
            ->get($targetUrl);

        $this->validateDownloadResponse($response);
        $body = $response->body();
        $size = strlen($body);
        if ($size === 0 || $size > self::MAX_AUDIO_BYTES || ! $this->isMp3($body)) {
            throw new RuntimeException('Generated file is not a valid MP3 audio file.');
        }

        $responseMime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        if ($responseMime !== '' && ! in_array($responseMime, ['audio/mpeg', 'audio/mp3', 'application/octet-stream'], true)) {
            throw new RuntimeException('Generated audio response has an unsupported content type.');
        }

        $publicId = (string) Str::uuid();
        $providerFilename = basename(trim((string) ($file['filename'] ?? '')));
        $filename = preg_match('/^[A-Za-z0-9._ -]+\.mp3$/i', $providerFilename) === 1
            ? $providerFilename
            : 'generated-speech-'.($index + 1).'.mp3';
        $path = "generated-audio/{$userId}/{$conversationUuid}/{$publicId}.mp3";

        if (! Storage::disk('local')->put($path, $body)) {
            throw new RuntimeException('Generated audio could not be saved.');
        }

        return [
            'public_id' => $publicId,
            'source_file_id' => $sourceFileId,
            'filename' => $filename,
            'content_type' => 'audio/mpeg',
            'size_bytes' => $size,
            'path' => $path,
            'disk' => 'local',
        ];
    }

    private function persistSuccess(
        Conversation $conversation,
        Message $userMessage,
        array $providerRequest,
        array $normalizedResponse,
        array $providerPayload,
        array $storedFiles,
        array $failedFiles,
        int $userId
    ): array {
        $requestId = $this->scalarString($providerPayload['request_id'] ?? $normalizedResponse['request_id'] ?? null);
        $provider = $this->scalarString($providerPayload['provider'] ?? $normalizedResponse['provider'] ?? null)
            ?? $this->configuredValue($conversation, 'provider')
            ?? self::DEFAULT_PROVIDER;
        $model = $this->scalarString($providerPayload['model'] ?? $providerPayload['model_key'] ?? $normalizedResponse['model_key'] ?? null)
            ?? $this->configuredValue($conversation, 'model')
            ?? self::DEFAULT_MODEL;
        $message = $this->scalarString($providerPayload['message'] ?? $normalizedResponse['reply'] ?? null)
            ?? 'Speech generated successfully.';
        $providerMetadata = $this->sanitizedProviderMetadata($providerPayload['metadata'] ?? null);
        $publicFiles = array_map(fn (array $file): array => $this->publicFileData($file), $storedFiles);
        $responseState = $this->normalizeState($providerRequest['state']);
        $responseState['last_output'] = [
            'request_id' => $requestId,
            'file_ids' => array_column($publicFiles, 'id'),
        ];
        $storedPaths = array_column($storedFiles, 'path');

        try {
            $assistantMessage = DB::transaction(function () use (
                $conversation,
                $userMessage,
                $providerRequest,
                $responseState,
                $publicFiles,
                $storedFiles,
                $failedFiles,
                $providerMetadata,
                $provider,
                $model,
                $message,
                $requestId,
                $normalizedResponse,
                $userId
            ): Message {
                $assistant = Message::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $message,
                    'is_error' => false,
                    'reply_to_message_id' => $userMessage->id,
                    'metadata' => [
                        'success' => true,
                        'type' => 'result',
                        'tool' => self::TOOL_KEY,
                        'tool_key' => self::TOOL_KEY,
                        'provider' => $provider,
                        'model' => $model,
                        'model_key' => $model,
                        'request_id' => $requestId,
                        'user_id' => $userId,
                        'sub_tool_id' => self::SUB_TOOL_ID,
                        'conversation_uuid' => (string) $conversation->uuid,
                        'message' => $message,
                        'state' => $responseState,
                        'request_prompt' => $providerRequest['user_message'],
                        'request_payload' => $providerRequest,
                        'files' => $publicFiles,
                        'count' => count($publicFiles),
                        'failed_files' => $failedFiles,
                        'provider_metadata' => $providerMetadata,
                        'usage' => $normalizedResponse['usage'] ?? null,
                        'cost' => $normalizedResponse['cost'] ?? null,
                        'points_deducted' => 0,
                        'billing_source' => null,
                    ],
                ]);

                foreach ($storedFiles as $file) {
                    GeneratedImage::create([
                        'public_id' => $file['public_id'],
                        'user_id' => $userId,
                        'conversation_id' => $conversation->id,
                        'message_id' => $assistant->id,
                        'sub_tool_id' => self::SUB_TOOL_ID,
                        'source_file_id' => $file['source_file_id'],
                        'filename' => $file['filename'],
                        'path' => $file['path'],
                        'disk' => $file['disk'],
                        'content_type' => $file['content_type'],
                        'size_bytes' => $file['size_bytes'],
                        'metadata' => [
                            'media_type' => 'audio',
                            'request_id' => $requestId,
                            'provider' => $provider,
                            'model' => $model,
                        ],
                    ]);
                }

                return $assistant;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        $assistantMessage->setRelation('conversation', $conversation);
        $this->messageCache->updateAfterMessage($assistantMessage);
        $this->clearCache($userId);

        return $this->responseFromAssistant($assistantMessage, $conversation, $userId);
    }

    private function persistError(Conversation $conversation, Message $userMessage, array $state, int $userId): array
    {
        $message = 'Speech could not be generated right now. Please try again.';
        $assistant = Message::updateOrCreate(
            ['reply_to_message_id' => $userMessage->id],
            [
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $message,
                'is_error' => true,
                'metadata' => [
                    'success' => false,
                    'type' => 'error',
                    'tool' => self::TOOL_KEY,
                    'tool_key' => self::TOOL_KEY,
                    'sub_tool_id' => self::SUB_TOOL_ID,
                    'conversation_uuid' => (string) $conversation->uuid,
                    'message' => $message,
                    'state' => $state,
                    'files' => [],
                    'count' => 0,
                    'points_deducted' => 0,
                ],
            ]
        );
        $assistant->setRelation('conversation', $conversation);
        $this->messageCache->updateAfterMessage($assistant);
        $this->clearCache($userId);

        return $this->responseFromAssistant($assistant, $conversation, $userId);
    }

    private function responseFromAssistant(Message $assistant, Conversation $conversation, int $userId): array
    {
        $metadata = is_array($assistant->metadata ?? null) ? $assistant->metadata : [];
        $files = is_array($metadata['files'] ?? null) ? array_values($metadata['files']) : [];

        return [
            'success' => (bool) ($metadata['success'] ?? ! $assistant->is_error),
            'type' => (string) ($metadata['type'] ?? ($assistant->is_error ? 'error' : 'result')),
            'tool' => self::TOOL_KEY,
            'provider' => $metadata['provider'] ?? null,
            'model' => $metadata['model'] ?? $metadata['model_key'] ?? null,
            'user_id' => $userId,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => (string) $conversation->uuid,
            'message' => (string) ($metadata['message'] ?? $assistant->content),
            'files' => $files,
            'count' => count($files),
            'request_id' => $metadata['request_id'] ?? null,
            'metadata' => is_array($metadata['provider_metadata'] ?? null) ? $metadata['provider_metadata'] : [],
            'state' => is_array($metadata['state'] ?? null) ? $metadata['state'] : self::DEFAULT_STATE,
            'usage' => $metadata['usage'] ?? null,
            'cost' => $metadata['cost'] ?? null,
            'failed_files' => $metadata['failed_files'] ?? [],
            'points_deducted' => 0,
            'assistant_message_id' => $assistant->id,
        ];
    }

    private function publicFileData(array $file): array
    {
        $id = (string) $file['public_id'];

        return [
            'id' => $id,
            'file_id' => $id,
            'source_file_id' => $file['source_file_id'],
            'filename' => $file['filename'],
            'content_type' => $file['content_type'],
            'size_bytes' => $file['size_bytes'],
            'preview_url' => "/generated-images/{$id}/preview",
            'download_url' => "/generated-images/{$id}/download",
        ];
    }

    private function providerPayload(array $response): array
    {
        $raw = is_array($response['raw'] ?? null) ? $response['raw'] : [];

        return is_array($raw['data'] ?? null)
            ? $raw['data']
            : ($raw !== [] ? $raw : $response);
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

    private function trustedDownloadUrl(string $downloadUrl): string
    {
        if ($downloadUrl === '') {
            throw new RuntimeException('Generated audio URL is missing.');
        }

        $baseUrl = rtrim((string) (
            config('services.ai.base_url')
                ?: config('services.aiarabic.base_url')
                ?: config('services.aiarabic.url')
        ), '/');
        $baseParts = parse_url($baseUrl);
        if (! is_array($baseParts)
            || ! in_array(strtolower((string) ($baseParts['scheme'] ?? '')), ['http', 'https'], true)
            || empty($baseParts['host'])) {
            throw new RuntimeException('The configured AI service URL is invalid.');
        }

        if (str_starts_with($downloadUrl, '/')) {
            if (! preg_match(self::ALLOWED_DOWNLOAD_PATH, $downloadUrl)) {
                throw new RuntimeException('Generated audio path is not allowed.');
            }

            return $baseUrl.$downloadUrl;
        }

        $parts = parse_url($downloadUrl);
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
        $sameOrigin = is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === strtolower((string) $baseParts['scheme'])
            && strtolower((string) ($parts['host'] ?? '')) === strtolower((string) $baseParts['host'])
            && (int) ($parts['port'] ?? $this->defaultPort($parts['scheme'] ?? null))
                === (int) ($baseParts['port'] ?? $this->defaultPort($baseParts['scheme'] ?? null));

        if (! $sameOrigin || ! preg_match(self::ALLOWED_DOWNLOAD_PATH, $path)) {
            throw new RuntimeException('Generated audio URL is not trusted.');
        }

        return $downloadUrl;
    }

    private function validateDownloadResponse(Response $response): void
    {
        if (! $response->successful()) {
            throw new RuntimeException('Generated audio download request failed.');
        }

        if ((int) ($response->header('Content-Length') ?? 0) > self::MAX_AUDIO_BYTES) {
            throw new RuntimeException('Generated audio exceeds the maximum allowed size.');
        }
    }

    private function isMp3(string $body): bool
    {
        if (strlen($body) < 3) {
            return false;
        }

        if (str_starts_with($body, 'ID3')) {
            return true;
        }

        return strlen($body) >= 2
            && ord($body[0]) === 0xFF
            && (ord($body[1]) & 0xE0) === 0xE0;
    }

    private function normalizeState(array $state): array
    {
        $state = array_replace(self::DEFAULT_STATE, $state);

        return [
            'provider' => $this->scalarString($state['provider'] ?? null),
            'model' => $this->scalarString($state['model'] ?? null),
            'voice' => 'alloy',
            'response_format' => 'mp3',
            'speed' => max(0.25, min(4.0, (float) ($state['speed'] ?? 1.0))),
            'extra_options' => array_values(array_filter(array_map(
                fn (mixed $option): string => trim((string) $option),
                is_array($state['extra_options'] ?? null) ? $state['extra_options'] : []
            ))),
            'last_output' => is_array($state['last_output'] ?? null)
                ? $state['last_output']
                : $this->scalarString($state['last_output'] ?? null),
        ];
    }

    private function sanitizedProviderMetadata(mixed $metadata): array
    {
        if (! is_array($metadata)) {
            return [];
        }

        $sanitized = array_filter([
            'model' => $this->scalarString($metadata['model'] ?? null),
            'voice' => $this->scalarString($metadata['voice'] ?? null),
            'format' => $this->scalarString($metadata['format'] ?? null),
            'generation_id' => $this->scalarString($metadata['generation_id'] ?? null),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        if (array_key_exists('provider_cost_usd', $metadata)) {
            $sanitized['provider_cost_usd'] = is_numeric($metadata['provider_cost_usd'])
                ? (float) $metadata['provider_cost_usd']
                : null;
        }

        return $sanitized;
    }

    private function resolveEndpoint(Conversation $conversation): string
    {
        return $this->configuredValue($conversation, 'endpoint')
            ?? $this->scalarString($conversation->subTool?->endpoint)
            ?? self::ENDPOINT;
    }

    private function configuredValue(Conversation $conversation, string $key): ?string
    {
        $conversation->loadMissing('subTool');
        $config = is_array($conversation->subTool?->config ?? null) ? $conversation->subTool->config : [];

        return $this->scalarString($config[$key] ?? null);
    }

    private function scalarString(mixed $value): ?string
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

    private function clearCache(int $userId): void
    {
        try {
            Cache::tags(['conversations', "user_{$userId}"])->flush();
        } catch (Throwable $exception) {
            Log::debug('Text-to-speech tagged cache flush skipped.', ['error' => $exception->getMessage()]);
        }
    }
}
