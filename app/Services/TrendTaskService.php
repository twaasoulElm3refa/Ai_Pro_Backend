<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\GeneratedImage;
use App\Models\Message;
use App\Models\SubTools;
use App\Models\Wallet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class TrendTaskService
{
    public function __construct(
        private readonly ConversationMessageCacheService $messageCache,
        private readonly ProviderCostBillingService $billingService,
        private readonly GeneratedImageService $generatedImageService
    ) {}

    public function handle(
        string $trendSlug,
        array $data,
        UploadedFile $uploadedFile,
        int $userId
    ): array {
        $trend = $this->trendConfig($trendSlug);
        $conversation = Conversation::query()
            ->with('subTool')
            ->where('uuid', (string) $data['conversation_uuid'])
            ->where('user_id', $userId)
            ->first();

        if (! $conversation) {
            abort(404, 'Conversation not found.');
        }

        $subTool = $conversation->subTool;
        if (
            ! $subTool
            || (int) $subTool->id !== (int) $trend['sub_tool_id']
            || (string) $subTool->slug !== $trendSlug
            || (int) $subTool->main_tool_id !== (int) config('trends.main_tool_id', 7)
            || ! (bool) $subTool->is_active
        ) {
            abort(422, 'This conversation is not configured for the selected Trends tool.');
        }

        $selectedModelId = $this->selectedModelId($subTool, $trend);
        if (
            isset($data['selected_model_id'])
            && (int) $data['selected_model_id'] !== $selectedModelId
        ) {
            abort(422, 'The selected model is not available for this Trends tool.');
        }

        $this->ensureWalletCanStart($userId);

        $idempotencyKey = (string) $data['idempotency_key'];
        $lock = Cache::lock(
            "trend:{$trendSlug}:{$userId}:{$conversation->id}:{$idempotencyKey}",
            900
        );

        return $lock->block(30, fn (): array => $this->handleLocked(
            $conversation,
            $trendSlug,
            $trend,
            $selectedModelId,
            $data,
            $uploadedFile,
            $userId,
            $idempotencyKey
        ));
    }

    private function handleLocked(
        Conversation $conversation,
        string $trendSlug,
        array $trend,
        int $selectedModelId,
        array $data,
        UploadedFile $uploadedFile,
        int $userId,
        string $idempotencyKey
    ): array {
        $prompt = trim((string) $data['user_message']);
        $state = is_array($data['state'] ?? null) ? $data['state'] : [];
        $state['parameters'] = is_array($state['parameters'] ?? null) ? $state['parameters'] : [];

        $userMessage = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', 'user')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($userMessage) {
            $existingAssistant = Message::query()
                ->where('reply_to_message_id', $userMessage->id)
                ->where('role', 'assistant')
                ->first();

            if ($existingAssistant) {
                if ($existingAssistant->is_error) {
                    throw new RuntimeException('A previous request with this key failed. Please retry.');
                }

                return $this->responseFromAssistant($existingAssistant, $conversation, $userId);
            }
        }

        if (! $userMessage) {
            $userMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'user',
                'content' => $prompt,
                'idempotency_key' => $idempotencyKey,
                'is_error' => false,
                'metadata' => [
                    'type' => 'trend_image_request',
                    'tool' => $trend['tool_key'],
                    'trend' => $trendSlug,
                    'sub_tool_id' => (int) $conversation->sub_tool_id,
                    'selected_model_id' => $selectedModelId,
                    'conversation_uuid' => (string) $conversation->uuid,
                    'source_filename' => basename($uploadedFile->getClientOriginalName()),
                    'state' => $state,
                ],
            ]);

            $userMessage->setRelation('conversation', $conversation);
            $this->messageCache->updateAfterMessage($userMessage);
        }

        $localFile = null;

        try {
            $providerResult = $this->requestProvider(
                $uploadedFile,
                $conversation,
                $trend,
                $selectedModelId,
                $prompt,
                $state,
                $userId
            );
            $generation = $this->validateProviderResult($providerResult, $selectedModelId);
            $localFile = $this->generatedImageService->downloadGeneratedFile(
                $generation['file'],
                $userId,
                (string) $conversation->uuid
            );

            $assistantMessage = $this->persistResult(
                $conversation,
                $userMessage,
                $trendSlug,
                $trend,
                $selectedModelId,
                $prompt,
                $state,
                $generation,
                $localFile,
                $userId
            );

            return $this->responseFromAssistant($assistantMessage, $conversation, $userId);
        } catch (Throwable $exception) {
            if (is_array($localFile) && ! empty($localFile['path'])) {
                Storage::disk((string) $localFile['disk'])->delete($localFile['path']);
            }

            Log::warning('Trend image generation failed.', [
                'trend' => $trendSlug,
                'user_id' => $userId,
                'conversation_id' => $conversation->id,
                'sub_tool_id' => $conversation->sub_tool_id,
                'idempotency_key' => $idempotencyKey,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            Message::updateOrCreate(
                ['reply_to_message_id' => $userMessage->id],
                [
                    'conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => 'The trend image could not be generated. Please try again.',
                    'is_error' => true,
                    'metadata' => [
                        'success' => false,
                        'type' => 'error',
                        'tool' => $trend['tool_key'],
                        'trend' => $trendSlug,
                        'sub_tool_id' => (int) $conversation->sub_tool_id,
                        'conversation_uuid' => (string) $conversation->uuid,
                        'files' => [],
                        'images' => [],
                        'count' => 0,
                    ],
                ]
            );

            $this->messageCache->forget((string) $conversation->uuid);

            throw $exception;
        }
    }

    private function trendConfig(string $trendSlug): array
    {
        $trend = config("trends.tools.{$trendSlug}");

        if (
            ! is_array($trend)
            || empty($trend['sub_tool_id'])
            || empty($trend['endpoint'])
            || empty($trend['selected_model_id'])
            || empty($trend['tool_key'])
        ) {
            abort(404, 'Trends tool not found.');
        }

        return $trend;
    }

    private function selectedModelId(SubTools $subTool, array $trend): int
    {
        $configuredModelId = (int) $trend['selected_model_id'];
        $allowedModelIds = $subTool->allowed_model_ids;

        if (is_string($allowedModelIds)) {
            $allowedModelIds = json_decode($allowedModelIds, true);
        }

        if (! is_array($allowedModelIds) || $allowedModelIds === []) {
            return $configuredModelId;
        }

        $allowedModelIds = array_values(array_filter(array_map('intval', $allowedModelIds)));
        if (! in_array($configuredModelId, $allowedModelIds, true)) {
            abort(422, 'The Trends tool model configuration is invalid.');
        }

        return $configuredModelId;
    }

    private function ensureWalletCanStart(int $userId): void
    {
        $canStart = DB::transaction(function () use ($userId): bool {
            $wallet = Wallet::query()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            return $wallet !== null
                && (bool) $wallet->is_active
                && (int) $wallet->balance > 0
                && (int) ($wallet->payback_balance ?? 0) === 0;
        });

        if (! $canStart) {
            abort(402, 'Insufficient points. Please recharge your wallet to continue.');
        }
    }

    private function requestProvider(
        UploadedFile $uploadedFile,
        Conversation $conversation,
        array $trend,
        int $selectedModelId,
        string $prompt,
        array $state,
        int $userId
    ): array {
        $baseUrl = rtrim((string) config('services.ai.base_url'), '/');
        $apiKey = trim((string) (config('services.ai.internal_api_key') ?: config('services.aiarabic.internal_api_key')));

        if ($baseUrl === '' || $apiKey === '') {
            throw new RuntimeException('The Trends provider is not configured.');
        }

        $handle = @fopen($uploadedFile->getRealPath(), 'r');
        if ($handle === false) {
            throw new RuntimeException('The uploaded image could not be opened.');
        }

        $payload = [
            'user_id' => $userId,
            'sub_tool_id' => (int) $conversation->sub_tool_id,
            'selected_model_id' => $selectedModelId,
            'conversation_uuid' => (string) $conversation->uuid,
            'user_message' => $prompt,
            'state' => $state,
            'debug' => (bool) config('services.ai.trends_debug', false),
        ];

        try {
            $response = Http::withHeaders(['x-internal-api-key' => $apiKey])
                ->attach(
                    'file',
                    $handle,
                    basename($uploadedFile->getClientOriginalName()),
                    ['Content-Type' => $uploadedFile->getMimeType() ?: 'application/octet-stream']
                )
                ->connectTimeout(10)
                ->timeout(180)
                ->post($baseUrl.'/'.ltrim((string) $trend['endpoint'], '/'), [
                    'payload' => json_encode(
                        $payload,
                        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    ),
                ]);
        } finally {
            fclose($handle);
        }

        $result = $response->json();
        if (! $response->successful() || ! is_array($result)) {
            Log::warning('Trends provider rejected the request.', [
                'endpoint' => $trend['endpoint'],
                'status' => $response->status(),
                'conversation_id' => $conversation->id,
            ]);

            throw new RuntimeException('The image provider did not complete the request.');
        }

        return $result;
    }

    private function validateProviderResult(array $result, int $selectedModelId): array
    {
        $payload = is_array($result['data'] ?? null) ? $result['data'] : $result;
        $success = $payload['success'] ?? $result['success'] ?? true;
        $status = strtolower(trim((string) ($payload['status'] ?? $result['status'] ?? '')));

        if ($success === false || in_array($status, ['error', 'failed', 'cancelled'], true)) {
            throw new RuntimeException('The image provider reported a failed generation.');
        }

        if ((int) ($payload['selected_model_id'] ?? $result['selected_model_id'] ?? 0) !== $selectedModelId) {
            throw new RuntimeException('The image provider returned an unexpected model result.');
        }

        if (strtolower((string) ($payload['operation'] ?? $result['operation'] ?? '')) !== 'image_edit') {
            throw new RuntimeException('The image provider returned an unexpected operation.');
        }

        $provider = trim((string) ($payload['provider'] ?? $result['provider'] ?? ''));
        $model = trim((string) ($payload['model'] ?? $result['model'] ?? ''));
        $providerResponse = $payload['provider_response']
            ?? data_get($payload, 'metadata.provider_response')
            ?? $result['provider_response']
            ?? data_get($result, 'metadata.provider_response');
        $files = is_array($payload['files'] ?? null)
            ? $payload['files']
            : (is_array($result['files'] ?? null) ? $result['files'] : []);

        if ($provider === '' || $model === '' || ! is_array($providerResponse)) {
            throw new RuntimeException('The image provider returned incomplete generation metadata.');
        }

        $cost = $providerResponse['cost'] ?? null;
        if (
            is_bool($cost)
            || ! is_scalar($cost)
            || ! is_numeric(trim((string) $cost))
            || (float) $cost < 0
        ) {
            throw new RuntimeException('The image provider returned an invalid generation cost.');
        }

        $providerRequestId = trim((string) (
            $providerResponse['taskUUID']
            ?? $providerResponse['task_uuid']
            ?? $providerResponse['imageUUID']
            ?? ''
        ));
        if ($providerRequestId === '') {
            throw new RuntimeException('The image provider did not return a task identifier.');
        }

        $file = $files[0] ?? null;
        if (! is_array($file) || trim((string) ($file['download_url'] ?? '')) === '') {
            throw new RuntimeException('The image provider did not return a generated file.');
        }

        return [
            'provider' => $provider,
            'model' => $model,
            'operation' => 'image_edit',
            'selected_model_id' => $selectedModelId,
            'provider_request_id' => $providerRequestId,
            'provider_response' => $providerResponse,
            'file' => $file,
            'message' => trim((string) ($payload['message'] ?? $result['message'] ?? '')),
        ];
    }

    private function persistResult(
        Conversation $conversation,
        Message $userMessage,
        string $trendSlug,
        array $trend,
        int $selectedModelId,
        string $prompt,
        array $state,
        array $generation,
        array $localFile,
        int $userId
    ): Message {
        $content = $generation['message'] ?: (string) $trend['result_message'];
        $publicFile = $this->publicFileData($localFile);

        $assistantMessage = DB::transaction(function () use (
            $conversation,
            $userMessage,
            $trendSlug,
            $trend,
            $selectedModelId,
            $prompt,
            $state,
            $generation,
            $localFile,
            $publicFile,
            $content,
            $userId
        ): Message {
            $billing = $this->billingService->chargeTrendResponse(
                $userId,
                (int) $conversation->id,
                (int) $conversation->sub_tool_id,
                $generation['provider_response'],
                $generation['provider_request_id'],
                $generation['model']
            );

            $message = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $content,
                'is_error' => false,
                'reply_to_message_id' => $userMessage->id,
                'metadata' => [
                    'success' => true,
                    'type' => 'result',
                    'tool' => $trend['tool_key'],
                    'trend' => $trendSlug,
                    'provider' => $generation['provider'],
                    'model' => $generation['model'],
                    'model_key' => $generation['model'],
                    'operation' => $generation['operation'],
                    'selected_model_id' => $selectedModelId,
                    'request_id' => $generation['provider_request_id'],
                    'user_id' => $userId,
                    'sub_tool_id' => (int) $conversation->sub_tool_id,
                    'conversation_uuid' => (string) $conversation->uuid,
                    'message' => $content,
                    'request_prompt' => $prompt,
                    'state' => $state,
                    'files' => [$publicFile],
                    'images' => [$publicFile],
                    'count' => 1,
                    'generation' => ['provider_response' => $generation['provider_response']],
                    'cost' => [
                        'total_cost' => (string) $generation['provider_response']['cost'],
                        'currency' => 'USD',
                    ],
                    'points_deducted' => $billing['points_deducted'],
                    'billing' => $billing,
                ],
            ]);

            GeneratedImage::create([
                'public_id' => $localFile['public_id'],
                'user_id' => $userId,
                'conversation_id' => $conversation->id,
                'message_id' => $message->id,
                'sub_tool_id' => (int) $conversation->sub_tool_id,
                'source_file_id' => $localFile['source_file_id'],
                'filename' => $localFile['filename'],
                'path' => $localFile['path'],
                'disk' => $localFile['disk'],
                'content_type' => $localFile['content_type'],
                'size_bytes' => $localFile['size_bytes'],
                'metadata' => [
                    'request_id' => $generation['provider_request_id'],
                    'operation' => $generation['operation'],
                    'selected_model_id' => $selectedModelId,
                    'trend' => $trendSlug,
                ],
            ]);

            return $message;
        });

        $assistantMessage->setRelation('conversation', $conversation);
        $this->messageCache->updateAfterMessage($assistantMessage);
        Cache::tags(['conversations', "user_{$userId}"])->flush();

        return $assistantMessage;
    }

    private function publicFileData(array $file): array
    {
        $image = new GeneratedImage($file);
        $image->public_id = $file['public_id'];

        return [
            'id' => $file['public_id'],
            'filename' => $file['filename'],
            'content_type' => $file['content_type'],
            'size_bytes' => $file['size_bytes'],
            'preview_url' => route('generated-images.preview', ['image' => $image]),
            'download_url' => route('generated-images.download', ['image' => $image]),
        ];
    }

    private function responseFromAssistant(
        Message $assistantMessage,
        Conversation $conversation,
        int $userId
    ): array {
        $metadata = is_array($assistantMessage->metadata) ? $assistantMessage->metadata : [];
        $files = is_array($metadata['files'] ?? null) ? $metadata['files'] : [];

        return [
            'success' => (bool) ($metadata['success'] ?? ! $assistantMessage->is_error),
            'type' => $metadata['type'] ?? ($assistantMessage->is_error ? 'error' : 'result'),
            'tool' => $metadata['tool'] ?? null,
            'trend' => $metadata['trend'] ?? null,
            'operation' => $metadata['operation'] ?? null,
            'selected_model_id' => $metadata['selected_model_id'] ?? null,
            'user_id' => $userId,
            'sub_tool_id' => (int) $conversation->sub_tool_id,
            'conversation_uuid' => (string) $conversation->uuid,
            'message' => $metadata['message'] ?? $assistantMessage->content,
            'assistant_message_id' => $assistantMessage->id,
            'files' => $files,
            'images' => $files,
            'count' => count($files),
            'cost' => $metadata['cost'] ?? null,
            'billing' => $metadata['billing'] ?? null,
            'state' => $metadata['state'] ?? null,
        ];
    }
}
