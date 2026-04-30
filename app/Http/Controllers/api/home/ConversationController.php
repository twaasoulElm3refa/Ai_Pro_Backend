<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Repository\Conversation\ConversationInterface;
use App\Repository\tools\SubToolInterface;
use App\Services\ConversationMessageCacheService;
use App\Services\QdrantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

            if (! $conversation) {
                return $this->notFound('Conversation not found');
            }

            $messages = $this->messageCache->get($uuid);

            if ($messages === null) {
                $messages = $this->messageCache->remember($conversation);
            }

            $conversation->setRelation('message', collect($this->messageCache->toResponseMessages($messages)));

            return $this->success($conversation,'Conversation Fetched Successfully');
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->error('Something went wrong');
        }
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

}
