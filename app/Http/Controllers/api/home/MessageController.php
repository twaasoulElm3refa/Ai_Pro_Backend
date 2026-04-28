<?php

namespace App\Http\Controllers\api\home;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\MessageRequest;
use App\Repository\Messages\MessageInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    use ApiResponse;

    private $message;

    public function __construct(MessageInterface $message)
    {
        $this->message = $message;
    }

    public function sendMessage(MessageRequest $request)
    {
        try {
            $data = $request->validated();
            $send = $this->message->send($data);
            $this->clearCache(auth()->user()->id);
            return $this->success($send, 'Message Sent Successfully.');
        } catch (\Throwable $th) {
            Log::error($th);

            return $this->error('Something went wrong.');
        }
    }

    protected function clearCache($userId)
    {
        Cache::tags(['conversations', "user_{$userId}"])->flush();
    }
}
