<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\GeneratedImage;
use App\Models\Message;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BackgroundRemoverService
{
    public const SUB_TOOL_ID = 22;

    public const TOOL_KEY = 'ai_background_remover';

    private const ENDPOINT = 'tasks/background-remover';

    private const ALLOWED_DOWNLOAD_PATH = '#^/tasks/generated-files/download/[A-Za-z0-9-]+$#';

    private const ALLOWED_MIME_TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    private const MAX_GENERATED_BYTES = 25 * 1024 * 1024;

    public function __construct(
        private readonly ConversationMessageCacheService $messageCache
    ) {}

    public static function supports(int $subToolId, ?string $toolKey = null, ?string $modelKey = null): bool
    {
        return $subToolId === self::SUB_TOOL_ID
            || strtolower(trim((string) $toolKey)) === self::TOOL_KEY
            || strtolower(trim((string) $modelKey)) === 'background_remover';
    }

    public function handle(
        Conversation $conversation,
        array $data,
        ?UploadedFile $uploadedFile,
        int $userId
    ): array {
        if ((int) $conversation->user_id !== $userId) {
            abort(403, 'المحادثة لا تخص المستخدم الحالي.');
        }

        if ((int) $conversation->sub_tool_id !== self::SUB_TOOL_ID) {
            abort(422, 'هذه المحادثة ليست مخصصة لإزالة الخلفية.');
        }

        if (! $uploadedFile) {
            abort(422, 'يرجى رفع صورة أولًا.');
        }

        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? '')) ?: (string) Str::uuid();
        $lock = Cache::lock("background-remover:{$userId}:{$conversation->id}:{$idempotencyKey}", 360);

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
        $prompt = trim((string) ($data['user_message'] ?? ''))
            ?: 'Remove the background from the uploaded image and return a transparent PNG.';
        $state = is_array($data['state'] ?? null) ? $data['state'] : [];

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
                return $this->responseFromAssistant($existingAssistant, $conversation, $userId);
            }
        }

        $userMessage ??= Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $uploadedFile->getClientOriginalName(),
            'idempotency_key' => $idempotencyKey,
            'is_error' => false,
            'metadata' => [
                'type' => 'background_remover_request',
                'tool' => self::TOOL_KEY,
                'sub_tool_id' => self::SUB_TOOL_ID,
                'conversation_uuid' => (string) $conversation->uuid,
                'request_prompt' => $prompt,
                'state' => $state,
            ],
        ]);

        $localFiles = [];

        try {
            $aiResult = $this->requestAi($uploadedFile, $conversation, $prompt, $state, $userId);
            $remoteFiles = is_array($aiResult['files'] ?? null)
                ? $aiResult['files']
                : (is_array(data_get($aiResult, 'data.files')) ? data_get($aiResult, 'data.files') : []);

            if ($remoteFiles === []) {
                throw new RuntimeException('لم تُرجع خدمة الذكاء الاصطناعي ملفًا ناتجًا.');
            }

            $localFiles = $this->downloadFiles(
                $remoteFiles,
                $userId,
                (string) $conversation->uuid
            );

            if ($localFiles === []) {
                throw new RuntimeException('تعذر حفظ الصورة الناتجة محليًا.');
            }

            $assistantMessage = $this->persistResult(
                $conversation,
                $userMessage,
                $prompt,
                $state,
                $aiResult,
                $localFiles,
                $userId
            );

            return $this->responseFromAssistant($assistantMessage, $conversation, $userId);
        } catch (Throwable $exception) {
            foreach ($localFiles as $localFile) {
                if (is_array($localFile) && ! empty($localFile['path'])) {
                    Storage::disk((string) ($localFile['disk'] ?? 'public'))->delete($localFile['path']);
                }
            }

            Log::warning('Background remover failed.', [
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
                    'content' => 'تعذر إزالة خلفية الصورة حاليًا. يرجى المحاولة مرة أخرى.',
                    'is_error' => true,
                    'metadata' => [
                        'success' => false,
                        'type' => 'error',
                        'tool' => self::TOOL_KEY,
                        'sub_tool_id' => self::SUB_TOOL_ID,
                        'conversation_uuid' => (string) $conversation->uuid,
                        'message' => 'تعذر إزالة خلفية الصورة حاليًا. يرجى المحاولة مرة أخرى.',
                        'state' => $state,
                        'request_prompt' => $prompt,
                        'images' => [],
                        'files' => [],
                        'count' => 0,
                    ],
                ]
            );

            return $this->responseFromAssistant($assistantMessage, $conversation, $userId);
        }
    }

    private function requestAi(
        UploadedFile $uploadedFile,
        Conversation $conversation,
        string $prompt,
        array $state,
        int $userId
    ): array {
        $baseUrl = rtrim((string) config('services.ai.base_url'), '/');
        $apiKey = trim((string) (config('services.ai.internal_api_key') ?: config('services.aiarabic.internal_api_key')));

        if ($baseUrl === '' || $apiKey === '') {
            throw new RuntimeException('خدمة إزالة الخلفية غير مهيأة.');
        }

        $handle = @fopen($uploadedFile->getRealPath(), 'r');
        if ($handle === false) {
            throw new RuntimeException('تعذر فتح الصورة المرفوعة.');
        }

        try {
            $response = Http::withHeaders([
                'x-internal-api-key' => $apiKey,
            ])
                ->attach(
                    'file',
                    $handle,
                    $uploadedFile->getClientOriginalName(),
                    ['Content-Type' => $uploadedFile->getMimeType() ?: 'application/octet-stream']
                )
                ->timeout(180)
                ->retry(2, 500, null, false)
                ->post($baseUrl.'/'.self::ENDPOINT, [
                    'user_id' => $userId,
                    'sub_tool_id' => self::SUB_TOOL_ID,
                    'conversation_uuid' => (string) $conversation->uuid,
                    'user_message' => $prompt,
                    'state' => json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
        } finally {
            fclose($handle);
        }

        $result = $response->json();
        if (! $response->successful() || ! is_array($result)) {
            throw new RuntimeException('خدمة إزالة الخلفية لم تُكمل الطلب.');
        }

        Log::debug('Background remover AI request completed.', [
            'status' => $response->status(),
            'response_keys' => array_keys($result),
        ]);

        return $result;
    }

    private function downloadFiles(array $remoteFiles, int $userId, string $conversationUuid): array
    {
        $localFiles = [];

        foreach (array_slice($remoteFiles, 0, 4) as $remoteFile) {
            if (! is_array($remoteFile)) {
                continue;
            }

            try {
                $localFiles[] = $this->downloadFile($remoteFile, $userId, $conversationUuid);
            } catch (Throwable $exception) {
                Log::warning('Background remover file download failed.', [
                    'user_id' => $userId,
                    'conversation_uuid' => $conversationUuid,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $localFiles;
    }

    private function downloadFile(array $remoteFile, int $userId, string $conversationUuid): array
    {
        $downloadUrl = trim((string) ($remoteFile['download_url'] ?? ''));
        $targetUrl = $this->trustedDownloadUrl($downloadUrl);
        $apiKey = trim((string) (config('services.ai.internal_api_key') ?: config('services.aiarabic.internal_api_key')));

        $response = Http::withHeaders(['x-internal-api-key' => $apiKey])
            ->timeout(180)
            ->retry(2, 500, null, false)
            ->get($targetUrl);

        if (! $response->successful()) {
            throw new RuntimeException('تعذر تنزيل الصورة الناتجة.');
        }

        $body = $response->body();
        if ($body === '' || strlen($body) > self::MAX_GENERATED_BYTES) {
            throw new RuntimeException('حجم الصورة الناتجة غير صالح.');
        }

        $contentType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $detectedType = strtolower((string) (new \finfo(FILEINFO_MIME_TYPE))->buffer($body));
        if (! isset(self::ALLOWED_MIME_TYPES[$contentType]) || ! isset(self::ALLOWED_MIME_TYPES[$detectedType])) {
            throw new RuntimeException('نوع الصورة الناتجة غير مدعوم.');
        }

        $extension = self::ALLOWED_MIME_TYPES[$detectedType];
        $publicId = (string) Str::uuid();
        $storagePath = "background-remover/{$userId}/{$conversationUuid}/{$publicId}.{$extension}";

        if (! Storage::disk('public')->put($storagePath, $body)) {
            throw new RuntimeException('تعذر حفظ الصورة الناتجة.');
        }

        $remoteFilename = basename((string) ($remoteFile['filename'] ?? ''));
        $baseName = pathinfo($remoteFilename, PATHINFO_FILENAME);
        $displayFilename = Str::slug($baseName ?: 'background-removed').'-no-background.'.$extension;

        return [
            'public_id' => $publicId,
            'source_file_id' => is_scalar($remoteFile['file_id'] ?? null) ? (string) $remoteFile['file_id'] : null,
            'filename' => $displayFilename,
            'content_type' => $detectedType,
            'size_bytes' => strlen($body),
            'path' => $storagePath,
            'disk' => 'public',
        ];
    }

    private function trustedDownloadUrl(string $downloadUrl): string
    {
        if ($downloadUrl === '' || ! str_starts_with($downloadUrl, '/')) {
            throw new RuntimeException('رابط الصورة الناتجة غير صالح.');
        }

        if (! preg_match(self::ALLOWED_DOWNLOAD_PATH, $downloadUrl)) {
            throw new RuntimeException('رابط الصورة الناتجة غير مسموح.');
        }

        return rtrim((string) config('services.ai.base_url'), '/').$downloadUrl;
    }

    private function persistResult(
        Conversation $conversation,
        Message $userMessage,
        string $prompt,
        array $state,
        array $aiResult,
        array $localFiles,
        int $userId
    ): Message {
        $provider = $aiResult['provider'] ?? data_get($aiResult, 'data.provider');
        $model = $aiResult['model'] ?? data_get($aiResult, 'data.model');
        $requestId = $aiResult['request_id'] ?? data_get($aiResult, 'data.request_id');
        $cost = $aiResult['cost'] ?? data_get($aiResult, 'metadata.provider_cost_usd');
        $metadata = is_array($aiResult['metadata'] ?? null) ? $aiResult['metadata'] : [];
        $content = trim((string) ($aiResult['message'] ?? data_get($aiResult, 'data.message') ?? ''))
            ?: 'تمت إزالة الخلفية بنجاح.';
        $publicFiles = collect($localFiles)->map(fn (array $file): array => $this->publicFileData($file))->values()->all();

        $assistantMessage = DB::transaction(function () use (
            $conversation,
            $userMessage,
            $prompt,
            $state,
            $publicFiles,
            $localFiles,
            $content,
            $provider,
            $model,
            $requestId,
            $cost,
            $metadata,
            $userId
        ): Message {
            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $content,
                'is_error' => false,
                'reply_to_message_id' => $userMessage->id,
                'metadata' => [
                    'success' => true,
                    'type' => 'result',
                    'tool' => self::TOOL_KEY,
                    'provider' => $provider,
                    'model' => $model,
                    'model_key' => $model,
                    'request_id' => $requestId,
                    'user_id' => $userId,
                    'sub_tool_id' => self::SUB_TOOL_ID,
                    'conversation_uuid' => (string) $conversation->uuid,
                    'message' => $content,
                    'state' => $state,
                    'request_prompt' => $prompt,
                    'files' => $publicFiles,
                    'images' => $publicFiles,
                    'count' => count($publicFiles),
                    'generation' => $metadata,
                    'cost' => $cost,
                ],
            ]);

            foreach ($localFiles as $file) {
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
                        'sub_tool_id' => self::SUB_TOOL_ID,
                    ],
                ]);
            }

            return $assistantMessage;
        });

        $assistantMessage->setRelation('conversation', $conversation);
        $this->messageCache->updateAfterMessage($assistantMessage);
        Cache::tags(['conversations', "user_{$userId}"])->flush();

        return $assistantMessage;
    }

    private function publicFileData(array $file): array
    {
        $generatedFile = new GeneratedImage($file);
        $generatedFile->public_id = $file['public_id'];

        return [
            'id' => $file['public_id'],
            'filename' => $file['filename'],
            'content_type' => $file['content_type'],
            'size_bytes' => $file['size_bytes'],
            'preview_url' => route('background-remover.files.preview', ['file' => $generatedFile]),
            'download_url' => route('background-remover.files.download', ['file' => $generatedFile]),
        ];
    }

    private function responseFromAssistant(Message $assistantMessage, Conversation $conversation, int $userId): array
    {
        $metadata = is_array($assistantMessage->metadata) ? $assistantMessage->metadata : [];
        $files = is_array($metadata['files'] ?? null) ? $metadata['files'] : [];

        return [
            'success' => (bool) ($metadata['success'] ?? ! $assistantMessage->is_error),
            'type' => $metadata['type'] ?? ($assistantMessage->is_error ? 'error' : 'result'),
            'tool' => self::TOOL_KEY,
            'provider' => $metadata['provider'] ?? null,
            'model' => $metadata['model'] ?? null,
            'model_key' => $metadata['model_key'] ?? null,
            'user_id' => $userId,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => (string) $conversation->uuid,
            'message' => $metadata['message'] ?? $assistantMessage->content,
            'assistant_message_id' => $assistantMessage->id,
            'files' => $files,
            'images' => $files,
            'count' => count($files),
            'metadata' => $metadata['generation'] ?? [],
            'request_id' => $metadata['request_id'] ?? null,
            'cost' => $metadata['cost'] ?? null,
            'state' => $metadata['state'] ?? null,
        ];
    }
}
