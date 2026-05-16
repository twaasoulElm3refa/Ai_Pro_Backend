<?php

namespace App\Repository\Conversation;

use App\Models\Conversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ConversationRepository implements ConversationInterface
{
    protected function clearCache($userId)
    {
        Cache::tags(['conversations', "user_{$userId}"])->flush();
    }

    public function index()
    {
        try {
            $userId = auth()->id();
            return Cache::tags(['conversations', "user_{$userId}"])
                ->remember("conversations_user_{$userId}", now()->addMinutes(10), function () use ($userId) {
                    return Conversation::with([
                        'firstUserMessage:id,conversation_id,content',
                    ])
                        ->where('user_id', $userId)
                        ->latest()
                        ->get()
                        ->map(function ($conversation) {
                            $conversation->setAttribute(
                                'first_user_message_content',
                                optional($conversation->firstUserMessage)->content
                            );

                            return $conversation->makeHidden('firstUserMessage');
                        });
                });
        } catch (\Throwable $th) {
            Log::error($th);
            return [];
        }
    }

    public function create($data)
    {
        try {
            $conversation = Conversation::create($data);
            $this->clearCache($conversation->user_id);
            return $conversation;
        } catch (\Throwable $th) {
            Log::error($th);
            return [];
        }
    }

    public function show($uuid)
    {
        try {
            $userId = auth()->id();

            return Cache::tags(['conversations', "user_{$userId}"])
                ->remember("conversation_{$userId}_{$uuid}", now()->addMinutes(10), function () use ($uuid, $userId) {
                    return Conversation::with('message')
                        ->where('uuid', $uuid)
                        ->where('user_id', $userId)
                        ->first();
                });
        } catch (\Throwable $th) {
            Log::error($th);
            return [];
        }
    }

    public function destroy($uuid)
    {
        try {
            $conversation = Conversation::where('uuid', $uuid)
                ->where('user_id', auth()->id())
                ->first();
            if (!$conversation) {
                return false;
            }
            $userId = $conversation->user_id;
            $conversation->delete();
            $this->clearCache($userId);
            Cache::forget("conversation_{$userId}_{$uuid}");
            return true;
        } catch (\Throwable $th) {
            Log::error($th);
            return [];
        }
    }
}
