<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repository\Conversation\ConversationInterface;
use App\Repository\tools\SubToolInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConversationController extends Controller
{
    use ApiResponse;
    private $conversation;
    private $subToolRepository;
    public function __construct(ConversationInterface $conversation , SubToolInterface $subToolRepository)
    {
        $this->conversation = $conversation;
        $this->subToolRepository = $subToolRepository;
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
            $conversation = $this->conversation->show($uuid);
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
            $data = $request->all();
            $data['sub_tool_id'] = $subTool->id;
            $data['user_id'] = auth()->user()->id;
            $data['uuid'] = uniqid();
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
            $conversation = $this->conversation->destroy($uuid);
            return $this->success($conversation,'Conversation Deleted Successfully');
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->error('Something went wrong');
        }
    }

}
