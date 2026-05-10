<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\CostLogger;
use App\Models\Message;
use App\Repository\Conversation\ConversationInterface;
use App\Repository\tools\SubToolInterface;
use App\Services\ConversationMessageCacheService;
use App\Services\QdrantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class ConversationController extends Controller
{
    use ApiResponse;
    private $conversation;
    private $subToolRepository;
    private ConversationMessageCacheService $messageCache;
    private QdrantService $qdrantService;

    public function __construct(
        ConversationInterface $conversation,
        SubToolInterface $subToolRepository,
        ConversationMessageCacheService $messageCache,
        QdrantService $qdrantService
    )
    {
        $this->conversation = $conversation;
        $this->subToolRepository = $subToolRepository;
        $this->messageCache = $messageCache;
        $this->qdrantService = $qdrantService;
    }

    public function conversation(Request $request)
    {
        try {
            $conversations = $this->conversation->index();
            return $this->success($conversations,'Conversations Fetched Successfully');
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->error('Something went wrong');
        }
    }

    public function conversationDetails($uuid)
    {
        try {
            $conversation = Conversation::where('uuid', $uuid)
                ->where('user_id', auth()->id())
                ->first();
            $limit  = CostLogger::where('conversation_id', $conversation->id)->latest()->first();
            if(!$limit){
                CostLogger::create([
                    'conversation_id' => $conversation->id,
                    'total_tokens' => 0
                ]);
            }
            if (! $conversation) {
                return $this->notFound('Conversation not found');
            }

            $messages = $this->messageCache->get($uuid);

            if ($messages === null) {
                $messages = $this->messageCache->remember($conversation);
            }

            $conversation->setRelation('message', collect($this->messageCache->toResponseMessages($messages)));
            if($limit->total_tokens >= 10000 ){
                return $this->success($conversation,'Limit Exceeded');
            }
            return $this->success($conversation,'Conversation Fetched Successfully');
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->error('Something went wrong');
        }
    }

    public function conversationStream(Request $request, $uuid)
    {
        $user = $request->user() ?? $this->userFromStreamToken($request);

        if (! $user) {
            abort(401, 'Unauthorized');
        }

        $rateKey = "conversation-stream:{$user->id}";
        if (RateLimiter::tooManyAttempts($rateKey, 30)) {
            abort(429, 'Too many stream requests.');
        }
        RateLimiter::hit($rateKey, 60);

        $conversation = Conversation::where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $afterId = (int) $request->query('after_id', 0);

        return response()->stream(function () use ($conversation, $afterId) {
            $this->sendSseEvent(['type' => 'typing']);

            $assistantMessage = $this->waitForAssistantMessage($conversation->id, $afterId);

            if (! $assistantMessage) {
                $this->sendSseEvent([
                    'type' => 'error',
                    'content' => 'Assistant response timed out. Please try again.',
                ]);
                $this->sendSseEvent(['type' => 'done']);

                return;
            }

            foreach ($this->chunkText($assistantMessage->content) as $chunk) {
                $this->sendSseEvent([
                    'type' => 'token',
                    'content' => $chunk,
                ]);
                usleep(25000);
            }

            $this->sendSseEvent([
                'type' => 'done',
                'message' => [
                    'id' => $assistantMessage->id,
                    'conversation_id' => $assistantMessage->conversation_id,
                    'role' => $assistantMessage->role,
                    'content' => $assistantMessage->content,
                    'is_error' => (bool) $assistantMessage->is_error,
                    'created_at' => optional($assistantMessage->created_at)->toISOString(),
                ],
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function createConversation(Request $request ,$slug)
    {
        try {
            $subTool = $this->subToolRepository->showBySlug($slug);

            if (! $subTool) {
                return $this->notFound('Tool not found');
            }

            $data = [
                'sub_tool_id' => $subTool->id,
                'user_id' => auth()->id(),
                'uuid' => (string) Str::uuid(),
            ];
            $conversation = $this->conversation->create($data);
            return $this->success($conversation,'Conversation Created Successfully');
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->error('Something went wrong');
        }
    }

    public function conversationDelete($uuid)
    {
        try {
            $conversationModel = Conversation::where('uuid', $uuid)
                ->where('user_id', auth()->id())
                ->first();

            $conversation = $this->conversation->destroy($uuid);

            if ($conversationModel && $conversation === true) {
                $this->messageCache->forget($uuid);
                $this->qdrantService->deleteCollection(
                    $this->qdrantService->collectionName((int) $conversationModel->id)
                );
            }

            return $this->success($conversation,'Conversation Deleted Successfully');
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->error('Something went wrong');
        }
    }

    protected function userFromStreamToken(Request $request)
    {
        $token = $request->query('token') ?: $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);

        return $accessToken?->tokenable;
    }

    protected function waitForAssistantMessage(int $conversationId, int $afterId): ?Message
    {
        $deadline = now()->addSeconds(75);

        while (now()->lessThan($deadline)) {
            $query = Message::where('conversation_id', $conversationId)
                ->where('role', 'assistant');

            if ($afterId > 0) {
                $query->where('reply_to_message_id', $afterId)->orderBy('id');
            } else {
                $query->orderBy('id', 'desc');
            }

            $message = $query->first();

            if ($message) {
                return $message;
            }

            $this->sendSseEvent(['type' => 'heartbeat']);
            usleep(500000);
        }

        return null;
    }

    protected function chunkText(string $text, int $size = 12): array
    {
        return preg_split('/(.{1,' . $size . '})/us', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
    }

    protected function sendSseEvent(array $payload): void
    {
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";

        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }

}
