<?php

namespace App\Repository\Conversation;

use App\Models\Conversation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ConversationRepository implements ConversationInterface
{
    protected const CHAT4_SUB_TOOL_IDS = [17, 18, 19, 20];

    protected function clearCache($userId)
    {
        Cache::tags(['conversations', "user_{$userId}"])->flush();
    }

    protected function conversationTitleFromMessage(?string $content): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $content)) ?? '');

        if ($clean === '') {
            return '';
        }

        $words = preg_split('/\s+/u', $clean, 5, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_slice($words, 0, 4));
    }

    public function index()
    {
        try {
            $userId = auth()->id();
            return Cache::tags(['conversations', "user_{$userId}"])
                ->remember("conversations_user_{$userId}", now()->addMinutes(10), function () use ($userId) {
                    return Conversation::with([
                        'firstUserMessage' => function ($query) {
                            $query->select([
                                'messages.id',
                                'messages.conversation_id',
                                'messages.content',
                            ])->where('messages.role', 'user');
                        },
                    ])
                        ->where('user_id', $userId)
                        ->latest()
                        ->get()
                        ->map(function ($conversation) {
                            $firstUserMessageContent = optional($conversation->firstUserMessage)->content;
                            $conversation->setAttribute(
                                'first_user_message_content',
                                $firstUserMessageContent
                            );

                            if (in_array((int) $conversation->sub_tool_id, self::CHAT4_SUB_TOOL_IDS, true)) {
                                $firstUserTitle = $this->conversationTitleFromMessage($firstUserMessageContent);

                                if ($firstUserTitle !== '') {
                                    $conversation->setAttribute('title', $firstUserTitle);
                                }
                            }

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
