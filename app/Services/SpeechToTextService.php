<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SpeechToTextService
{
    public const SUB_TOOL_ID = 26;

    public const TOOL_KEY = 'speech_to_text';

    public const MODEL_KEY = 'speech_to_text';

    public const DEFAULT_PROVIDER = 'openrouter';

    public const DEFAULT_MODEL = 'openai/whisper-large-v3';

    public const ENDPOINT = 'tasks/speech-to-text';

    private const DEFAULT_MESSAGE = 'Transcribe the uploaded Arabic audio accurately.';

    private const DEFAULT_STATE = [
        'provider' => null,
        'language' => 'ar',
        'extra_options' => [],
        'last_output' => null,
    ];

    public function __construct(
        private readonly ConversationMessageCacheService $messageCache,
        private readonly ProviderCostBillingService $billingService
    ) {}

    public static function supports(int $subToolId, ?string $toolKey = null, ?string $modelKey = null): bool
    {
        $toolKey = strtolower(trim((string) $toolKey));
        $modelKey = strtolower(trim((string) $modelKey));

        return $subToolId === self::SUB_TOOL_ID
            && ($toolKey === '' || $toolKey === self::TOOL_KEY)
            && ($modelKey === '' || $modelKey === self::MODEL_KEY);
    }

    public function handle(
        Conversation $conversation,
        array $data,
        ?UploadedFile $uploadedFile,
        int $userId
    ): array {
        if ((int) $conversation->user_id !== $userId) {
            abort(403, 'Conversation does not belong to the authenticated user.');
        }

        if ((int) $conversation->sub_tool_id !== self::SUB_TOOL_ID) {
            abort(422, 'This conversation is not a speech-to-text conversation.');
        }

        if (! $uploadedFile) {
            abort(422, 'An audio file is required.');
        }

        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? '')) ?: (string) Str::uuid();
        $lock = Cache::lock("speech-to-text:{$userId}:{$conversation->id}:{$idempotencyKey}", 360);

        return $lock->block(30, fn (): array => $this->handleLocked(
            $conversation,
            $data,
            $uploadedFile,
            $userId,
            $idempotencyKey
        ));
    }

    private function handleLocked(
        Conversation $conversation,
        array $data,
        UploadedFile $uploadedFile,
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
        $prompt = trim((string) ($data['user_message'] ?? '')) ?: self::DEFAULT_MESSAGE;
        $filename = basename($uploadedFile->getClientOriginalName());
        $requestPayload = [
            'user_id' => $userId,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => (string) $conversation->uuid,
            'user_message' => $prompt,
            'state' => $state,
            'debug' => (bool) ($data['debug'] ?? false),
            'idempotency_key' => $idempotencyKey,
        ];

        $userMessage = $existingUserMessage ?: Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => '🎵 '.$filename,
            'idempotency_key' => $idempotencyKey,
            'is_error' => false,
            'metadata' => [
                'type' => 'speech_to_text_request',
                'tool' => self::TOOL_KEY,
                'tool_key' => self::TOOL_KEY,
                'model_key' => self::MODEL_KEY,
                'sub_tool_id' => self::SUB_TOOL_ID,
                'conversation_uuid' => (string) $conversation->uuid,
                'original_filename' => $filename,
                'request_prompt' => $prompt,
                'state' => $state,
            ],
        ]);

        $userMessage->setRelation('conversation', $conversation);
        if ($userMessage->wasRecentlyCreated) {
            $this->messageCache->updateAfterMessage($userMessage);
            $this->clearCache($userId);
        }

        try {
            $providerResponse = $this->requestProvider(
                $conversation,
                $uploadedFile,
                $requestPayload
            );
            $this->assertValidProviderResponse($providerResponse);

            return $this->persistSuccess(
                $conversation,
                $userMessage,
                $providerResponse,
                $requestPayload,
                $filename,
                $userId
            );
        } catch (Throwable $exception) {
            Log::warning('Speech-to-text request failed.', [
                'user_id' => $userId,
                'conversation_id' => $conversation->id,
                'sub_tool_id' => self::SUB_TOOL_ID,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->persistError(
                $conversation,
                $userMessage,
                $state,
                $filename,
                $userId
            );
        }
    }

    private function requestProvider(
        Conversation $conversation,
        UploadedFile $uploadedFile,
        array $requestPayload
    ): array {
        $baseUrl = rtrim((string) config('services.ai.base_url'), '/');
        $apiKey = trim((string) (config('services.ai.internal_api_key') ?: config('services.aiarabic.internal_api_key')));

        if ($baseUrl === '' || $apiKey === '') {
            throw new RuntimeException('Speech-to-text provider is not configured.');
        }

        $handle = @fopen($uploadedFile->getRealPath(), 'r');
        if ($handle === false) {
            throw new RuntimeException('The uploaded audio file could not be opened.');
        }

        try {
            $response = Http::withHeaders(['x-internal-api-key' => $apiKey])
                ->attach(
                    'file',
                    $handle,
                    $uploadedFile->getClientOriginalName(),
                    ['Content-Type' => $uploadedFile->getMimeType() ?: 'application/octet-stream']
                )
                ->timeout(300)
                ->post($baseUrl.'/'.$this->resolveEndpoint($conversation), [
                    'payload' => json_encode(
                        $requestPayload,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                ]);
        } finally {
            fclose($handle);
        }

        $result = $response->json();
        if (! $response->successful() || ! is_array($result)) {
            throw new RuntimeException('Speech-to-text provider did not complete the request.');
        }

        Log::debug('Speech-to-text provider request completed.', [
            'status' => $response->status(),
            'response_keys' => array_keys($result),
        ]);

        return $result;
    }

    private function assertValidProviderResponse(array $response): void
    {
        if (($response['success'] ?? false) !== true) {
            throw new RuntimeException('Speech-to-text provider returned an unsuccessful response.');
        }

        if (strtolower(trim((string) ($response['tool'] ?? ''))) !== self::TOOL_KEY) {
            throw new RuntimeException('Speech-to-text provider returned an unexpected tool.');
        }

        if ((int) ($response['sub_tool_id'] ?? 0) !== self::SUB_TOOL_ID) {
            throw new RuntimeException('Speech-to-text provider returned an unexpected sub tool.');
        }

        if (trim((string) ($response['transcript'] ?? '')) === '') {
            throw new RuntimeException('Speech-to-text provider returned an empty transcript.');
        }
    }

    private function persistSuccess(
        Conversation $conversation,
        Message $userMessage,
        array $providerResponse,
        array $requestPayload,
        string $filename,
        int $userId
    ): array {
        $transcript = trim((string) $providerResponse['transcript']);
        $providerMetadata = $this->sanitizedProviderMetadata($providerResponse);
        $provider = $this->scalarString($providerResponse['provider'] ?? null)
            ?? $this->configuredValue($conversation, 'provider')
            ?? self::DEFAULT_PROVIDER;
        $model = $this->scalarString($providerResponse['model'] ?? null)
            ?? $this->configuredValue($conversation, 'model')
            ?? self::DEFAULT_MODEL;
        $requestId = $this->scalarString($providerResponse['request_id'] ?? null);
        $detectedLanguage = $this->scalarString($providerResponse['detected_language'] ?? null);
        $duration = is_numeric($providerResponse['duration_seconds'] ?? null)
            ? (float) $providerResponse['duration_seconds']
            : null;
        $responseState = $this->normalizeState($requestPayload['state']);
        $responseState['last_output'] = $transcript;

        $assistantMessage = DB::transaction(function () use (
            $conversation,
            $userMessage,
            $providerResponse,
            $requestPayload,
            $transcript,
            $providerMetadata,
            $provider,
            $model,
            $requestId,
            $userId,
            $detectedLanguage,
            $duration,
            $responseState,
            $filename
        ): Message {
            $billing = $this->billingService->chargeSpeechToTextResponse(
                $userId,
                (int) $conversation->id,
                self::SUB_TOOL_ID,
                $providerResponse,
                (string) $requestPayload['idempotency_key'],
                $model
            );

            return Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $transcript,
                'is_error' => false,
                'reply_to_message_id' => $userMessage->id,
                'metadata' => [
                    'success' => true,
                    'type' => 'result',
                    'tool' => self::TOOL_KEY,
                    'tool_key' => self::TOOL_KEY,
                    'model' => $model,
                    'model_key' => $model,
                    'provider' => $provider,
                    'request_id' => $requestId,
                    'user_id' => $userId,
                    'sub_tool_id' => self::SUB_TOOL_ID,
                    'conversation_uuid' => (string) $conversation->uuid,
                    'transcript' => $transcript,
                    'detected_language' => $detectedLanguage,
                    'duration_seconds' => $duration,
                    'state' => $responseState,
                    'original_filename' => $filename,
                    'request_prompt' => $requestPayload['user_message'],
                    'provider_metadata' => $providerMetadata,
                    'billing_source' => $billing['source'],
                    'points_deducted' => $billing['points_deducted'],
                ],
            ]);
        });

        $assistantMessage->setRelation('conversation', $conversation);
        $this->messageCache->updateAfterMessage($assistantMessage);
        $this->clearCache($userId);

        return $this->responseFromAssistant($assistantMessage, $conversation, $userId);
    }

    private function persistError(
        Conversation $conversation,
        Message $userMessage,
        array $state,
        string $filename,
        int $userId
    ): array {
        $message = 'The audio could not be transcribed right now. Please try again.';
        $assistantMessage = Message::updateOrCreate(
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
                    'model_key' => self::MODEL_KEY,
                    'sub_tool_id' => self::SUB_TOOL_ID,
                    'conversation_uuid' => (string) $conversation->uuid,
                    'message' => $message,
                    'transcript' => null,
                    'state' => $state,
                    'original_filename' => $filename,
                ],
            ]
        );
        $assistantMessage->setRelation('conversation', $conversation);
        $this->messageCache->updateAfterMessage($assistantMessage);
        $this->clearCache($userId);

        return $this->responseFromAssistant($assistantMessage, $conversation, $userId);
    }

    private function responseFromAssistant(
        Message $assistantMessage,
        Conversation $conversation,
        int $userId
    ): array {
        $metadata = is_array($assistantMessage->metadata ?? null) ? $assistantMessage->metadata : [];

        return [
            'success' => (bool) ($metadata['success'] ?? ! $assistantMessage->is_error),
            'type' => (string) ($metadata['type'] ?? ($assistantMessage->is_error ? 'error' : 'result')),
            'tool' => self::TOOL_KEY,
            'provider' => $metadata['provider'] ?? null,
            'model' => $metadata['model'] ?? $metadata['model_key'] ?? null,
            'user_id' => $userId,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => (string) $conversation->uuid,
            'transcript' => $metadata['transcript'] ?? null,
            'detected_language' => $metadata['detected_language'] ?? null,
            'duration_seconds' => $metadata['duration_seconds'] ?? null,
            'request_id' => $metadata['request_id'] ?? null,
            'metadata' => is_array($metadata['provider_metadata'] ?? null)
                ? $metadata['provider_metadata']
                : [],
            'message' => $metadata['message'] ?? $metadata['transcript'] ?? $assistantMessage->content,
            'state' => is_array($metadata['state'] ?? null) ? $metadata['state'] : self::DEFAULT_STATE,
            'assistant_message_id' => $assistantMessage->id,
        ];
    }

    private function normalizeState(array $state): array
    {
        $state = array_replace(self::DEFAULT_STATE, $state);
        $language = strtolower(trim((string) ($state['language'] ?? 'ar')));
        $state['provider'] = $this->scalarString($state['provider'] ?? null);
        $state['language'] = preg_match('/^[a-z]{2,3}(?:-[a-z]{2,4})?$/', $language) === 1
            ? $language
            : 'ar';
        $state['extra_options'] = array_values(array_filter(array_map(
            fn (mixed $option): string => trim((string) $option),
            is_array($state['extra_options'] ?? null) ? $state['extra_options'] : []
        )));
        $state['last_output'] = $this->scalarString($state['last_output'] ?? null);

        return $state;
    }

    private function sanitizedProviderMetadata(array $providerResponse): array
    {
        $metadata = $providerResponse['metadata'] ?? null;
        if (! is_array($metadata) || ! array_key_exists('provider_cost_usd', $metadata)) {
            return [];
        }

        $providerCost = $metadata['provider_cost_usd'];

        return is_scalar($providerCost) && ! is_bool($providerCost)
            ? ['provider_cost_usd' => $providerCost]
            : [];
    }

    private function resolveEndpoint(Conversation $conversation): string
    {
        $endpoint = $this->configuredValue($conversation, 'endpoint')
            ?? $this->scalarString($conversation->subTool?->endpoint);

        return $endpoint ?? self::ENDPOINT;
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

    private function clearCache(int $userId): void
    {
        try {
            Cache::tags(['conversations', "user_{$userId}"])->flush();
        } catch (Throwable $exception) {
            Log::debug('Speech-to-text tagged cache flush skipped.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
