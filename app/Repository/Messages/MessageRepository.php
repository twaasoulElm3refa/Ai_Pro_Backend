<?php

namespace App\Repository\Messages;

use App\Models\Message;
use Illuminate\Support\Facades\Log;

class MessageRepository implements MessageInterface
{
    public function send($data)
    {
        try {
            $message = Message::create($data);
            return $message;
        } catch (\Throwable $th) {
            Log::error($th);
            return null;
        }
    }
}
