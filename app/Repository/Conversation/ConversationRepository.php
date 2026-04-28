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
                    return Conversation::where('user_id', $userId)
                        ->latest()
                        ->get();
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
            return Cache::tags(['conversations'])
                ->remember("conversation_{$uuid}", now()->addMinutes(10), function () use ($uuid) {
                    return Conversation::with('message')->where('uuid', $uuid)->first();
                });
        } catch (\Throwable $th) {
            Log::error($th);
            return [];
        }
    }

    public function destroy($uuid)
    {
        try {
            $conversation = Conversation::where('uuid', $uuid)->first();
            if (!$conversation) {
                return false;
            }
            $userId = $conversation->user_id;
            $conversation->delete();
            $this->clearCache($userId);
            Cache::forget("conversation_{$uuid}");
            return true;
        } catch (\Throwable $th) {
            Log::error($th);
            return [];
        }
    }
}
