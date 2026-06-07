<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\CostLogger;
use App\Models\Message;
use App\Models\User;
use App\Models\Wallet;
use App\Repository\Messages\MessageInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class EmailWriterService
{
    public const SUB_TOOL_ID = 6;
    public const TOOL_KEY = 'ai_email_writer';
    public const MODEL_KEY = 'email_writer';
    public const ENDPOINT = 'tasks/email-writer/chat';

    private const MODEL_PROMPT = <<<'PROMPT'
You are a professional AI Email Writer.

Infer the email purpose, type, recipient direction, language, tone, length, subject, and requested action from the user's message. Supported email types are: General Email, Business Email, Support Email, Request Email, Follow-up Email, Renewal Email, Confirmation Email, Acceptance Email, Rejection Email, Apology Email, Complaint Email, and Marketing Email. Supported tones are: Professional, Formal, Friendly, Persuasive, Apologetic, Appreciative, and Urgent.

When writing Arabic emails, use formal Modern Standard Arabic. For formal or business emails, use this ready-to-send structure:
1. A context-appropriate formal greeting.
2. A short polite opening.
3. A clear statement of purpose.
4. The requested action, when applicable.
5. A calm explanation of urgency, when applicable.
6. An offer to provide additional information or documents.
7. Polite thanks.
8. A formal closing.

Correctly distinguish the sender and recipient. An email from a user to a company or support team must address that company or team; it must not be written as though it came from them. Do not claim that the sender is CodeCanyon or any other company unless the user's message clearly says so.

Do not invent a sender name, company name, team name, facts, dates, or contact details. When sender_name is null, end after the formal closing without adding a signature name. When sender_name is present, place only that name after the closing.

If include_subject is true, include one clear subject line before the body. If include_subject is false, do not include a subject line. If include_subject is true and subject_line is null, infer a concise subject from the purpose.

Do not use colloquial Arabic, markdown, headings inside the email body, or emojis in formal emails unless the user explicitly asks for emojis. Keep Medium emails complete but concise, preserve blank lines between paragraphs, and return the final email in results[0].text.
PROMPT;

    public function __construct(
        private readonly AiArabicWriterService $writerService,
        private readonly MessageInterface $message,
        private readonly ConversationMessageCacheService $messageCache
    ) {
    }

    public function handle(
        Conversation $conversation,
        array $data,
        string $content,
        int $userId
    ): array {
        $requestState = $this->normalizeState($data['state'] ?? []);
        $latestState = $this->resolveLatestState($conversation);
        $inferredState = $this->inferStateFromContent(
            $content,
            $this->mergeState($latestState, $requestState)
        );
        $state = $this->mergeState(
            $this->mergeState($latestState, $inferredState),
            $requestState
        );
        if ($state['include_subject'] === false) {
            $state['subject_line'] = null;
        }

        Log::info('EmailWriter request received', [
            'user_id' => $userId,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => $content,
            'request_state' => $requestState,
            'state' => $state,
        ]);

        [$userMessage, $wasCreated] = $this->persistUserMessage(
            $conversation,
            $content,
            $state,
            $data['idempotency_key'] ?? null
        );

        $conversation->loadMissing('user.wallet', 'subTool');
        if (! $conversation->user) {
            throw new \RuntimeException('Conversation user not found.');
        }

        $userMessage->loadMissing('conversation.subTool');
        $this->messageCache->updateAfterMessage($userMessage);
        $this->clearCache($userId);

        $existingAssistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->where('reply_to_message_id', $userMessage->id)
            ->first();

        if ($existingAssistant) {
            return $this->buildResponseFromAssistant(
                $existingAssistant,
                $conversation,
                $userId
            ) + ['was_created' => false];
        }

        $payload = [
            'user_id' => $userId,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'conversation_id' => $conversation->id,
            'content' => $content,
            'user_message' => $content,
            'role' => 'user',
            'tool' => self::TOOL_KEY,
            'model_key' => self::MODEL_KEY,
            'state' => $state,
            'system_prompt' => self::MODEL_PROMPT,
            'debug' => (bool) ($data['debug'] ?? false),
        ];

        $providerError = null;

        try {
            $providerResponse = $this->writerService->generateReplyWithUsage(
                $payload,
                self::ENDPOINT
            );
        } catch (Throwable $th) {
            $providerError = $th;

            Log::error('EmailWriter failed', [
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString(),
            ]);

            $errorMessage = ((bool) ($data['debug'] ?? false) || app()->environment('local'))
                ? $th->getMessage()
                : 'حدث خطأ أثناء كتابة الإيميل.';

            $providerResponse = [
                'reply' => $errorMessage,
                'provider' => 'openrouter',
                'model_key' => self::MODEL_KEY,
                'raw' => [
                    'success' => false,
                    'type' => 'error',
                    'tool' => self::TOOL_KEY,
                    'provider' => 'openrouter',
                    'model_key' => self::MODEL_KEY,
                    'message' => $errorMessage,
                ],
            ];
        }

        $providerResponse = is_array($providerResponse)
            ? $providerResponse
            : ['reply' => (string) $providerResponse];
        $raw = is_array($providerResponse['raw'] ?? null) ? $providerResponse['raw'] : [];
        $providerSuccess = (bool) ($raw['success'] ?? ($raw['data']['success'] ?? true));
        $responseState = $this->normalizeState(
            $providerResponse['state'] ?? ($raw['state'] ?? ($raw['data']['state'] ?? []))
        );
        $mergedState = $this->mergeState($state, $responseState);
        $results = $this->normalizeResults(
            $providerResponse['results']
                ?? ($raw['results'] ?? ($raw['data']['results'] ?? []))
        );
        $type = strtolower(trim((string) (
            $providerResponse['type'] ?? ($raw['type'] ?? ($raw['data']['type'] ?? ''))
        )));

        if (! $providerSuccess) {
            $type = 'error';
        } elseif ($type === 'result' || count($results) > 0) {
            $type = 'result';
        } elseif ($type === 'question' || count($this->getMissingFields($mergedState)) > 0) {
            $type = 'question';
            $results = [];
        } else {
            $type = 'error';
        }

        if ($type === 'result') {
            $results = $this->normalizeEmailResults($results, $mergedState, $content);
            $mergedState['last_output'] = $this->buildOutput($results);
        }

        $responseMessage = trim((string) (
            $raw['message']
            ?? ($raw['data']['message'] ?? ($providerResponse['reply'] ?? ''))
        ));

        if ($responseMessage === '') {
            $responseMessage = match ($type) {
                'question' => 'من فضلك أكمل البيانات المطلوبة لكتابة الإيميل.',
                'result' => 'تمت كتابة الإيميل بنجاح.',
                default => 'حدث خطأ أثناء كتابة الإيميل.',
            };
        }

        $rawUsage = $raw['usage'] ?? ($providerResponse['usage'] ?? null);
        $rawCost = $raw['cost'] ?? ($providerResponse['cost'] ?? null);
        $usage = $this->normalizeUsage($rawUsage);
        $cost = $this->normalizeCost($rawCost);
        $usageMetadata = is_array($rawUsage) && count($rawUsage) > 0 ? $usage : null;
        $costMetadata = is_array($rawCost) && count($rawCost) > 0 ? $cost : null;
        $provider = $this->toNullableString(
            $providerResponse['provider'] ?? ($raw['provider'] ?? ($raw['data']['provider'] ?? null))
        ) ?? 'openrouter';
        $requestId = $this->toNullableString(
            $providerResponse['request_id'] ?? ($raw['request_id'] ?? ($raw['data']['request_id'] ?? null))
        );
        $count = $type === 'result' ? count($results) : 0;
        $assistantContent = $type === 'result' ? $this->buildOutput($results) : $responseMessage;
        $tokensToDeduct = $type === 'result' ? (int) $usage['total_tokens'] : 0;
        $walletSnapshot = ['balance' => null, 'payback_balance' => null];
        $assistantMessage = null;

        DB::transaction(function () use (
            $conversation,
            $userMessage,
            $userId,
            $type,
            $assistantContent,
            $provider,
            $requestId,
            $mergedState,
            $results,
            $responseMessage,
            $count,
            $usage,
            $cost,
            $usageMetadata,
            $costMetadata,
            $tokensToDeduct,
            $providerError,
            &$walletSnapshot,
            &$assistantMessage
        ): void {
            if ($type === 'result') {
                $walletSnapshot = $this->deductWalletTokens(
                    $conversation->user,
                    $tokensToDeduct
                );
            } else {
                $wallet = Wallet::where('user_id', $conversation->user_id)
                    ->lockForUpdate()
                    ->first();
                $walletSnapshot = [
                    'balance' => $wallet ? (int) $wallet->balance : null,
                    'payback_balance' => $wallet ? (int) ($wallet->payback_balance ?? 0) : null,
                ];
            }

            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $assistantContent,
                'is_error' => $type === 'error',
                'reply_to_message_id' => $userMessage->id,
                'metadata' => [
                    'type' => $type,
                    'tool' => self::TOOL_KEY,
                    'provider' => $provider,
                    'model_key' => self::MODEL_KEY,
                    'request_id' => $requestId,
                    'state' => $mergedState,
                    'results' => $results,
                    'message' => $responseMessage,
                    'count' => $count,
                    'usage' => $usageMetadata,
                    'cost' => $costMetadata,
                    'tokens_deducted' => $tokensToDeduct,
                    'debug_error' => $providerError?->getMessage(),
                    'sub_tool_id' => self::SUB_TOOL_ID,
                    'conversation_uuid' => $conversation->uuid,
                ],
            ]);

            if ($type === 'result') {
                CostLogger::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                    'sub_tool_id' => self::SUB_TOOL_ID,
                    'input_tokens' => $usage['input_tokens'],
                    'output_tokens' => $usage['output_tokens'],
                    'total_tokens' => $usage['total_tokens'],
                    'input_cost' => $cost['input_cost'],
                    'output_cost' => $cost['output_cost'],
                    'web_search_cost' => $cost['web_search_cost'],
                    'total_cost' => $cost['total_cost'],
                    'currency' => $cost['currency'],
                    'provider_request_id' => $requestId,
                    'model_key' => self::MODEL_KEY,
                ]);
            }
        });

        $assistantMessage->setRelation('conversation', $conversation);
        $this->messageCache->updateAfterMessage($assistantMessage);
        $this->clearCache($userId);

        return [
            'success' => $type !== 'error',
            'type' => $type,
            'tool' => self::TOOL_KEY,
            'provider' => $provider,
            'model_key' => self::MODEL_KEY,
            'user_id' => $userId,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'message' => $responseMessage,
            'state' => $mergedState,
            'results' => $results,
            'count' => $count,
            'request_id' => $requestId,
            'debug' => (bool) ($data['debug'] ?? false) && $providerError
                ? ['error' => $providerError->getMessage()]
                : null,
            'usage' => $usageMetadata,
            'cost' => $costMetadata,
            'tokens_deducted' => $tokensToDeduct,
            'wallet' => $walletSnapshot,
            'assistant_message_id' => $assistantMessage->id,
            'was_created' => $wasCreated,
        ];
    }

    public function normalizeState(mixed $state): array
    {
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            $state = is_array($decoded) ? $decoded : [];
        }

        $state = is_array($state) ? $state : [];
        $merged = array_merge([
            'purpose' => null,
            'email_type' => null,
            'recipient' => null,
            'sender_name' => null,
            'language' => null,
            'tone' => null,
            'length' => null,
            'subject_line' => null,
            'call_to_action' => null,
            'include_subject' => null,
            'extra_options' => [],
            'last_output' => null,
        ], $state);

        foreach ([
            'purpose',
            'email_type',
            'recipient',
            'sender_name',
            'language',
            'tone',
            'length',
            'subject_line',
            'call_to_action',
            'last_output',
        ] as $key) {
            $merged[$key] = $this->toNullableString($merged[$key] ?? null);
        }

        $includeSubject = $merged['include_subject'] ?? null;
        if (is_bool($includeSubject)) {
            $merged['include_subject'] = $includeSubject;
        } elseif (in_array($includeSubject, [1, '1', 'true'], true)) {
            $merged['include_subject'] = true;
        } elseif (in_array($includeSubject, [0, '0', 'false'], true)) {
            $merged['include_subject'] = false;
        } else {
            $merged['include_subject'] = null;
        }

        $extraOptions = is_array($merged['extra_options'] ?? null)
            ? $merged['extra_options']
            : [];
        $merged['extra_options'] = collect($extraOptions)
            ->map(fn ($item) => $this->toNullableString($item))
            ->filter()
            ->values()
            ->all();

        return $merged;
    }

    private function inferStateFromContent(string $content, array $currentState): array
    {
        $text = mb_strtolower(trim($content));
        $isArabic = preg_match('/\p{Arabic}/u', $content) === 1;
        $isDomainRenewal = $this->containsAny($text, [
            'تجديد الدومين',
            'تجديد النطاق',
            'الدومين',
            'domain renewal',
            'renew the domain',
        ]) && $this->containsAny($text, ['تجديد', 'renew']);
        $includeSubject = ! $this->containsAny($text, [
            'بدون عنوان',
            'من غير عنوان',
            'بدون subject',
            'without subject',
            'no subject',
        ]);

        $emailType = match (true) {
            $isDomainRenewal || $this->containsAny($text, ['تجديد', 'renewal']) => 'Renewal Email',
            $this->containsAny($text, ['متابعة', 'follow up', 'follow-up']) => 'Follow-up Email',
            $this->containsAny($text, ['اعتذار', 'آسف', 'apology', 'sorry']) => 'Apology Email',
            $this->containsAny($text, ['شكوى', 'complaint']) => 'Complaint Email',
            $this->containsAny($text, ['رفض', 'rejection', 'rejected']) => 'Rejection Email',
            $this->containsAny($text, ['قبول', 'تم قبول', 'acceptance', 'accepted']) => 'Acceptance Email',
            $this->containsAny($text, ['تأكيد', 'confirmation', 'confirm']) => 'Confirmation Email',
            $this->containsAny($text, ['الدعم', 'support']) => 'Support Email',
            $this->containsAny($text, ['طلب', 'request']) => 'Request Email',
            $this->containsAny($text, ['رسمي', 'إداري', 'شركة', 'business']) => 'Business Email',
            default => 'General Email',
        };

        $recipient = match (true) {
            $this->containsAny($text, ['فريق الدعم', 'الدعم الفني', 'support team']) => 'Support Team',
            $this->containsAny($text, ['الشركة', 'شركة']) => 'Company',
            default => 'General Recipient',
        };

        $purpose = trim($content);
        $subject = null;
        $callToAction = null;

        if ($isDomainRenewal) {
            $purpose = $isArabic
                ? 'تجديد نطاق الدومين الخاص بالشركة قبل موعد انتهائه لتجنب انقطاع الخدمة'
                : 'Renew the company domain before it expires to avoid service interruption';
            $subject = $isArabic
                ? 'طلب تجديد نطاق الدومين'
                : 'Domain Renewal Request';
            $callToAction = $isArabic
                ? 'تأكيد استلام الطلب وإتمام إجراءات التجديد في أقرب وقت ممكن'
                : 'Confirm receipt and complete the renewal as soon as possible';
        } elseif (
            $this->containsAny($text, ['تم قبول طلبك', 'قبول طلبك', 'request was accepted', 'request has been accepted'])
        ) {
            $purpose = $isArabic
                ? 'إبلاغ المستلم بقبول طلبه'.($this->containsAny($text, ['code canyon', 'codecanyon']) ? ' على موقع CodeCanyon' : '')
                : 'Notify the recipient that their request was accepted';
            $subject = $isArabic
                ? 'إشعار بقبول طلبك'.($this->containsAny($text, ['code canyon', 'codecanyon']) ? ' على موقع CodeCanyon' : '')
                : 'Your Request Has Been Accepted';
        }

        $formal = $this->containsAny($text, [
            'رسمي',
            'إداري',
            'formal',
            'business',
        ]) || in_array($emailType, [
            'Business Email',
            'Support Email',
            'Request Email',
            'Follow-up Email',
            'Renewal Email',
            'Complaint Email',
        ], true);

        return $this->normalizeState([
            'purpose' => $purpose,
            'email_type' => $emailType,
            'recipient' => $recipient,
            'sender_name' => $currentState['sender_name'] ?? null,
            'language' => $isArabic ? 'Arabic' : ($currentState['language'] ?? 'English'),
            'tone' => $formal ? 'Formal' : 'Professional',
            'length' => $currentState['length'] ?? 'Medium',
            'subject_line' => $includeSubject ? $subject : null,
            'call_to_action' => $callToAction,
            'include_subject' => $includeSubject,
            'extra_options' => array_values(array_unique(array_filter([
                'Clear structure',
                'Ready to send',
                $isArabic && $formal ? 'Formal Arabic' : null,
            ]))),
            'last_output' => $currentState['last_output'] ?? null,
        ]);
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function normalizeEmailResults(array $results, array $state, string $userMessage): array
    {
        $state = $this->normalizeState($state);
        $includeSubject = $state['include_subject'] !== false;
        $removeEmojis = in_array($state['tone'], ['Formal', 'Professional'], true)
            && ! $this->containsAny(mb_strtolower($userMessage), ['إيموجي', 'ايموجي', 'emoji']);

        return collect($results)
            ->map(function (array $result) use ($state, $includeSubject, $removeEmojis): array {
                $text = $this->sanitizeEmailText((string) ($result['text'] ?? ''), $removeEmojis);
                $subject = $this->toNullableString($result['subject'] ?? null)
                    ?? $this->extractSubjectFromText($text)
                    ?? $state['subject_line'];

                if (! $includeSubject) {
                    $text = $this->removeSubjectFromText($text);
                    $subject = null;
                } elseif ($subject !== null && ! $this->hasSubjectInText($text)) {
                    $text = "Subject: {$subject}\n\n".$text;
                }

                $result['text'] = trim($text);
                $result['subject'] = $subject;

                return $result;
            })
            ->filter(fn (array $result): bool => $result['text'] !== '')
            ->values()
            ->all();
    }

    private function sanitizeEmailText(string $text, bool $removeEmojis): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $text = preg_replace('/```(?:[a-z0-9_-]+)?\s*/iu', '', $text) ?? $text;
        $text = preg_replace('/^\s{0,3}#{1,6}\s+/mu', '', $text) ?? $text;
        $text = preg_replace('/^\s*[-*+]\s+/mu', '', $text) ?? $text;
        $text = preg_replace('/\[(.*?)\]\((?:.*?)\)/u', '$1', $text) ?? $text;
        $text = str_replace(['**', '__', '`'], '', $text);

        if ($removeEmojis) {
            $text = preg_replace(
                '/[\x{1F1E6}-\x{1F1FF}\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u',
                '',
                $text
            ) ?? $text;
        }

        return trim(preg_replace("/[ \t]+\n/u", "\n", $text) ?? $text);
    }

    private function extractSubjectFromText(string $text): ?string
    {
        if (preg_match('/^\s*(?:Subject|الموضوع)\s*:\s*(.+)$/imu', $text, $matches) !== 1) {
            return null;
        }

        return $this->toNullableString($matches[1] ?? null);
    }

    private function hasSubjectInText(string $text): bool
    {
        return preg_match('/^\s*(?:Subject|الموضوع)\s*:/imu', $text) === 1;
    }

    private function removeSubjectFromText(string $text): string
    {
        return trim(
            preg_replace('/^\s*(?:Subject|الموضوع)\s*:[^\n]*(?:\n+)?/iu', '', $text, 1)
            ?? $text
        );
    }

    private function persistUserMessage(
        Conversation $conversation,
        string $content,
        array $state,
        mixed $requestedIdempotencyKey
    ): array {
        $idempotencyKey = $this->toNullableString($requestedIdempotencyKey) ?? (string) Str::uuid();
        $lock = Cache::lock(
            "message-send:{$conversation->user_id}:{$conversation->id}:{$idempotencyKey}",
            15
        );

        return $lock->block(5, function () use (
            $conversation,
            $content,
            $state,
            $idempotencyKey
        ): array {
            return DB::transaction(function () use (
                $conversation,
                $content,
                $state,
                $idempotencyKey
            ): array {
                $existing = Message::where('conversation_id', $conversation->id)
                    ->where('role', 'user')
                    ->where(function ($query) use ($idempotencyKey, $content): void {
                        $query->where('idempotency_key', $idempotencyKey)
                            ->orWhere(function ($duplicate) use ($content): void {
                                $duplicate->where('content', $content)
                                    ->where('created_at', '>=', now()->subSeconds(20));
                            });
                    })
                    ->orderByDesc('id')
                    ->first();

                if ($existing) {
                    return [$existing, false];
                }

                $userMessage = $this->message->send([
                    'conversation_id' => $conversation->id,
                    'role' => 'user',
                    'content' => $content,
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => [
                        'type' => 'user_input',
                        'tool' => self::TOOL_KEY,
                        'model_key' => self::MODEL_KEY,
                        'sub_tool_id' => self::SUB_TOOL_ID,
                        'conversation_uuid' => $conversation->uuid,
                        'state' => $state,
                    ],
                ]);

                if (! $userMessage instanceof Message) {
                    throw new \RuntimeException('Message could not be saved.');
                }

                return [$userMessage, true];
            }, 3);
        });
    }

    private function resolveLatestState(Conversation $conversation): array
    {
        $message = Message::where('conversation_id', $conversation->id)
            ->whereIn('role', ['user', 'assistant'])
            ->whereNotNull('metadata')
            ->orderByDesc('id')
            ->get()
            ->first(function (Message $message): bool {
                $metadata = is_array($message->metadata ?? null) ? $message->metadata : [];

                return (
                    strtolower((string) ($metadata['tool'] ?? '')) === self::TOOL_KEY
                    || (int) ($metadata['sub_tool_id'] ?? 0) === self::SUB_TOOL_ID
                ) && is_array($metadata['state'] ?? null);
            });

        $metadata = $message && is_array($message->metadata ?? null)
            ? $message->metadata
            : [];

        return $this->normalizeState($metadata['state'] ?? []);
    }

    private function mergeState(array $oldState, array $newState): array
    {
        $merged = $this->normalizeState($oldState);
        $incoming = $this->normalizeState($newState);

        foreach ($incoming as $key => $value) {
            if ($key === 'extra_options') {
                if (count($value) > 0) {
                    $merged[$key] = $value;
                }
                continue;
            }

            if ($value !== null && $value !== '') {
                $merged[$key] = $value;
            }
        }

        return $this->normalizeState($merged);
    }

    private function getMissingFields(array $state): array
    {
        $state = $this->normalizeState($state);
        $required = [
            'purpose',
            'email_type',
            'recipient',
            'language',
            'tone',
            'length',
            'include_subject',
        ];

        return collect($required)
            ->filter(fn (string $key): bool => $state[$key] === null || $state[$key] === '')
            ->values()
            ->all();
    }

    private function normalizeResults(mixed $results): array
    {
        if (! is_array($results)) {
            return [];
        }

        return collect($results)
            ->map(function ($result, int $index): array {
                $row = is_array($result) ? $result : [];

                return [
                    'id' => isset($row['id']) && is_numeric($row['id'])
                        ? (int) $row['id']
                        : $index + 1,
                    'text' => trim((string) ($row['text'] ?? (is_scalar($result) ? $result : ''))),
                    'title' => $this->toNullableString($row['title'] ?? null),
                    'subject' => $this->toNullableString($row['subject'] ?? null),
                    'meta' => is_array($row['meta'] ?? null) ? $row['meta'] : [],
                ];
            })
            ->filter(fn (array $result): bool => $result['text'] !== '')
            ->values()
            ->all();
    }

    private function buildOutput(array $results): string
    {
        return collect($results)
            ->map(fn (array $result): string => trim((string) ($result['text'] ?? '')))
            ->filter()
            ->implode("\n\n");
    }

    private function buildResponseFromAssistant(
        Message $assistantMessage,
        Conversation $conversation,
        int $userId
    ): array {
        $metadata = is_array($assistantMessage->metadata ?? null) ? $assistantMessage->metadata : [];
        $results = $this->normalizeResults($metadata['results'] ?? []);
        $type = (string) ($metadata['type'] ?? ($assistantMessage->is_error ? 'error' : 'result'));
        $wallet = Wallet::where('user_id', $userId)->first();

        return [
            'success' => $type !== 'error',
            'type' => $type,
            'tool' => self::TOOL_KEY,
            'provider' => $this->toNullableString($metadata['provider'] ?? null) ?? 'openrouter',
            'model_key' => self::MODEL_KEY,
            'user_id' => $userId,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'message' => $this->toNullableString($metadata['message'] ?? null)
                ?? trim((string) $assistantMessage->content),
            'state' => $this->normalizeState($metadata['state'] ?? []),
            'results' => $results,
            'count' => (int) ($metadata['count'] ?? count($results)),
            'request_id' => $this->toNullableString($metadata['request_id'] ?? null),
            'debug' => null,
            'usage' => is_array($metadata['usage'] ?? null)
                ? $this->normalizeUsage($metadata['usage'])
                : null,
            'cost' => is_array($metadata['cost'] ?? null)
                ? $this->normalizeCost($metadata['cost'])
                : null,
            'tokens_deducted' => (int) ($metadata['tokens_deducted'] ?? 0),
            'wallet' => [
                'balance' => $wallet ? (int) $wallet->balance : null,
                'payback_balance' => $wallet ? (int) ($wallet->payback_balance ?? 0) : null,
            ],
            'assistant_message_id' => $assistantMessage->id,
        ];
    }

    private function normalizeUsage(mixed $usage): array
    {
        $usage = is_array($usage) ? $usage : [];
        $input = is_numeric($usage['input_tokens'] ?? null) ? (int) $usage['input_tokens'] : 0;
        $output = is_numeric($usage['output_tokens'] ?? null) ? (int) $usage['output_tokens'] : 0;

        return [
            'input_tokens' => $input,
            'output_tokens' => $output,
            'total_tokens' => is_numeric($usage['total_tokens'] ?? null)
                ? (int) $usage['total_tokens']
                : $input + $output,
        ];
    }

    private function normalizeCost(mixed $cost): array
    {
        $cost = is_array($cost) ? $cost : [];
        $input = is_numeric($cost['input_cost'] ?? null) ? (float) $cost['input_cost'] : 0.0;
        $output = is_numeric($cost['output_cost'] ?? null) ? (float) $cost['output_cost'] : 0.0;
        $search = is_numeric($cost['web_search_cost'] ?? null)
            ? (float) $cost['web_search_cost']
            : 0.0;

        return [
            'input_cost' => $input,
            'output_cost' => $output,
            'web_search_cost' => $search,
            'total_cost' => is_numeric($cost['total_cost'] ?? null)
                ? (float) $cost['total_cost']
                : $input + $output + $search,
            'currency' => strtoupper($this->toNullableString($cost['currency'] ?? 'USD') ?? 'USD'),
        ];
    }

    private function deductWalletTokens(User $user, int $tokens): array
    {
        $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

        if (! $wallet) {
            $wallet = Wallet::create([
                'user_id' => $user->id,
                'uuid' => (string) Str::uuid(),
                'balance' => 0,
                'ip_address' => request()->ip(),
            ]);
        }

        $balance = (int) $wallet->balance;
        $payback = (int) ($wallet->payback_balance ?? 0);

        if ($tokens > 0) {
            if ($balance >= $tokens) {
                $wallet->balance = $balance - $tokens;
            } else {
                $wallet->balance = 0;
                $wallet->payback_balance = $payback + ($tokens - $balance);
            }
            $wallet->save();
            $wallet->refresh();
        }

        return [
            'balance' => (int) $wallet->balance,
            'payback_balance' => (int) ($wallet->payback_balance ?? 0),
        ];
    }

    private function toNullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function clearCache(int $userId): void
    {
        try {
            Cache::tags(['conversations', "user_{$userId}"])->flush();
        } catch (Throwable $th) {
            Log::debug('Conversation tagged cache flush skipped.', [
                'error' => $th->getMessage(),
            ]);
        }
    }
}
