<?php

namespace App\Http\Controllers\api\auth;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class UserProfileController extends Controller
{
    use ApiResponse;

    public function conversations()
    {
        try {
            $conversations = Conversation::with('subTool.translation','firstUserMessage.id,conversation_id,content')->where('user_id', auth()->id())->get();
            return $this->success($conversations,'Conversations Fetched Successfully');
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->error('Something went wrong while fetching conversations.');
        }
    }

    public function conversationDetails($uuid) {
        try {
            $conversation = Conversation::where('uuid', $uuid)->first();
            return $this->success($conversation,'Conversation Fetched Successfully');
        } catch (\Throwable $th) {
            Log::error($th);
            return $this->error('Something went wrong while fetching conversation.');
        }
    }
}
