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
    ) {
        $this->conversation = $conversation;
        $this->subToolRepository = $subToolRepository;
        $this->messageCache = $messageCache;
        $this->qdrantService = $qdrantService;
    }

    public function conversation(Request $request)
    {
        try {
            $conversations = $this->conversation->index();

            return $this->success($conversations, 'Conversations Fetched Successfully');
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

            if (! $conversation) {
                return $this->notFound('Conversation not found');
            }

            $usageSummary = [
                'total_tokens' => (int) CostLogger::where('conversation_id', $conversation->id)->latest()->first()?->total_tokens ?? 0,
                'total_cost' => (float) CostLogger::where('conversation_id', $conversation->id)->sum('total_cost'),
            ];

            $messages = $this->messageCache->get($uuid);

            if ($messages === null) {
                $messages = $this->messageCache->remember($conversation);
            }

            $conversation->setRelation('message', collect($this->messageCache->toResponseMessages($messages)));
            $conversation->setAttribute('usage_summary', $usageSummary);

            if ($usageSummary['total_tokens'] >= 20000) {
                return $this->success($conversation, 'Limit Exceeded');
            }

            return $this->success($conversation, 'Conversation Fetched Successfully');
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

            $latestCost = CostLogger::where('conversation_id', $conversation->id)
                ->latest('id')
                ->first();

            $usage = $latestCost ? [
                'input_tokens' => (int) ($latestCost->input_tokens ?? 0),
                'output_tokens' => (int) ($latestCost->output_tokens ?? 0),
                'total_tokens' => (int) ($latestCost->total_tokens ?? 0),
            ] : null;

            $cost = $latestCost ? [
                'input_cost' => (float) ($latestCost->input_cost ?? 0),
                'output_cost' => (float) ($latestCost->output_cost ?? 0),
                'web_search_cost' => (float) ($latestCost->web_search_cost ?? 0),
                'total_cost' => (float) ($latestCost->total_cost ?? 0),
                'currency' => (string) ($latestCost->currency ?? 'USD'),
            ] : null;

            $pointsCharged = null;
            if ($latestCost) {
                $pointsCharged = (float) ($latestCost->total_cost ?? 0) > 0
                    ? (int) ceil(((float) $latestCost->total_cost) * 100)
                    : (int) ceil(((int) $latestCost->input_tokens * 0.000125) + ((int) $latestCost->output_tokens * 0.001));
            }

            $walletBalance = optional($conversation->user()->with('wallet')->first()?->wallet)->balance;
            $assistantMetadata = is_array($assistantMessage->metadata ?? null)
                ? $assistantMessage->metadata
                : [];
            $assistantResponse = $this->assistantApiResponse(
                $assistantMessage,
                $assistantMetadata,
                $usage,
                $cost
            );

            $donePayload = [
                'type' => 'done',
                'message' => [
                    'id' => $assistantMessage->id,
                    'conversation_id' => $assistantMessage->conversation_id,
                    'role' => $assistantMessage->role,
                    'content' => $assistantMessage->content,
                    'is_error' => (bool) $assistantMessage->is_error,
                    'created_at' => optional($assistantMessage->created_at)->toISOString(),
                    'metadata' => $assistantMetadata !== [] ? $assistantMetadata : null,
                ],
                'response' => $assistantResponse,
                'usage' => $usage,
                'cost' => $cost,
                'wallet' => [
                    'points_charged' => $pointsCharged,
                    'balance' => $walletBalance !== null ? (int) $walletBalance : null,
                ],
            ];

            if (
                (int) ($assistantResponse['sub_tool_id'] ?? 0) === 10
                || ($assistantResponse['tool'] ?? null) === 'ai_prompt_enhancer'
            ) {
                Log::info('PROMPT ENHANCER SSE DONE PAYLOAD', [
                    'payload' => $donePayload,
                    'response' => $donePayload['response'] ?? null,
                    'results' => $donePayload['response']['results'] ?? null,
                    'state' => $donePayload['response']['state'] ?? null,
                ]);
            }

            if (
                (int) ($assistantResponse['sub_tool_id'] ?? 0) === 11
                || ($assistantResponse['tool'] ?? null) === 'ai_idea_generator'
            ) {
                Log::info('IDEA GENERATOR SSE DONE PAYLOAD', [
                    'payload' => $donePayload,
                    'response' => $donePayload['response'] ?? null,
                    'results' => $donePayload['response']['results'] ?? null,
                    'state' => $donePayload['response']['state'] ?? null,
                ]);
            }

            $this->sendSseEvent($donePayload);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function createConversation(Request $request, $slug)
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
            CostLogger::create([
                'conversation_id' => $conversation->id,
                'total_tokens' => 0,
                'sub_tool_id' => $subTool->id,
                'user_id' => auth()->id(),
                'input_tokens' => 0,
                'output_tokens' => 0,
                'input_cost' => 0,
                'output_cost' => 0,
                'web_search_cost' => 0,
                'total_cost' => 0,
                'currency' => 'USD',
            ]);

            return $this->success($conversation, 'Conversation Created Successfully');
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

            return $this->success($conversation, 'Conversation Deleted Successfully');
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

    protected function assistantApiResponse(
        Message $message,
        array $metadata,
        ?array $usage,
        ?array $cost
    ): array {
        $results = is_array($metadata['results'] ?? null) ? $metadata['results'] : [];
        $state = is_array($metadata['state'] ?? null) ? $metadata['state'] : null;

        return [
            'success' => (bool) ($metadata['success'] ?? ! $message->is_error),
            'type' => (string) ($metadata['type'] ?? ($message->is_error ? 'error' : 'result')),
            'tool' => $metadata['tool'] ?? null,
            'provider' => $metadata['provider'] ?? null,
            'model_key' => $metadata['model_key'] ?? null,
            'request_id' => $metadata['request_id'] ?? null,
            'user_id' => $metadata['user_id'] ?? $message->conversation?->user_id,
            'sub_tool_id' => $metadata['sub_tool_id'] ?? $message->conversation?->sub_tool_id,
            'conversation_uuid' => $metadata['conversation_uuid'] ?? $message->conversation?->uuid,
            'message' => $metadata['message'] ?? null,
            'state' => $state,
            'results' => $results,
            'count' => (int) ($metadata['count'] ?? count($results)),
            'usage' => is_array($metadata['usage'] ?? null) ? $metadata['usage'] : $usage,
            'cost' => is_array($metadata['cost'] ?? null) ? $metadata['cost'] : $cost,
            'debug' => $metadata['debug'] ?? null,
        ];
    }

    protected function chunkText(string $text, int $size = 12): array
    {
        return preg_split('/(.{1,'.$size.'})/us', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
    }

    protected function sendSseEvent(array $payload): void
    {
        echo 'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE)."\n\n";

        if (ob_get_level() > 0) {
            @ob_flush();
        }

        flush();
    }
}
