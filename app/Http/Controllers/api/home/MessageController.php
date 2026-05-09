<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\MessageRequest;
use App\Jobs\GenerateAssistantReplyJob;
use App\Models\Message;
use App\Repository\Messages\MessageInterface;
use App\Services\ConversationMessageCacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class MessageController extends Controller
{
    use ApiResponse;

    private MessageInterface $message;
    private ConversationMessageCacheService $messageCache;

    public function __construct(
        MessageInterface $message,
        ConversationMessageCacheService $messageCache
    ) {
        $this->message = $message;
        $this->messageCache = $messageCache;
    }

    public function sendMessage(MessageRequest $request)
    {
        try {
            Log::debug('Message send request received.', [
                'user_id' => auth()->id(),
                'input' => $request->all(),
            ]);

            $data = $request->validated();
            Log::debug('Message send request validated.', [
                'user_id' => auth()->id(),
                'validated' => $data,
            ]);

            $userId = (int) auth()->id();
            if ($userId <= 0) {
                return $this->unauthorized('Unauthorized.');
            }
            $data['conversation_id'] = (int) ($data['conversation_id'] ?? 0);
            $data['role'] = 'user';
            $data['content'] = trim((string) ($data['content'] ?? ''));
            $data['is_error'] = false;
            $taskOptions = $this->normalizeTaskOptions($data['task_options'] ?? null);

            if ($data['conversation_id'] <= 0 || $data['content'] === '') {
                Log::warning('Message send rejected due to invalid normalized payload.', [
                    'user_id' => $userId,
                    'payload' => $data,
                ]);

                return $this->validationError([
                    'content' => ['Message content is required.'],
                    'conversation_id' => ['Conversation id is required.'],
                ], 'Invalid message data.');
            }
            /*
             * مهم:
             * لو الفرونت مبعتش idempotency_key لأي سبب، بنولده هنا.
             * لكن الأفضل الفرونت يبعته عشان يمنع Retry duplication.
             */
            $data['idempotency_key'] = trim((string) ($data['idempotency_key'] ?? ''));
            if ($data['idempotency_key'] === '') {
                $data['idempotency_key'] = (string) Str::uuid();
            }
            /*
             * احذف أي مفاتيح ممكن الفرونت يبعتها وتبوظ التخزين
             */
            unset(
                $data['id'],
                $data['created_at'],
                $data['updated_at'],
                $data['deleted_at'],
                $data['reply_to_message_id'],
                $data['task_options']
            );
            $lockKey = $this->requestLockKey(
                $userId,
                $data['conversation_id'],
                $data['idempotency_key']
            );
            $lock = Cache::lock($lockKey, 15);
            $processed = $lock->block(5, function () use ($data) {
                return DB::transaction(function () use ($data) {
                    /*
                     * 1) منع تكرار نفس الرسالة بنفس idempotency_key
                     */
                    $existingByKey = Message::where('conversation_id', $data['conversation_id'])
                        ->where('role', 'user')
                        ->where('idempotency_key', $data['idempotency_key'])
                        ->first();

                    if ($existingByKey) {
                        return [$existingByKey, false];
                    }

                    /*
                     * 2) حماية إضافية:
                     * لو حصل request تاني قديم أو endpoint تاني بيبعت نفس المحتوى بدون key
                     * خلال آخر 20 ثانية، رجّع الرسالة الموجودة بدل ما تعمل create جديد.
                     */
                    $existingRecentDuplicate = Message::where('conversation_id', $data['conversation_id'])
                        ->where('role', 'user')
                        ->where('content', $data['content'])
                        ->where('created_at', '>=', now()->subSeconds(20))
                        ->orderByDesc('id')
                        ->first();

                    if ($existingRecentDuplicate) {
                        if (empty($existingRecentDuplicate->idempotency_key)) {
                            $existingRecentDuplicate->idempotency_key = $data['idempotency_key'];
                            $existingRecentDuplicate->save();
                        }

                        return [$existingRecentDuplicate, false];
                    }

                    $userMessage = $this->message->send($data);

                    if (! $userMessage || ! isset($userMessage->id)) {
                        return [null, false];
                    }

                    return [$userMessage, true];
                }, 3);
            });

            /** @var Message|null $userMessage */
            $userMessage = $processed[0] ?? null;
            $wasCreated = (bool) ($processed[1] ?? false);

            if (! $userMessage || ! isset($userMessage->id)) {
                return $this->error('Message could not be saved.');
            }

            if ($wasCreated) {
                $userMessage->loadMissing('conversation');

                $this->messageCache->updateAfterMessage($userMessage);

                $this->clearCache($userId);

                Log::debug('Dispatching assistant reply generation.', [
                    'user_id' => $userId,
                    'conversation_id' => $userMessage->conversation_id,
                    'message_id' => $userMessage->id,
                    'task_options' => $taskOptions,
                ]);
                $this->dispatchAssistantReplyIfNeeded($userMessage, $taskOptions);
            }

            Log::debug('Message send succeeded.', [
                'user_id' => $userId,
                'conversation_id' => $userMessage->conversation_id,
                'message_id' => $userMessage->id,
                'was_created' => $wasCreated,
            ]);

            return $this->success([
                'message_id' => $userMessage->id,
                'conversation_id' => $userMessage->conversation_id,
                'message' => $userMessage,
                'was_created' => $wasCreated,
            ], 'Message Sent Successfully.');
        } catch (Throwable $th) {
            Log::error('Send message failed.', [
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString(),
                'request_input' => $request->all(),
                'user_id' => auth()->id(),
            ]);

            if (app()->environment('local')) {
                return response()->json([
                    'success' => false,
                    'error' => $th->getMessage(),
                    'trace' => $this->shortTrace($th),
                ], 500);
            }

            return $this->error('Sorry, I could not generate a response right now.');
        }
    }

    protected function clearCache($userId): void
    {
        try {
            Cache::tags(['conversations', "user_{$userId}"])->flush();
        } catch (\Throwable $th) {
            Log::debug('Conversation tagged cache flush skipped.', [
                'error' => $th->getMessage(),
            ]);
        }
    }

    protected function dispatchAssistantReplyIfNeeded(Message $userMessage, ?array $taskOptions = null): void
    {
        $assistantExists = Message::where('role', 'assistant')
            ->where('reply_to_message_id', $userMessage->id)
            ->exists();

        if ($assistantExists) {
            return;
        }

        $dispatchMarker = GenerateAssistantReplyJob::dispatchMarkerKey($userMessage->id);

        if (! Cache::add($dispatchMarker, true, now()->addMinutes(5))) {
            return;
        }

        GenerateAssistantReplyJob::dispatch($userMessage->id, $taskOptions)->afterResponse();
    }

    protected function requestLockKey(int $userId, int $conversationId, string $idempotencyKey): string
    {
        return "message-send:{$userId}:{$conversationId}:{$idempotencyKey}";
    }

    protected function shortTrace(Throwable $th, int $limit = 5): array
    {
        $trace = collect($th->getTrace())
            ->take($limit)
            ->map(function (array $frame): array {
                return [
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                    'function' => $frame['function'] ?? null,
                ];
            })
            ->values()
            ->all();

        array_unshift($trace, [
            'file' => $th->getFile(),
            'line' => $th->getLine(),
            'function' => 'throw',
        ]);

        return $trace;
    }

    protected function normalizeTaskOptions(mixed $taskOptions): ?array
    {
        if (! is_array($taskOptions)) {
            return null;
        }

        $searchMode = (string) ($taskOptions['search_mode'] ?? '');
        if ($searchMode !== 'on') {
            return null;
        }

        return [
            'search_mode' => 'on',
            'web_search_max_results' => isset($taskOptions['web_search_max_results'])
                ? (int) $taskOptions['web_search_max_results']
                : 3,
            'web_search_total_results' => isset($taskOptions['web_search_total_results'])
                ? (int) $taskOptions['web_search_total_results']
                : 5,
            'max_tokens' => isset($taskOptions['max_tokens'])
                ? (int) $taskOptions['max_tokens']
                : 1000,
            'temperature' => isset($taskOptions['temperature'])
                ? (float) $taskOptions['temperature']
                : 0.45,
        ];
    }
}
