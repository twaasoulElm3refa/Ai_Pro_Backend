<?php

namespace App\Http\Controllers\api\home;

use App\Exceptions\AiServiceException;
use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\MessageRequest;
use App\Jobs\GenerateAssistantReplyJob;
use App\Models\Conversation;
use App\Models\CostLogger;
use App\Models\Message;
use App\Models\User;
use App\Models\Wallet;
use App\Repository\Messages\MessageInterface;
use App\Services\AiArabicWriterService;
use App\Services\ChatSeoToolService;
use App\Services\ConversationMessageCacheService;
use App\Services\EmailWriterService;
use App\Services\ProductDescriptionGeneratorService;
use App\Services\ResumeBuilderService;
use App\Services\ScriptGeneratorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class MessageController extends Controller
{
    use ApiResponse;

    private const TEXT_SUMMARIZER_SUB_TOOL_ID = 2;
    private const TEXT_SUMMARIZER_TOOL_KEY = 'ai_text_summarizer';
    private const TEXT_SUMMARIZER_TASK_KEY = 'summarizer';
    private const PARAPHRASER_SUB_TOOL_ID = 3;
    private const PARAPHRASER_TOOL_KEY = 'ai_paraphraser';
    private const HEADLINE_GENERATOR_SUB_TOOL_ID = 4;
    private const HEADLINE_ENDPOINT = 'tasks/headline-generator/chat';
    private const SOCIAL_POST_GENERATOR_SUB_TOOL_ID = 5;
    private const SOCIAL_POST_GENERATOR_TOOL_KEY = 'ai_social_post_generator';
    private const SOCIAL_POST_GENERATOR_MODEL_KEY = 'social_post_generator';
    private const SOCIAL_POST_GENERATOR_ENDPOINT = 'tasks/social-post-generator/chat';
    private const KEYWORD_GENERATOR_SUB_TOOL_ID = 13;

    private MessageInterface $message;

    private ConversationMessageCacheService $messageCache;

    public function __construct(
        MessageInterface $message,
        ConversationMessageCacheService $messageCache
    ) {
        $this->message = $message;
        $this->messageCache = $messageCache;
    }

    public function sendMessage(
        MessageRequest $request,
        AiArabicWriterService $writerService,
        EmailWriterService $emailWriterService,
            ScriptGeneratorService $scriptGeneratorService,
            ProductDescriptionGeneratorService $productDescriptionGeneratorService,
            ChatSeoToolService $chatSeoToolService,
            ResumeBuilderService $resumeBuilderService
    )
    {
        Log::info('Message send request received', [
            'user_id' => $request->input('user_id'),
            'authenticated_user_id' => auth()->id(),
            'sub_tool_id' => $request->input('sub_tool_id'),
            'conversation_uuid' => $request->input('conversation_uuid'),
            'user_message' => $request->input('user_message'),
            'state' => $request->input('state'),
            'all' => $request->all(),
        ]);

        try {
            $data = $request->validated();
            $userId = (int) auth()->id();

            if ($userId <= 0) {
                return $this->unauthorized('Unauthorized.');
            }

            $conversation = $this->resolveConversation($data, $userId);
            if (! $conversation) {
                return $this->validationError([
                    'conversation_id' => ['Conversation not found.'],
                    'conversation_uuid' => ['Conversation not found.'],
                ], 'Invalid conversation.');
            }

            $subToolId = (int) ($data['sub_tool_id'] ?? $conversation->sub_tool_id ?? 0);
            $content = $this->resolveInputContent($data);
            $requestedTool = trim((string) $request->input('tool', ''));
            $requestedToolKey = trim((string) $request->input('tool_key', ''));
            $requestedModelKey = trim((string) $request->input('model_key', ''));
            $requestedTaskKey = trim((string) $request->input('task_key', ''));
            $isChatSeoTool = ChatSeoToolService::supports(
                $subToolId,
                $requestedToolKey !== '' ? $requestedToolKey : $requestedTool,
                $requestedModelKey
            );
            $isTextSummarizer = (
                $subToolId === self::TEXT_SUMMARIZER_SUB_TOOL_ID
                || strcasecmp($requestedTool, self::TEXT_SUMMARIZER_TOOL_KEY) === 0
                || strcasecmp($requestedTaskKey, self::TEXT_SUMMARIZER_TASK_KEY) === 0
            );
            $isProductDescriptionGenerator = (
                $subToolId === ProductDescriptionGeneratorService::SUB_TOOL_ID
                || strcasecmp(
                    $requestedTool,
                    ProductDescriptionGeneratorService::TOOL_KEY
                ) === 0
            );

            if ($content === '') {
                if ($isProductDescriptionGenerator) {
                    $content = trim((string) $request->input('state.product', ''));

                    if ($content === '') {
                        return response()->json([
                            'success' => false,
                            'message' => 'يرجى إدخال اسم المنتج أو وصف المنتج أولًا.',
                        ], 422);
                    }
                }
            }

            if ($content === '' && ! $isTextSummarizer && ! $isChatSeoTool) {
                return $this->validationError([
                    'user_message' => ['Message content is required.'],
                ], 'Invalid message data.');
            }

            if ($subToolId === ResumeBuilderService::SUB_TOOL_ID) {
                return $this->success(
                    $resumeBuilderService->handle(
                        $conversation,
                        $data,
                        $request->file('file'),
                        $userId
                    ),
                    'Resume Builder Response Ready.'
                );
            }

            if ($isChatSeoTool) {
                return $this->success(
                    $chatSeoToolService->handle($conversation, $data, $content, $userId),
                    'SEO Tool Response Ready.'
                );
            }

            if ($isTextSummarizer) {
                return $this->handleTextSummarizerFlow(
                    $writerService,
                    $conversation,
                    $data,
                    $userId
                );
            }

            if (
                $subToolId === self::PARAPHRASER_SUB_TOOL_ID
                || strcasecmp($requestedTool, self::PARAPHRASER_TOOL_KEY) === 0
            ) {
                return $this->handleParaphraserFlow(
                    $writerService,
                    $conversation,
                    $data,
                    $content,
                    $userId
                );
            }

            if ($subToolId === self::HEADLINE_GENERATOR_SUB_TOOL_ID) {
                $request->validate([
                    'sub_tool_id' => ['required', 'integer'],
                    'conversation_uuid' => ['nullable', 'uuid'],
                    'user_message' => ['nullable', 'string', 'max:5000'],
                    'message' => ['nullable', 'string', 'max:5000'],
                    'content' => ['nullable', 'string', 'max:5000'],
                    'debug' => ['nullable', 'boolean'],
                    'state' => ['nullable', 'array'],
                    'state.content' => ['nullable', 'string', 'max:1000'],
                    'state.content_type' => ['nullable', 'string', 'max:100'],
                    'state.goal' => ['nullable', 'string', 'max:100'],
                    'state.language' => ['nullable', 'string', 'max:50'],
                    'state.tone' => ['nullable', 'string', 'max:50'],
                    'state.number_of_headlines' => ['nullable', 'integer', 'min:1', 'max:20'],
                    'state.headline_length' => ['nullable', 'string', 'max:50'],
                    'state.extra_options' => ['nullable', 'array'],
                    'state.extra_options.*' => ['string', 'max:150'],
                ]);

                $messageText = $request->input('user_message')
                    ?? $request->input('message')
                    ?? $request->input('content');

                if (! is_string($messageText) || trim($messageText) === '') {
                    return $this->validationError([
                        'user_message' => ['Message text is required.'],
                    ], 'Invalid message data.');
                }

                return $this->handleHeadlineGeneratorFlow(
                    $writerService,
                    $conversation,
                    $data,
                    $content,
                    $userId
                );
            }

            if (
                $subToolId === self::SOCIAL_POST_GENERATOR_SUB_TOOL_ID
                || strcasecmp($requestedTool, self::SOCIAL_POST_GENERATOR_TOOL_KEY) === 0
            ) {
                return $this->handleSocialPostGeneratorFlow(
                    $writerService,
                    $conversation,
                    $data,
                    $content,
                    $userId
                );
            }

            if (
                $subToolId === EmailWriterService::SUB_TOOL_ID
                || strcasecmp($requestedTool, EmailWriterService::TOOL_KEY) === 0
            ) {
                return $this->success(
                    $emailWriterService->handle(
                        $conversation,
                        $data,
                        $content,
                        $userId
                    ),
                    'Email Writer Response Ready.'
                );
            }

            if (
                $subToolId === ScriptGeneratorService::SUB_TOOL_ID
                || strcasecmp($requestedTool, ScriptGeneratorService::TOOL_KEY) === 0
            ) {
                return $this->success(
                    $scriptGeneratorService->handle(
                        $conversation,
                        $data,
                        $content,
                        $userId
                    ),
                    'Script Generator Response Ready.'
                );
            }

            if ($isProductDescriptionGenerator) {
                return $this->success(
                    $productDescriptionGeneratorService->handle(
                        $conversation,
                        $data,
                        $content,
                        $userId
                    ),
                    'Product Description Generator Response Ready.'
                );
            }

            return $this->handleDefaultFlow($conversation, $data, $content, $userId);
        } catch (AiServiceException $th) {
            return $this->aiProviderErrorResponse($th);
        } catch (ValidationException $th) {
            return $this->validationError($th->errors(), 'Invalid SEO tool payload.');
        } catch (HttpExceptionInterface $th) {
            return $this->error(
                $th->getMessage() ?: 'Unsupported file type.',
                $th->getStatusCode()
            );
        } catch (Throwable $th) {
            Log::error('Send message failed.', [
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString(),
                'request_input' => $request->all(),
                'user_id' => auth()->id(),
            ]);

            if (app()->environment('local')) {
                return response()->json([
                    'success' => false,
                    'error' => $th->getMessage(),
                    'trace' => $this->shortTrace($th),
                ], 500);
            }

            return $this->error('Sorry, I could not generate a response right now.');
        }
    }

    public function downloadResumeOutput(string $filename)
    {
        if (! preg_match('/^[A-Za-z0-9-]+\.(docx|pdf)$/', $filename)) {
            abort(404);
        }

        $path = 'resume_outputs/'.$filename;

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $contentType = $extension === 'pdf'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        return Storage::disk('local')->download($path, $filename, [
            'Content-Type' => $contentType,
        ]);
    }

    protected function handleTextSummarizerFlow(
        AiArabicWriterService $writerService,
        Conversation $conversation,
        array $data,
        int $userId
    ) {
        $state = $this->normalizeSummarizerState(is_array($data['state'] ?? null) ? $data['state'] : []);
        $body = trim((string) (
            ($data['body'] ?? null)
            ?: ($state['content'] ?? null)
            ?: ($data['user_message'] ?? null)
            ?: ''
        ));

        if ($body === '') {
            return $this->validationError([
                'body' => ['من فضلك أدخل النص الذي تريد تلخيصه.'],
            ], 'Invalid summarizer payload.');
        }

        $state['content'] = $state['content'] ?: $body;

        $userInstruction = trim((string) ($data['user_message'] ?? ''));
        if ($userInstruction === '') {
            $userInstruction = $body;
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = mb_substr($this->cleanSummaryTitle($body), 0, 120);
        }
        if ($title === '') {
            $title = 'Text summary';
        }

        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = (string) Str::uuid();
        }

        $requestPayload = [
            'user_id' => $userId,
            'sub_tool_id' => self::TEXT_SUMMARIZER_SUB_TOOL_ID,
            'title' => $title,
            'conversation_uuid' => $conversation->uuid,
            'body' => $body,
            'user_message' => $userInstruction,
            'task_key' => self::TEXT_SUMMARIZER_TASK_KEY,
            'tool' => self::TEXT_SUMMARIZER_TOOL_KEY,
            'state' => $state,
        ];

        if ((bool) ($data['regenerate'] ?? false)) {
            $requestPayload['regenerate'] = true;
            $requestPayload['previous_output'] = trim((string) ($data['previous_output'] ?? ''));
        }

        $lockKey = $this->requestLockKey($userId, (int) $conversation->id, $idempotencyKey);
        $lock = Cache::lock($lockKey, 15);

        $processed = $lock->block(5, function () use ($conversation, $body, $userInstruction, $idempotencyKey, $requestPayload, $state) {
            return DB::transaction(function () use ($conversation, $body, $userInstruction, $idempotencyKey, $requestPayload, $state) {
                $existingByKey = Message::where('conversation_id', $conversation->id)
                    ->where('role', 'user')
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingByKey) {
                    return [$existingByKey, false];
                }

                $userMessage = $this->message->send([
                    'conversation_id' => (int) $conversation->id,
                    'role' => 'user',
                    'content' => $body,
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => [
                        'type' => 'summarizer_request',
                        'tool' => self::TEXT_SUMMARIZER_TOOL_KEY,
                        'task_key' => self::TEXT_SUMMARIZER_TASK_KEY,
                        'sub_tool_id' => self::TEXT_SUMMARIZER_SUB_TOOL_ID,
                        'conversation_uuid' => $conversation->uuid,
                        'state' => $state,
                        'request_payload' => $requestPayload,
                        'user_instruction' => $userInstruction,
                    ],
                ]);

                if (! $userMessage || ! isset($userMessage->id)) {
                    return [null, false];
                }

                return [$userMessage, true];
            }, 3);
        });

        /** @var Message|null $userMessage */
        $userMessage = $processed[0] ?? null;
        $wasCreated = (bool) ($processed[1] ?? false);

        if (! $userMessage || ! isset($userMessage->id)) {
            return $this->error('Message could not be saved.');
        }

        $conversation->loadMissing('user.wallet', 'subTool');

        if (! $conversation->user) {
            return $this->error('Conversation user not found.');
        }

        $userMessage->loadMissing('conversation.subTool');
        $this->messageCache->updateAfterMessage($userMessage);
        $this->clearCache($userId);

        $existingAssistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->where('reply_to_message_id', $userMessage->id)
            ->first();

        if ($existingAssistant) {
            $cached = $this->buildTextSummarizerResponseFromAssistant($existingAssistant, $conversation, $userId);

            return $this->success($cached + ['was_created' => false], 'Text Summarizer Response Ready.');
        }

        $endpoint = trim((string) ($conversation->subTool?->endpoint ?? ''));
        if ($endpoint === '') {
            return $this->error('Text summarizer endpoint is not configured.');
        }

        $providerPayload = [
            'user_id' => $userId,
            'sub_tool_id' => self::TEXT_SUMMARIZER_SUB_TOOL_ID,
            'title' => $title,
            'conversation_uuid' => $conversation->uuid,
            'body' => $body,
            'user_message' => $userInstruction,
            'task_key' => self::TEXT_SUMMARIZER_TASK_KEY,
            'tool' => self::TEXT_SUMMARIZER_TOOL_KEY,
            'system_prompt' => $this->buildSummarizerSystemPrompt($state),
        ];

        try {
            $providerResponse = $writerService->generateReplyWithUsage($providerPayload, $endpoint);
        } catch (ValidationException $th) {
            return $this->validationError($th->errors(), 'Invalid SEO tool payload.');
        } catch (Throwable $th) {
            Log::warning('Summarizer first attempt failed', [
                'message' => $th->getMessage(),
                'state' => $state,
                'conversation_uuid' => $conversation->uuid,
            ]);

            if (! $this->isRetriableSummarizerProviderError($th)) {
                return $this->summarizerProviderErrorResponse($th);
            }

            try {
                usleep(500000);
                $providerPayload['system_prompt'] .= "\nReturn a clean plain-text summary only. Avoid markdown tables.";
                $providerResponse = $writerService->generateReplyWithUsage($providerPayload, $endpoint);
            } catch (Throwable $retryThrowable) {
                Log::error('Summarizer retry failed', [
                    'message' => $retryThrowable->getMessage(),
                    'state' => $state,
                    'conversation_uuid' => $conversation->uuid,
                ]);

                return $this->summarizerProviderErrorResponse($retryThrowable);
            }
        }
        $raw = is_array($providerResponse['raw'] ?? null) ? $providerResponse['raw'] : [];
        $rawData = is_array($raw['data'] ?? null) ? $raw['data'] : [];

        $reply = trim((string) (
            $providerResponse['reply']
            ?? ($raw['reply'] ?? ($rawData['reply'] ?? ($raw['message'] ?? ($rawData['message'] ?? ''))))
        ));

        if ($reply === '') {
            return $this->error('Text summarizer returned an empty response.');
        }

        $responseState = $state;
        $responseState['last_output'] = $reply;

        $modelKey = $this->toNullableString(
            ($providerResponse['model_key'] ?? null)
            ?? ($raw['model_key'] ?? ($rawData['model_key'] ?? null))
        );
        $requestId = $this->toNullableString(
            ($providerResponse['request_id'] ?? null)
            ?? ($raw['request_id'] ?? ($rawData['request_id'] ?? null))
        );
        $usage = $this->normalizeUsage($raw['usage'] ?? ($providerResponse['usage'] ?? []));
        $cost = $this->normalizeCost($raw['cost'] ?? ($providerResponse['cost'] ?? []));
        $tokensToDeduct = $this->getTokensToDeduct(['usage' => $usage]);

        $walletSnapshot = [
            'balance' => null,
            'payback_balance' => null,
        ];
        $assistantMessage = null;

        DB::transaction(function () use (
            $conversation,
            $tokensToDeduct,
            $usage,
            $cost,
            $reply,
            $userMessage,
            $modelKey,
            $requestId,
            $requestPayload,
            $responseState,
            $userId,
            &$walletSnapshot,
            &$assistantMessage
        ): void {
            $walletDetails = $this->deductWalletTokens(
                $conversation->user,
                $tokensToDeduct,
                'text_summarizer_ai_usage'
            );

            $walletSnapshot = [
                'balance' => $walletDetails['wallet_after'] ?? null,
                'payback_balance' => $walletDetails['payback_after'] ?? null,
            ];

            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $reply,
                'is_error' => false,
                'reply_to_message_id' => $userMessage->id,
                'metadata' => [
                    'type' => 'result',
                    'tool' => self::TEXT_SUMMARIZER_TOOL_KEY,
                    'task_key' => self::TEXT_SUMMARIZER_TASK_KEY,
                    'model_key' => $modelKey,
                    'request_id' => $requestId,
                    'request_payload' => $requestPayload,
                    'reply' => $reply,
                    'state' => $responseState,
                    'usage' => $usage,
                    'cost' => $cost,
                    'tokens_deducted' => $tokensToDeduct,
                    'sub_tool_id' => self::TEXT_SUMMARIZER_SUB_TOOL_ID,
                    'conversation_uuid' => $conversation->uuid,
                ],
            ]);

            CostLogger::create([
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'sub_tool_id' => self::TEXT_SUMMARIZER_SUB_TOOL_ID,
                'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
                'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
                'total_tokens' => (int) ($usage['total_tokens'] ?? (($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0))),
                'input_cost' => (float) ($cost['input_cost'] ?? 0),
                'output_cost' => (float) ($cost['output_cost'] ?? 0),
                'web_search_cost' => (float) ($cost['web_search_cost'] ?? 0),
                'total_cost' => (float) ($cost['total_cost'] ?? (($cost['input_cost'] ?? 0) + ($cost['output_cost'] ?? 0) + ($cost['web_search_cost'] ?? 0))),
                'currency' => (string) ($cost['currency'] ?? 'USD'),
                'provider_request_id' => $requestId,
                'model_key' => $modelKey,
            ]);
        });

        if (! $assistantMessage) {
            return $this->error('Assistant message could not be saved.');
        }

        $assistantMessage->setRelation('conversation', $conversation);
        $this->messageCache->updateAfterMessage($assistantMessage);
        $this->clearCache($userId);

        return $this->success([
            'success' => true,
            'reply' => $reply,
            'task_key' => self::TEXT_SUMMARIZER_TASK_KEY,
            'tool' => self::TEXT_SUMMARIZER_TOOL_KEY,
            'model_key' => $modelKey,
            'user_id' => $userId,
            'sub_tool_id' => self::TEXT_SUMMARIZER_SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'request_id' => $requestId,
            'debug' => null,
            'usage' => $usage,
            'cost' => $cost,
            'state' => $responseState,
            'wallet' => [
                'balance' => $walletSnapshot['balance'],
                'payback_balance' => $walletSnapshot['payback_balance'],
            ],
            'assistant_message_id' => $assistantMessage->id,
            'was_created' => $wasCreated,
        ], 'Text Summarizer Response Ready.');
    }

    protected function buildTextSummarizerResponseFromAssistant(Message $assistantMessage, Conversation $conversation, int $userId): array
    {
        $metadata = is_array($assistantMessage->metadata ?? null) ? $assistantMessage->metadata : [];
        $wallet = Wallet::where('user_id', $userId)->first();

        return [
            'success' => true,
            'reply' => trim((string) ($metadata['reply'] ?? $assistantMessage->content)),
            'task_key' => (string) ($metadata['task_key'] ?? self::TEXT_SUMMARIZER_TASK_KEY),
            'tool' => (string) ($metadata['tool'] ?? self::TEXT_SUMMARIZER_TOOL_KEY),
            'model_key' => $this->toNullableString($metadata['model_key'] ?? null),
            'user_id' => $userId,
            'sub_tool_id' => self::TEXT_SUMMARIZER_SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'request_id' => $this->toNullableString($metadata['request_id'] ?? null),
            'debug' => null,
            'usage' => $this->normalizeUsage($metadata['usage'] ?? []),
            'cost' => $this->normalizeCost($metadata['cost'] ?? []),
            'state' => is_array($metadata['state'] ?? null)
                ? $this->normalizeSummarizerState($metadata['state'])
                : null,
            'wallet' => [
                'balance' => $wallet ? (int) $wallet->balance : null,
                'payback_balance' => $wallet ? (int) ($wallet->payback_balance ?? 0) : null,
            ],
            'assistant_message_id' => $assistantMessage->id,
            'request_payload' => is_array($metadata['request_payload'] ?? null)
                ? $metadata['request_payload']
                : null,
        ];
    }

    protected function cleanSummaryTitle(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
    }

    protected function normalizeSummarizerState(array $state = []): array
    {
        $stringValue = fn (string $key): ?string => $this->toNullableString($state[$key] ?? null);
        $arrayValue = function (string $key) use ($state): array {
            if (! is_array($state[$key] ?? null)) {
                return [];
            }

            return collect($state[$key])
                ->map(fn ($item) => $this->toNullableString($item))
                ->filter()
                ->values()
                ->all();
        };

        $includeBullets = null;
        if (array_key_exists('include_bullets', $state)) {
            $includeBullets = filter_var($state['include_bullets'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return [
            'content' => $stringValue('content'),
            'language' => $stringValue('language'),
            'summary_type' => $stringValue('summary_type'),
            'length' => $stringValue('length'),
            'audience' => $stringValue('audience'),
            'focus_points' => $arrayValue('focus_points'),
            'output_format' => $stringValue('output_format'),
            'include_bullets' => $includeBullets,
            'extra_options' => $arrayValue('extra_options'),
            'last_output' => $stringValue('last_output'),
        ];
    }

    protected function buildSummarizerSystemPrompt(array $state = []): string
    {
        $language = $state['language'] ?: 'same language as the input';
        $summaryType = $state['summary_type'] ?: 'General Summary';
        $length = $state['length'] ?: 'Medium';
        $audience = $state['audience'] ?: 'General Audience';
        $outputFormat = $state['output_format'] ?: 'Paragraphs';
        $includeBullets = ($state['include_bullets'] ?? null) === true ? 'true' : 'false';
        $focusPoints = ! empty($state['focus_points'])
            ? implode(', ', $state['focus_points'])
            : 'main ideas and important details';
        $extraOptions = ! empty($state['extra_options'])
            ? implode(', ', $state['extra_options'])
            : 'Keep original meaning';

        return <<<PROMPT
You are a text summarizer only.

Always summarize the body field.
Do not write a new article, story, or unrelated content.
Do not continue the text.
Do not explain your process.

Summary requirements:
- Output language: {$language}
- Summary type: {$summaryType}
- Summary length: {$length}
- Target audience: {$audience}
- Focus on: {$focusPoints}
- Output format: {$outputFormat}
- Include bullets: {$includeBullets}
- Extra instructions: {$extraOptions}

Formatting rules:
- If output format is "Key Points" or "Bullet Points", write the summary as clear bullet points.
- If include_bullets is true, use bullet points.
- If the selected language is English, translate the summary into clear English.
- Preserve important names, dates, numbers, places, and organizations.
- Remove repetition and keep the meaning accurate.
- Do not include the previous output unless explicitly requested.

If body is empty, ask the user to provide text to summarize.
PROMPT;
    }

    protected function isRetriableSummarizerProviderError(Throwable $th): bool
    {
        $haystack = $th->getMessage();

        if (method_exists($th, 'context')) {
            $context = $th->context();
            $haystack .= ' '.json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return str_contains($haystack, 'OpenRouter')
            || str_contains($haystack, '500')
            || str_contains($haystack, 'Internal Server Error');
    }

    protected function summarizerProviderErrorResponse(Throwable $th)
    {
        return response()->json([
            'status' => 'error',
            'message' => 'AI provider failed while regenerating the summary. Please try again.',
            'debug' => config('app.debug') ? [
                'provider_error' => $th->getMessage(),
            ] : null,
        ], 502);
    }

    protected function aiProviderErrorResponse(AiServiceException $th)
    {
        $context = $th->context();
        $status = (int) ($context['status'] ?? $th->getCode() ?: 502);

        if ($status < 400 || $status > 599) {
            $status = 502;
        }

        $message = $this->toNullableString($context['friendly_message'] ?? null)
            ?? $this->toNullableString($th->getMessage())
            ?? 'AI provider failed while generating the response. Please try again.';

        if ($status === 429) {
            $message = $this->toNullableString($context['friendly_message'] ?? null)
                ?? 'الموديل مشغول مؤقتًا، حاول مرة أخرى بعد قليل.';
        }

        Log::warning('AI provider error returned to client.', [
            'status' => $status,
            'message' => $th->getMessage(),
            'context' => $context,
        ]);

        return response()->json([
            'status' => 'error',
            'message' => $message,
            'code' => $context['code'] ?? ($status === 429 ? 'AI_RATE_LIMITED' : 'AI_PROVIDER_ERROR'),
            'detail' => config('app.debug') ? $th->getMessage() : null,
        ], $status);
    }

    protected function handleDefaultFlow(Conversation $conversation, array $data, string $content, int $userId)
    {
        $normalized = $data;
        $normalized['conversation_id'] = (int) $conversation->id;
        $normalized['role'] = 'user';
        $normalized['content'] = $content;
        $normalized['is_error'] = false;
        $normalized['idempotency_key'] = trim((string) ($normalized['idempotency_key'] ?? ''));

        if ($normalized['idempotency_key'] === '') {
            $normalized['idempotency_key'] = (string) Str::uuid();
        }

        $requestState = is_array($normalized['state'] ?? null) ? $normalized['state'] : null;
        $subToolId = (int) ($data['sub_tool_id'] ?? $conversation->sub_tool_id ?? 0);

        if ($subToolId === self::KEYWORD_GENERATOR_SUB_TOOL_ID && is_array($requestState)) {
            $requestState['last_output'] = null;
        }

        if ($requestState !== null) {
            $requestPayload = [
                'user_id' => $userId,
                'sub_tool_id' => $subToolId,
                'conversation_uuid' => $conversation->uuid,
                'user_message' => $content,
                'state' => $requestState,
                'debug' => (bool) ($data['debug'] ?? false),
            ];

            foreach (['tool', 'tool_key', 'model_key', 'regenerate', 'previous_output'] as $key) {
                if (array_key_exists($key, $data)) {
                    $requestPayload[$key] = $data[$key];
                }
            }

            $normalized['metadata'] = [
                'state' => $requestState,
                'sub_tool_id' => $subToolId,
                'conversation_uuid' => $conversation->uuid,
                'tool' => $data['tool'] ?? null,
                'tool_key' => $data['tool_key'] ?? ($data['tool'] ?? null),
                'model_key' => $data['model_key'] ?? null,
                'regenerate' => (bool) ($data['regenerate'] ?? false),
                'previous_output' => trim((string) ($data['previous_output'] ?? '')),
                'request_payload' => $requestPayload,
            ];
        }

        $taskOptions = $this->normalizeTaskOptions($normalized['task_options'] ?? null);
        $userWordsCount = $this->countWords($normalized['content']);
        $aiWordsCount = null;

        unset(
            $normalized['id'],
            $normalized['created_at'],
            $normalized['updated_at'],
            $normalized['deleted_at'],
            $normalized['reply_to_message_id'],
            $normalized['task_options'],
            $normalized['state'],
            $normalized['debug'],
            $normalized['user_message'],
            $normalized['message'],
            $normalized['conversation_uuid']
        );

        $lockKey = $this->requestLockKey(
            $userId,
            $normalized['conversation_id'],
            $normalized['idempotency_key']
        );

        $lock = Cache::lock($lockKey, 15);

        $processed = $lock->block(5, function () use ($normalized) {
            return DB::transaction(function () use ($normalized) {
                $existingByKey = Message::where('conversation_id', $normalized['conversation_id'])
                    ->where('role', 'user')
                    ->where('idempotency_key', $normalized['idempotency_key'])
                    ->first();

                if ($existingByKey) {
                    return [$existingByKey, false];
                }

                $existingRecentDuplicate = null;

                if (! (bool) ($normalized['regenerate'] ?? false)) {
                    $existingRecentDuplicate = Message::where('conversation_id', $normalized['conversation_id'])
                        ->where('role', 'user')
                        ->where('content', $normalized['content'])
                        ->where('created_at', '>=', now()->subSeconds(20))
                        ->orderByDesc('id')
                        ->first();
                }

                if ($existingRecentDuplicate) {
                    if (empty($existingRecentDuplicate->idempotency_key)) {
                        $existingRecentDuplicate->idempotency_key = $normalized['idempotency_key'];
                        $existingRecentDuplicate->save();
                    }

                    return [$existingRecentDuplicate, false];
                }

                $userMessage = $this->message->send($normalized);

                if (! $userMessage || ! isset($userMessage->id)) {
                    return [null, false];
                }

                return [$userMessage, true];
            }, 3);
        });

        /** @var Message|null $userMessage */
        $userMessage = $processed[0] ?? null;
        $wasCreated = (bool) ($processed[1] ?? false);

        if (! $userMessage || ! isset($userMessage->id)) {
            return $this->error('Message could not be saved.');
        }

        if ($wasCreated) {
            $userMessage->loadMissing('conversation');
            $this->messageCache->updateAfterMessage($userMessage);
            $this->clearCache($userId);
            $this->dispatchAssistantReplyIfNeeded(
                $userMessage,
                $taskOptions,
                $requestState,
                (bool) ($data['debug'] ?? false)
            );
        }

        return $this->success([
            'message_id' => $userMessage->id,
            'conversation_id' => $userMessage->conversation_id,
            'message' => $userMessage,
            'assistant' => null,
            'usage' => null,
            'cost' => null,
            'wallet' => [
                'points_charged' => null,
                'balance' => null,
            ],
            'was_created' => $wasCreated,
            'user_words_count' => $userWordsCount,
            'ai_words_count' => $aiWordsCount,
        ], 'Message Sent Successfully.');
    }

    protected function handleHeadlineGeneratorFlow(
        AiArabicWriterService $writerService,
        Conversation $conversation,
        array $data,
        string $content,
        int $userId
    ) {
        $initialState = [
            'content' => null,
            'content_type' => null,
            'goal' => null,
            'language' => null,
            'tone' => null,
            'number_of_headlines' => null,
            'headline_length' => null,
            'extra_options' => [],
        ];

        $state = array_merge($initialState, is_array($data['state'] ?? null) ? $data['state'] : []);
        $state = $this->normalizeHeadlineState($state);
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = (string) Str::uuid();
        }

        $lockKey = $this->requestLockKey($userId, (int) $conversation->id, $idempotencyKey);
        $lock = Cache::lock($lockKey, 15);

        $processed = $lock->block(5, function () use ($conversation, $content, $idempotencyKey, $state) {
            return DB::transaction(function () use ($conversation, $content, $idempotencyKey, $state) {
                $existingByKey = Message::where('conversation_id', $conversation->id)
                    ->where('role', 'user')
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingByKey) {
                    return [$existingByKey, false];
                }

                $existingRecentDuplicate = Message::where('conversation_id', $conversation->id)
                    ->where('role', 'user')
                    ->where('content', $content)
                    ->where('created_at', '>=', now()->subSeconds(20))
                    ->orderByDesc('id')
                    ->first();

                if ($existingRecentDuplicate) {
                    if (empty($existingRecentDuplicate->idempotency_key)) {
                        $existingRecentDuplicate->idempotency_key = $idempotencyKey;
                        $existingRecentDuplicate->save();
                    }

                    return [$existingRecentDuplicate, false];
                }

                $userMessage = $this->message->send([
                    'conversation_id' => (int) $conversation->id,
                    'role' => 'user',
                    'content' => $content,
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => [
                        'type' => 'headline_request',
                        'tool' => 'ai_headline_generator',
                        'state' => $state,
                        'sub_tool_id' => self::HEADLINE_GENERATOR_SUB_TOOL_ID,
                        'conversation_uuid' => $conversation->uuid,
                    ],
                ]);

                if (! $userMessage || ! isset($userMessage->id)) {
                    return [null, false];
                }

                return [$userMessage, true];
            }, 3);
        });

        /** @var Message|null $userMessage */
        $userMessage = $processed[0] ?? null;
        $wasCreated = (bool) ($processed[1] ?? false);

        if (! $userMessage || ! isset($userMessage->id)) {
            return $this->error('Message could not be saved.');
        }

        $conversation->loadMissing('user.wallet');

        if (! $conversation->user) {
            return $this->error('Conversation user not found.');
        }

        $userMessage->loadMissing('conversation.subTool');
        $this->messageCache->updateAfterMessage($userMessage);
        $this->clearCache($userId);

        $existingAssistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->where('reply_to_message_id', $userMessage->id)
            ->first();

        if ($existingAssistant) {
            $cached = $this->buildHeadlineResponseFromAssistant($existingAssistant, $conversation, $userId);

            return $this->success($cached + ['was_created' => false], 'Headline Response Ready.');
        }

        $payload = [
            'user_id' => $userId,
            'sub_tool_id' => self::HEADLINE_GENERATOR_SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => $content,
            'debug' => (bool) ($data['debug'] ?? false),
            'state' => $state,
        ];

        if (! is_array($payload) || empty($payload) || trim((string) ($payload['user_message'] ?? '')) === '') {
            return $this->validationError([
                'payload' => ['Headline payload body is required.'],
            ], 'Invalid headline payload.');
        }

        Log::info('Headline generator payload prepared', [
            'conversation_id' => $conversation->id,
            'conversation_uuid' => $conversation->uuid,
            'payload_is_array' => is_array($payload),
            'payload_keys_count' => is_array($payload) ? count($payload) : 0,
            'state_keys_count' => is_array($payload['state'] ?? null) ? count($payload['state']) : 0,
        ]);

        $providerResponse = $writerService->generateReplyWithUsage($payload, self::HEADLINE_ENDPOINT);
        $raw = is_array($providerResponse['raw'] ?? null) ? $providerResponse['raw'] : [];

        $type = (string) (($providerResponse['type'] ?? null) ?? ($raw['type'] ?? 'message'));
        if (! in_array($type, ['question', 'result'], true)) {
            $type = 'message';
        }

        $responseState = $this->normalizeHeadlineState(
            is_array($providerResponse['state'] ?? null) ? $providerResponse['state'] : ($raw['state'] ?? [])
        );
        $mergedState = $this->mergeHeadlineState($state, $responseState);
        $headlines = $this->normalizeHeadlines(
            is_array($providerResponse['headlines'] ?? null) ? $providerResponse['headlines'] : ($raw['headlines'] ?? [])
        );

        $usage = $this->normalizeUsage($raw['usage'] ?? ($providerResponse['usage'] ?? []));
        $cost = $this->normalizeCost($raw['cost'] ?? ($providerResponse['cost'] ?? []));
        $tokensToDeduct = $this->getTokensToDeduct([
            'usage' => $usage,
        ]);

        $responseMessage = trim((string) ($raw['message'] ?? ($providerResponse['reply'] ?? '')));
        if ($responseMessage === '') {
            $responseMessage = $type === 'question'
                ? 'من فضلك أكمل البيانات المطلوبة.'
                : 'تم توليد العناوين بنجاح.';
        }

        $provider = $this->toNullableString(($providerResponse['provider'] ?? null) ?? ($raw['provider'] ?? null));
        $modelKey = $this->toNullableString(($providerResponse['model_key'] ?? null) ?? ($raw['model_key'] ?? null));
        $requestId = $this->toNullableString(($providerResponse['request_id'] ?? null) ?? ($raw['request_id'] ?? null));
        $tool = $this->toNullableString(($providerResponse['tool'] ?? null) ?? ($raw['tool'] ?? 'ai_headline_generator')) ?? 'ai_headline_generator';

        $assistantContent = $this->buildAssistantContent($type, $responseMessage, $headlines);
        $shouldResetState = $type === 'result';
        $walletSnapshot = [
            'balance' => null,
            'payback_balance' => null,
        ];
        $assistantMessage = null;

        DB::transaction(function () use (
            $conversation,
            $tokensToDeduct,
            $type,
            $usage,
            $cost,
            $assistantContent,
            $userMessage,
            $tool,
            $provider,
            $modelKey,
            $requestId,
            $mergedState,
            $headlines,
            $providerResponse,
            $raw,
            $responseMessage,
            $userId,
            &$walletSnapshot,
            &$assistantMessage
        ): void {
            $walletDetails = $this->deductWalletTokens(
                $conversation->user,
                $tokensToDeduct,
                'headline_generator_ai_usage'
            );

            $walletSnapshot = [
                'balance' => $walletDetails['wallet_after'] ?? null,
                'payback_balance' => $walletDetails['payback_after'] ?? null,
            ];

            Log::info('Headline generator wallet deduction', [
                'sub_tool_id' => self::HEADLINE_GENERATOR_SUB_TOOL_ID,
                'response_type' => $type,
                'tokens_to_deduct' => $tokensToDeduct,
                'wallet_before' => $walletDetails['wallet_before'] ?? null,
                'wallet_after' => $walletDetails['wallet_after'] ?? null,
                'payback_before' => $walletDetails['payback_before'] ?? null,
                'payback_after' => $walletDetails['payback_after'] ?? null,
                'user_id' => $conversation->user_id,
                'conversation_id' => $conversation->id,
            ]);

            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $assistantContent,
                'is_error' => false,
                'reply_to_message_id' => $userMessage->id,
                'metadata' => [
                    'type' => $type,
                    'tool' => $tool,
                    'provider' => $provider,
                    'model_key' => $modelKey,
                    'request_id' => $requestId,
                    'state' => $mergedState,
                    'headlines' => $headlines,
                    'message' => $responseMessage,
                    'count' => (int) (($providerResponse['count'] ?? null) ?? ($raw['count'] ?? count($headlines))),
                    'usage' => $usage,
                    'cost' => $cost,
                    'tokens_deducted' => $tokensToDeduct,
                    'sub_tool_id' => self::HEADLINE_GENERATOR_SUB_TOOL_ID,
                    'conversation_uuid' => $conversation->uuid,
                ],
            ]);

            CostLogger::create([
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'sub_tool_id' => self::HEADLINE_GENERATOR_SUB_TOOL_ID,
                'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
                'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
                'total_tokens' => (int) ($usage['total_tokens'] ?? (($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0))),
                'input_cost' => (float) ($cost['input_cost'] ?? 0),
                'output_cost' => (float) ($cost['output_cost'] ?? 0),
                'web_search_cost' => (float) ($cost['web_search_cost'] ?? 0),
                'total_cost' => (float) ($cost['total_cost'] ?? (($cost['input_cost'] ?? 0) + ($cost['output_cost'] ?? 0) + ($cost['web_search_cost'] ?? 0))),
                'currency' => (string) ($cost['currency'] ?? 'USD'),
                'provider_request_id' => $requestId,
                'model_key' => $modelKey,
            ]);
        });

        if (! $assistantMessage) {
            return $this->error('Assistant message could not be saved.');
        }

        $assistantMessage->setRelation('conversation', $conversation);
        $this->messageCache->updateAfterMessage($assistantMessage);

        $this->clearCache($userId);

        return $this->success([
            'success' => true,
            'type' => $type,
            'tool' => $tool,
            'provider' => $provider,
            'model_key' => $modelKey,
            'user_id' => $userId,
            'sub_tool_id' => self::HEADLINE_GENERATOR_SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'message' => $responseMessage,
            'state' => $mergedState,
            'headlines' => $headlines,
            'count' => (int) (($providerResponse['count'] ?? null) ?? ($raw['count'] ?? count($headlines))),
            'request_id' => $requestId,
            'debug' => null,
            'usage' => $usage,
            'cost' => $cost,
            'wallet' => [
                'balance' => $walletSnapshot['balance'],
                'payback_balance' => $walletSnapshot['payback_balance'],
            ],
            'should_reset_state' => $shouldResetState,
            'assistant_message_id' => $assistantMessage->id,
            'was_created' => $wasCreated,
        ], 'Headline Response Ready.');
    }

    protected function handleSocialPostGeneratorFlow(
        AiArabicWriterService $writerService,
        Conversation $conversation,
        array $data,
        string $content,
        int $userId
    ) {
        $requestState = $this->normalizeSocialPostState($data['state'] ?? []);
        $latestState = $this->resolveLatestSocialPostState($conversation);
        $state = $this->mergeSocialPostState($latestState, $requestState);

        Log::info('SocialPostGenerator request received', [
            'user_id' => $userId,
            'sub_tool_id' => self::SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => $content,
            'request_state' => $requestState,
            'state' => $state,
        ]);

        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = (string) Str::uuid();
        }

        $lock = Cache::lock(
            $this->requestLockKey($userId, (int) $conversation->id, $idempotencyKey),
            15
        );

        $processed = $lock->block(5, function () use (
            $conversation,
            $content,
            $idempotencyKey,
            $state
        ) {
            return DB::transaction(function () use (
                $conversation,
                $content,
                $idempotencyKey,
                $state
            ) {
                $existing = Message::where('conversation_id', $conversation->id)
                    ->where('role', 'user')
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    return [$existing, false];
                }

                $recentDuplicate = Message::where('conversation_id', $conversation->id)
                    ->where('role', 'user')
                    ->where('content', $content)
                    ->where('created_at', '>=', now()->subSeconds(20))
                    ->orderByDesc('id')
                    ->first();

                if ($recentDuplicate) {
                    if (empty($recentDuplicate->idempotency_key)) {
                        $recentDuplicate->idempotency_key = $idempotencyKey;
                        $recentDuplicate->save();
                    }

                    return [$recentDuplicate, false];
                }

                $userMessage = $this->message->send([
                    'conversation_id' => (int) $conversation->id,
                    'role' => 'user',
                    'content' => $content,
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => [
                        'type' => 'user_input',
                        'tool' => self::SOCIAL_POST_GENERATOR_TOOL_KEY,
                        'model_key' => self::SOCIAL_POST_GENERATOR_MODEL_KEY,
                        'sub_tool_id' => self::SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
                        'conversation_uuid' => $conversation->uuid,
                        'state' => $state,
                    ],
                ]);

                return [$userMessage, true];
            }, 3);
        });

        /** @var Message|null $userMessage */
        $userMessage = $processed[0] ?? null;
        $wasCreated = (bool) ($processed[1] ?? false);

        if (! $userMessage || ! isset($userMessage->id)) {
            return $this->error('Message could not be saved.');
        }

        $conversation->loadMissing('user.wallet', 'subTool');
        if (! $conversation->user) {
            return $this->error('Conversation user not found.');
        }

        $userMessage->loadMissing('conversation.subTool');
        $this->messageCache->updateAfterMessage($userMessage);
        $this->clearCache($userId);

        $existingAssistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->where('reply_to_message_id', $userMessage->id)
            ->first();

        if ($existingAssistant) {
            $cached = $this->buildSocialPostResponseFromAssistant(
                $existingAssistant,
                $conversation,
                $userId
            );

            return $this->success($cached + ['was_created' => false], 'Social Post Response Ready.');
        }

        $payload = [
            'user_id' => $userId,
            'sub_tool_id' => self::SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'conversation_id' => $conversation->id,
            'content' => $content,
            'user_message' => $content,
            'role' => 'user',
            'tool' => self::SOCIAL_POST_GENERATOR_TOOL_KEY,
            'model_key' => self::SOCIAL_POST_GENERATOR_MODEL_KEY,
            'state' => $state,
            'debug' => (bool) ($data['debug'] ?? false),
        ];

        $providerError = null;

        try {
            $providerResponse = $writerService->generateReplyWithUsage(
                $payload,
                self::SOCIAL_POST_GENERATOR_ENDPOINT
            );
        } catch (Throwable $th) {
            $providerError = $th;

            Log::error('SocialPostGenerator failed', [
                'message' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString(),
            ]);

            $errorMessage = ((bool) ($data['debug'] ?? false) || app()->environment('local'))
                ? $th->getMessage()
                : 'حدث خطأ أثناء توليد منشور السوشيال.';

            $providerResponse = [
                'reply' => $errorMessage,
                'provider' => 'openrouter',
                'model_key' => self::SOCIAL_POST_GENERATOR_MODEL_KEY,
                'raw' => [
                    'success' => false,
                    'type' => 'error',
                    'tool' => self::SOCIAL_POST_GENERATOR_TOOL_KEY,
                    'provider' => 'openrouter',
                    'model_key' => self::SOCIAL_POST_GENERATOR_MODEL_KEY,
                    'message' => $errorMessage,
                ],
            ];
        }

        $raw = is_array($providerResponse['raw'] ?? null) ? $providerResponse['raw'] : [];
        $providerSuccess = (bool) ($raw['success'] ?? ($raw['data']['success'] ?? true));

        $responseState = $this->normalizeSocialPostState(
            $providerResponse['state'] ?? ($raw['state'] ?? ($raw['data']['state'] ?? []))
        );
        $mergedState = $this->mergeSocialPostState($state, $responseState);
        $results = $this->normalizeSocialPostResults(
            $raw['results'] ?? ($raw['data']['results'] ?? [])
        );
        $missingFields = $this->getMissingSocialPostFields($mergedState);

        $type = strtolower(trim((string) (
            $providerResponse['type'] ?? ($raw['type'] ?? ($raw['data']['type'] ?? ''))
        )));

        if (! $providerSuccess) {
            $type = 'error';
        } elseif ($type === 'result' || count($results) > 0) {
            $type = 'result';
        } elseif (count($missingFields) > 0) {
            $type = 'question';
            $results = [];
        } else {
            $type = 'error';
        }

        if ($type === 'result' && count($results) > 0) {
            $mergedState['last_output'] = $this->buildSocialPostOutputFromResults($results);
        }

        $responseMessage = trim((string) (
            $raw['message']
            ?? ($raw['data']['message'] ?? ($providerResponse['reply'] ?? ''))
        ));

        if ($responseMessage === '') {
            $responseMessage = match ($type) {
                'question' => 'من فضلك أكمل البيانات المطلوبة لتوليد منشور السوشيال.',
                'result' => 'تم توليد منشور السوشيال بنجاح.',
                default => 'حدث خطأ أثناء توليد منشور السوشيال.',
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
        $modelKey = self::SOCIAL_POST_GENERATOR_MODEL_KEY;
        $requestId = $this->toNullableString(
            $providerResponse['request_id'] ?? ($raw['request_id'] ?? ($raw['data']['request_id'] ?? null))
        );
        $tool = self::SOCIAL_POST_GENERATOR_TOOL_KEY;
        $count = $type === 'result' ? count($results) : 0;
        $assistantContent = $type === 'result'
            ? $this->buildSocialPostOutputFromResults($results)
            : $responseMessage;
        $tokensToDeduct = $type === 'result'
            ? $this->getTokensToDeduct(['usage' => $usage])
            : 0;
        $walletSnapshot = ['balance' => null, 'payback_balance' => null];
        $assistantMessage = null;

        DB::transaction(function () use (
            $conversation,
            $tokensToDeduct,
            $type,
            $usage,
            $cost,
            $usageMetadata,
            $costMetadata,
            $assistantContent,
            $userMessage,
            $tool,
            $provider,
            $modelKey,
            $requestId,
            $mergedState,
            $results,
            $responseMessage,
            $count,
            $userId,
            $providerError,
            &$walletSnapshot,
            &$assistantMessage
        ): void {
            if ($type === 'result') {
                $walletDetails = $this->deductWalletTokens(
                    $conversation->user,
                    $tokensToDeduct,
                    'social_post_generator_ai_usage'
                );

                $walletSnapshot = [
                    'balance' => $walletDetails['wallet_after'] ?? null,
                    'payback_balance' => $walletDetails['payback_after'] ?? null,
                ];
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
                    'tool' => $tool,
                    'provider' => $provider,
                    'model_key' => $modelKey,
                    'request_id' => $requestId,
                    'state' => $mergedState,
                    'results' => $results,
                    'message' => $responseMessage,
                    'count' => $count,
                    'usage' => $usageMetadata,
                    'cost' => $costMetadata,
                    'tokens_deducted' => $tokensToDeduct,
                    'debug_error' => $providerError?->getMessage(),
                    'sub_tool_id' => self::SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
                    'conversation_uuid' => $conversation->uuid,
                ],
            ]);

            if ($type === 'result') {
                CostLogger::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                    'sub_tool_id' => self::SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
                    'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
                    'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
                    'total_tokens' => (int) ($usage['total_tokens'] ?? 0),
                    'input_cost' => (float) ($cost['input_cost'] ?? 0),
                    'output_cost' => (float) ($cost['output_cost'] ?? 0),
                    'web_search_cost' => (float) ($cost['web_search_cost'] ?? 0),
                    'total_cost' => (float) ($cost['total_cost'] ?? 0),
                    'currency' => (string) ($cost['currency'] ?? 'USD'),
                    'provider_request_id' => $requestId,
                    'model_key' => $modelKey,
                ]);
            }
        });

        if (! $assistantMessage) {
            return $this->error('Assistant message could not be saved.');
        }

        $assistantMessage->setRelation('conversation', $conversation);
        $this->messageCache->updateAfterMessage($assistantMessage);
        $this->clearCache($userId);

        return $this->success([
            'success' => $type !== 'error',
            'type' => $type,
            'tool' => $tool,
            'provider' => $provider,
            'model_key' => $modelKey,
            'user_id' => $userId,
            'sub_tool_id' => self::SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'message' => $responseMessage,
            'state' => $mergedState,
            'results' => $results,
            'count' => $count,
            'request_id' => $requestId,
            'usage' => $usageMetadata,
            'cost' => $costMetadata,
            'tokens_deducted' => $tokensToDeduct,
            'debug' => (bool) ($data['debug'] ?? false) && $providerError
                ? ['error' => $providerError->getMessage()]
                : null,
            'wallet' => $walletSnapshot,
            'assistant_message_id' => $assistantMessage->id,
            'was_created' => $wasCreated,
        ], 'Social Post Response Ready.');
    }

    protected function persistSocialPostQuestion(
        Conversation $conversation,
        Message $userMessage,
        array $state,
        int $userId,
        bool $wasCreated
    ) {
        $message = 'من فضلك أكمل البيانات المطلوبة لتوليد منشور السوشيال.';
        $walletSnapshot = [
            'balance' => null,
            'payback_balance' => null,
        ];

        $assistantMessage = DB::transaction(function () use (
            $conversation,
            $userMessage,
            $state,
            $message,
            &$walletSnapshot
        ): Message {
            $wallet = Wallet::where('user_id', $conversation->user_id)
                ->lockForUpdate()
                ->first();

            $walletSnapshot = [
                'balance' => $wallet ? (int) $wallet->balance : null,
                'payback_balance' => $wallet ? (int) ($wallet->payback_balance ?? 0) : null,
            ];

            return Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $message,
                'is_error' => false,
                'reply_to_message_id' => $userMessage->id,
                'metadata' => [
                    'type' => 'question',
                    'tool' => self::SOCIAL_POST_GENERATOR_TOOL_KEY,
                    'provider' => 'openrouter',
                    'model_key' => self::SOCIAL_POST_GENERATOR_MODEL_KEY,
                    'request_id' => null,
                    'state' => $state,
                    'results' => [],
                    'message' => $message,
                    'count' => 0,
                    'usage' => null,
                    'cost' => null,
                    'tokens_deducted' => 0,
                    'sub_tool_id' => self::SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
                    'conversation_uuid' => $conversation->uuid,
                ],
            ]);
        });

        $assistantMessage->setRelation('conversation', $conversation);
        $this->messageCache->updateAfterMessage($assistantMessage);
        $this->clearCache($userId);

        return $this->success([
            'success' => true,
            'type' => 'question',
            'tool' => self::SOCIAL_POST_GENERATOR_TOOL_KEY,
            'provider' => 'openrouter',
            'model_key' => self::SOCIAL_POST_GENERATOR_MODEL_KEY,
            'user_id' => $userId,
            'sub_tool_id' => self::SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'message' => $message,
            'state' => $state,
            'results' => [],
            'count' => 0,
            'request_id' => null,
            'debug' => null,
            'usage' => null,
            'cost' => null,
            'tokens_deducted' => 0,
            'wallet' => $walletSnapshot,
            'assistant_message_id' => $assistantMessage->id,
            'was_created' => $wasCreated,
        ], 'Social Post Response Ready.');
    }

    protected function buildSocialPostResponseFromAssistant(
        Message $assistantMessage,
        Conversation $conversation,
        int $userId
    ): array {
        $metadata = is_array($assistantMessage->metadata ?? null) ? $assistantMessage->metadata : [];
        $results = $this->normalizeSocialPostResults($metadata['results'] ?? []);
        $type = (string) ($metadata['type'] ?? ($assistantMessage->is_error ? 'error' : 'result'));
        $wallet = Wallet::where('user_id', $userId)->first();

        return [
            'success' => $type !== 'error',
            'type' => $type,
            'tool' => (string) ($metadata['tool'] ?? self::SOCIAL_POST_GENERATOR_TOOL_KEY),
            'provider' => $this->toNullableString($metadata['provider'] ?? null) ?? 'openrouter',
            'model_key' => $this->toNullableString($metadata['model_key'] ?? null)
                ?? self::SOCIAL_POST_GENERATOR_MODEL_KEY,
            'user_id' => $userId,
            'sub_tool_id' => self::SOCIAL_POST_GENERATOR_SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'message' => $this->toNullableString($metadata['message'] ?? null)
                ?? trim((string) $assistantMessage->content),
            'state' => $this->normalizeSocialPostState($metadata['state'] ?? []),
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

    protected function resolveLatestSocialPostState(Conversation $conversation): array
    {
        $message = Message::where('conversation_id', $conversation->id)
            ->whereIn('role', ['user', 'assistant'])
            ->whereNotNull('metadata')
            ->orderByDesc('id')
            ->get()
            ->first(function (Message $message): bool {
                $metadata = is_array($message->metadata ?? null) ? $message->metadata : [];

                return (
                    strtolower((string) ($metadata['tool'] ?? '')) === self::SOCIAL_POST_GENERATOR_TOOL_KEY
                    || (int) ($metadata['sub_tool_id'] ?? 0) === self::SOCIAL_POST_GENERATOR_SUB_TOOL_ID
                ) && is_array($metadata['state'] ?? null);
            });

        if (! $message) {
            return $this->normalizeSocialPostState([]);
        }

        $metadata = is_array($message->metadata ?? null) ? $message->metadata : [];

        return $this->normalizeSocialPostState($metadata['state'] ?? []);
    }

    protected function normalizeSocialPostState(mixed $state): array
    {
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            $state = is_array($decoded) ? $decoded : [];
        }

        $state = is_array($state) ? $state : [];
        $merged = array_merge([
            'content' => null,
            'platform' => null,
            'language' => null,
            'tone' => null,
            'audience' => null,
            'goal' => null,
            'length' => null,
            'hashtag_count' => null,
            'include_emojis' => null,
            'results_count' => null,
            'extra_options' => [],
            'last_output' => null,
        ], $state);

        foreach (['content', 'platform', 'language', 'tone', 'audience', 'goal', 'length', 'last_output'] as $key) {
            $merged[$key] = $this->toNullableString($merged[$key] ?? null);
        }

        $hashtagCount = $merged['hashtag_count'] ?? null;
        if ($hashtagCount === null || $hashtagCount === '') {
            $merged['hashtag_count'] = null;
        } else {
            $merged['hashtag_count'] = is_numeric($hashtagCount) && (int) $hashtagCount >= 0
                ? min(30, (int) $hashtagCount)
                : null;
        }

        $includeEmojis = $merged['include_emojis'] ?? null;
        if (is_bool($includeEmojis)) {
            $merged['include_emojis'] = $includeEmojis;
        } elseif (in_array($includeEmojis, [1, '1', 'true'], true)) {
            $merged['include_emojis'] = true;
        } elseif (in_array($includeEmojis, [0, '0', 'false'], true)) {
            $merged['include_emojis'] = false;
        } else {
            $merged['include_emojis'] = null;
        }

        $resultsCount = $merged['results_count'] ?? null;
        $merged['results_count'] = is_numeric($resultsCount) && (int) $resultsCount > 0
            ? min(20, (int) $resultsCount)
            : null;

        $extraOptions = is_array($merged['extra_options'] ?? null) ? $merged['extra_options'] : [];
        $merged['extra_options'] = collect($extraOptions)
            ->map(fn ($item) => $this->toNullableString($item))
            ->filter()
            ->values()
            ->all();

        return $merged;
    }

    protected function enrichSocialPostStateFromMessage(array $state, string $message): array
    {
        $state = $this->normalizeSocialPostState($state);
        $message = trim($message);

        if ($message === '') {
            return $state;
        }

        if ($state['content'] === null) {
            $state['content'] = $message;
        }

        if ($state['platform'] === null) {
            $platformPatterns = [
                'LinkedIn' => '/\blinked\s*in\b|\blinkedin\b/iu',
                'Facebook' => '/\bfacebook\b/iu',
                'Instagram' => '/\binstagram\b/iu',
                'X' => '/\btwitter\b|(?:^|\s)x(?:\s|$)/iu',
            ];

            foreach ($platformPatterns as $platform => $pattern) {
                if (preg_match($pattern, $message) === 1) {
                    $state['platform'] = $platform;
                    break;
                }
            }
        }

        if ($state['language'] === null) {
            $state['language'] = preg_match('/\p{Arabic}/u', $message) === 1
                ? 'Arabic'
                : 'English';
        }

        return $this->normalizeSocialPostState($state);
    }

    protected function mergeSocialPostState(array $oldState, array $newState): array
    {
        $merged = $this->normalizeSocialPostState($oldState);
        $incoming = $this->normalizeSocialPostState($newState);

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

        return $this->normalizeSocialPostState($merged);
    }

    protected function getMissingSocialPostFields(array $state): array
    {
        $state = $this->normalizeSocialPostState($state);
        $required = [
            'content',
            'platform',
            'language',
            'tone',
            'audience',
            'goal',
            'length',
            'hashtag_count',
            'include_emojis',
            'results_count',
        ];

        return collect($required)
            ->filter(fn (string $key): bool => $state[$key] === null || $state[$key] === '')
            ->values()
            ->all();
    }

    protected function normalizeSocialPostResults(mixed $results): array
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
                        : ($index + 1),
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

    protected function buildSocialPostOutputFromResults(array $results): string
    {
        return collect($results)
            ->map(fn (array $result): string => trim((string) ($result['text'] ?? '')))
            ->filter()
            ->implode("\n\n");
    }

    protected function handleParaphraserFlow(
        AiArabicWriterService $writerService,
        Conversation $conversation,
        array $data,
        string $content,
        int $userId
    ) {
        $rawRequestState = $data['state'] ?? null;
        $requestState = $this->normalizeParaphraserState($rawRequestState);
        $lastKnownState = $this->resolveLatestParaphraserState($conversation);
        $mergedState = $this->mergeParaphraserState($lastKnownState, $requestState);

        if (($mergedState['content'] ?? null) === null) {
            $mergedState['content'] = $this->toNullableString($content);
        }

        $mergedState = $this->normalizeParaphraserState($mergedState);

        Log::info('Paraphraser request state received in controller.', [
            'conversation_id' => $conversation->id,
            'conversation_uuid' => $conversation->uuid,
            'user_id' => $userId,
            'sub_tool_id' => self::PARAPHRASER_SUB_TOOL_ID,
            'state_keys_received' => is_array($rawRequestState) ? array_keys($rawRequestState) : [],
            'state_received_is_null' => $rawRequestState === null,
        ]);
        Log::info('Paraphraser merged state prepared', [
            'conversation_id' => $conversation->id ?? null,
            'sub_tool_id' => self::PARAPHRASER_SUB_TOOL_ID,
            'state_keys' => array_keys($mergedState),
            'has_content' => ! empty($mergedState['content']),
        ]);

        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($idempotencyKey === '') {
            $idempotencyKey = (string) Str::uuid();
        }

        $lockKey = $this->requestLockKey($userId, (int) $conversation->id, $idempotencyKey);
        $lock = Cache::lock($lockKey, 15);

        $processed = $lock->block(5, function () use ($conversation, $content, $idempotencyKey, $mergedState) {
            return DB::transaction(function () use ($conversation, $content, $idempotencyKey, $mergedState) {
                $existingByKey = Message::where('conversation_id', $conversation->id)
                    ->where('role', 'user')
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingByKey) {
                    $existingMetadata = is_array($existingByKey->metadata ?? null) ? $existingByKey->metadata : [];
                    $existingMetadata['state'] = $this->mergeParaphraserState(
                        $this->normalizeParaphraserState($existingMetadata['state'] ?? []),
                        $mergedState
                    );
                    $existingMetadata['type'] = 'paraphraser_request';
                    $existingMetadata['tool'] = self::PARAPHRASER_TOOL_KEY;
                    $existingMetadata['sub_tool_id'] = self::PARAPHRASER_SUB_TOOL_ID;
                    $existingMetadata['conversation_uuid'] = $conversation->uuid;
                    $existingMetadata['source_content'] = $this->toNullableString($existingMetadata['state']['content'] ?? null);
                    $existingMetadata['user_instruction'] = $this->toNullableString($content);
                    $existingByKey->metadata = $existingMetadata;
                    $existingByKey->save();

                    return [$existingByKey, false];
                }

                $existingRecentDuplicate = Message::where('conversation_id', $conversation->id)
                    ->where('role', 'user')
                    ->where('content', $content)
                    ->where('created_at', '>=', now()->subSeconds(20))
                    ->orderByDesc('id')
                    ->first();

                if ($existingRecentDuplicate) {
                    $existingMetadata = is_array($existingRecentDuplicate->metadata ?? null) ? $existingRecentDuplicate->metadata : [];
                    $existingMetadata['state'] = $this->mergeParaphraserState(
                        $this->normalizeParaphraserState($existingMetadata['state'] ?? []),
                        $mergedState
                    );
                    $existingMetadata['type'] = 'paraphraser_request';
                    $existingMetadata['tool'] = self::PARAPHRASER_TOOL_KEY;
                    $existingMetadata['sub_tool_id'] = self::PARAPHRASER_SUB_TOOL_ID;
                    $existingMetadata['conversation_uuid'] = $conversation->uuid;
                    $existingMetadata['source_content'] = $this->toNullableString($existingMetadata['state']['content'] ?? null);
                    $existingMetadata['user_instruction'] = $this->toNullableString($content);

                    if (empty($existingRecentDuplicate->idempotency_key)) {
                        $existingRecentDuplicate->idempotency_key = $idempotencyKey;
                    }

                    $existingRecentDuplicate->metadata = $existingMetadata;
                    $existingRecentDuplicate->save();

                    return [$existingRecentDuplicate, false];
                }

                $userMessage = $this->message->send([
                    'conversation_id' => (int) $conversation->id,
                    'role' => 'user',
                    'content' => $content,
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => [
                        'type' => 'paraphraser_request',
                        'tool' => self::PARAPHRASER_TOOL_KEY,
                        'sub_tool_id' => self::PARAPHRASER_SUB_TOOL_ID,
                        'conversation_uuid' => $conversation->uuid,
                        'state' => $mergedState,
                        'source_content' => $this->toNullableString($mergedState['content'] ?? null),
                        'user_instruction' => $this->toNullableString($content),
                    ],
                ]);

                if (! $userMessage || ! isset($userMessage->id)) {
                    return [null, false];
                }

                return [$userMessage, true];
            }, 3);
        });

        /** @var Message|null $userMessage */
        $userMessage = $processed[0] ?? null;
        $wasCreated = (bool) ($processed[1] ?? false);

        if (! $userMessage || ! isset($userMessage->id)) {
            return $this->error('Message could not be saved.');
        }

        $conversation->loadMissing('user.wallet', 'subTool');

        if (! $conversation->user) {
            return $this->error('Conversation user not found.');
        }

        $userMessage->loadMissing('conversation.subTool');
        $this->messageCache->updateAfterMessage($userMessage);
        $this->clearCache($userId);

        $existingAssistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->where('reply_to_message_id', $userMessage->id)
            ->first();

        if ($existingAssistant) {
            $cached = $this->buildParaphraserResponseFromAssistant($existingAssistant, $conversation, $userId);

            return $this->success($cached + ['was_created' => false], 'Paraphraser Response Ready.');
        }

        $endpoint = trim((string) ($conversation->subTool?->endpoint ?? ''));
        if ($endpoint === '') {
            return $this->error('Paraphraser endpoint is not configured.');
        }

        $sourceContent = $this->toNullableString($mergedState['content'] ?? null) ?? $content;
        $composedUserMessage = $this->buildParaphraserInstructionMessage($conversation, $content, $mergedState);

        $payload = [
            'user_id' => $userId,
            'sub_tool_id' => self::PARAPHRASER_SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => $composedUserMessage,
            'content' => $sourceContent,
            'language' => $mergedState['language'],
            'tone' => $mergedState['tone'],
            'rewrite_mode' => $mergedState['rewrite_mode'],
            'change_level' => $mergedState['change_level'],
            'results_count' => $mergedState['results_count'],
            'extra_options' => $mergedState['extra_options'],
            'state' => $mergedState,
            'debug' => (bool) ($data['debug'] ?? false),
        ];

        Log::info('Paraphraser state passed to provider payload.', [
            'conversation_id' => $conversation->id,
            'conversation_uuid' => $conversation->uuid,
            'user_id' => $userId,
            'sub_tool_id' => self::PARAPHRASER_SUB_TOOL_ID,
            'state' => $mergedState,
        ]);

        $providerResponse = $writerService->generateReplyWithUsage($payload, $endpoint);
        $providerResponseArray = is_array($providerResponse) ? $providerResponse : ['reply' => (string) $providerResponse];
        $raw = is_array($providerResponseArray['raw'] ?? null) ? $providerResponseArray['raw'] : $providerResponseArray;

        $results = $this->normalizeParaphraserResults(
            $raw['results']
                ?? ($raw['data']['results'] ?? ($providerResponseArray['results'] ?? []))
        );

        if (count($results) === 0) {
            $fallbackText = $this->extractParaphraserReplyText($providerResponseArray, $raw);

            if ($fallbackText !== '') {
                $results = [
                    [
                        'id' => 1,
                        'text' => $fallbackText,
                    ],
                ];
            }
        }

        $responseMessage = trim((string) ($raw['message'] ?? ''));
        if ($responseMessage === '') {
            $responseMessage = count($results) > 0
                ? 'تمت إعادة صياغة النص بنجاح.'
                : 'لم يتم العثور على نص مُعاد صياغته في الاستجابة.';
        }

        $providerSuccess = ! array_key_exists('success', $raw) || (bool) $raw['success'] === true;
        $provider = $this->toNullableString(($providerResponseArray['provider'] ?? null) ?? ($raw['provider'] ?? null));
        $modelKey = $this->toNullableString(($providerResponseArray['model_key'] ?? null) ?? ($raw['model_key'] ?? null));
        $requestId = $this->toNullableString(($providerResponseArray['request_id'] ?? null) ?? ($raw['request_id'] ?? null));
        $tool = $this->toNullableString(($providerResponseArray['tool'] ?? null) ?? ($raw['tool'] ?? self::PARAPHRASER_TOOL_KEY)) ?? self::PARAPHRASER_TOOL_KEY;

        if (! $providerSuccess) {
            return $this->success([
                'success' => false,
                'tool' => $tool,
                'provider' => $provider,
                'model_key' => $modelKey,
                'user_id' => $userId,
                'sub_tool_id' => self::PARAPHRASER_SUB_TOOL_ID,
                'conversation_uuid' => $conversation->uuid,
                'message' => $responseMessage,
                'state' => $mergedState,
                'results' => $results,
                'count' => count($results),
                'request_id' => $requestId,
                'debug' => null,
            ], 'Paraphraser Response Error.');
        }

        $usage = $this->normalizeUsage($raw['usage'] ?? ($providerResponseArray['usage'] ?? []));
        $cost = $this->normalizeCost($raw['cost'] ?? ($providerResponseArray['cost'] ?? []));
        $tokensToDeduct = $this->getTokensToDeduct([
            'usage' => $usage,
        ]);

        $assistantContent = $this->buildParaphraserOutputFromResults($results);
        $mergedState['last_output'] = $assistantContent !== '' ? $assistantContent : null;
        $walletSnapshot = [
            'balance' => null,
            'payback_balance' => null,
        ];
        $assistantMessage = null;

        DB::transaction(function () use (
            $conversation,
            $tokensToDeduct,
            $usage,
            $cost,
            $assistantContent,
            $userMessage,
            $tool,
            $provider,
            $modelKey,
            $requestId,
            $results,
            $responseMessage,
            $mergedState,
            $sourceContent,
            $content,
            $userId,
            &$walletSnapshot,
            &$assistantMessage
        ): void {
            $walletDetails = $this->deductWalletTokens(
                $conversation->user,
                $tokensToDeduct,
                'paraphraser_ai_usage'
            );

            $walletSnapshot = [
                'balance' => $walletDetails['wallet_after'] ?? null,
                'payback_balance' => $walletDetails['payback_after'] ?? null,
            ];

            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $assistantContent,
                'is_error' => false,
                'reply_to_message_id' => $userMessage->id,
                'metadata' => [
                    'type' => 'result',
                    'tool' => $tool,
                    'provider' => $provider,
                    'model_key' => $modelKey,
                    'request_id' => $requestId,
                    'state' => $mergedState,
                    'source_content' => $sourceContent,
                    'user_instruction' => $this->toNullableString($content),
                    'results' => $results,
                    'message' => $responseMessage,
                    'count' => count($results),
                    'usage' => $usage,
                    'cost' => $cost,
                    'tokens_deducted' => $tokensToDeduct,
                    'sub_tool_id' => self::PARAPHRASER_SUB_TOOL_ID,
                    'conversation_uuid' => $conversation->uuid,
                ],
            ]);

            CostLogger::create([
                'conversation_id' => $conversation->id,
                'user_id' => $userId,
                'sub_tool_id' => self::PARAPHRASER_SUB_TOOL_ID,
                'input_tokens' => (int) ($usage['input_tokens'] ?? 0),
                'output_tokens' => (int) ($usage['output_tokens'] ?? 0),
                'total_tokens' => (int) ($usage['total_tokens'] ?? (($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0))),
                'input_cost' => (float) ($cost['input_cost'] ?? 0),
                'output_cost' => (float) ($cost['output_cost'] ?? 0),
                'web_search_cost' => (float) ($cost['web_search_cost'] ?? 0),
                'total_cost' => (float) ($cost['total_cost'] ?? (($cost['input_cost'] ?? 0) + ($cost['output_cost'] ?? 0) + ($cost['web_search_cost'] ?? 0))),
                'currency' => (string) ($cost['currency'] ?? 'USD'),
                'provider_request_id' => $requestId,
                'model_key' => $modelKey,
            ]);
        });

        if (! $assistantMessage) {
            return $this->error('Assistant message could not be saved.');
        }

        $assistantMessage->setRelation('conversation', $conversation);
        $this->messageCache->updateAfterMessage($assistantMessage);
        $this->clearCache($userId);

        return $this->success([
            'success' => true,
            'tool' => $tool,
            'provider' => $provider,
            'model_key' => $modelKey,
            'user_id' => $userId,
            'sub_tool_id' => self::PARAPHRASER_SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'message' => $responseMessage,
            'state' => $mergedState,
            'results' => $results,
            'count' => count($results),
            'request_id' => $requestId,
            'debug' => null,
            'usage' => $usage,
            'cost' => $cost,
            'wallet' => [
                'balance' => $walletSnapshot['balance'],
                'payback_balance' => $walletSnapshot['payback_balance'],
            ],
            'assistant_message_id' => $assistantMessage->id,
            'was_created' => $wasCreated,
        ], 'Paraphraser Response Ready.');
    }

    protected function buildParaphraserResponseFromAssistant(Message $assistantMessage, Conversation $conversation, int $userId): array
    {
        $metadata = is_array($assistantMessage->metadata ?? null) ? $assistantMessage->metadata : [];
        $results = $this->normalizeParaphraserResults($metadata['results'] ?? []);
        $wallet = Wallet::where('user_id', $userId)->first();

        if (count($results) === 0) {
            $content = trim((string) $assistantMessage->content);

            if ($content !== '') {
                $results = [
                    [
                        'id' => 1,
                        'text' => $content,
                    ],
                ];
            }
        }

        return [
            'success' => true,
            'tool' => (string) ($metadata['tool'] ?? self::PARAPHRASER_TOOL_KEY),
            'provider' => $this->toNullableString($metadata['provider'] ?? null),
            'model_key' => $this->toNullableString($metadata['model_key'] ?? null),
            'user_id' => $userId,
            'sub_tool_id' => self::PARAPHRASER_SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'state' => $this->normalizeParaphraserState($metadata['state'] ?? []),
            'message' => $this->toNullableString($metadata['message'] ?? null) ?? 'تمت إعادة صياغة النص بنجاح.',
            'results' => $results,
            'count' => (int) ($metadata['count'] ?? count($results)),
            'request_id' => $this->toNullableString($metadata['request_id'] ?? null),
            'debug' => null,
            'usage' => $this->normalizeUsage($metadata['usage'] ?? []),
            'cost' => $this->normalizeCost($metadata['cost'] ?? []),
            'wallet' => [
                'balance' => $wallet ? (int) $wallet->balance : null,
                'payback_balance' => $wallet ? (int) ($wallet->payback_balance ?? 0) : null,
            ],
            'assistant_message_id' => $assistantMessage->id,
        ];
    }

    protected function resolveLatestParaphraserState(Conversation $conversation): array
    {
        $latestStateMessage = Message::where('conversation_id', $conversation->id)
            ->whereIn('role', ['user', 'assistant'])
            ->whereNotNull('metadata')
            ->orderByDesc('id')
            ->get()
            ->first(function (Message $message): bool {
                $metadata = is_array($message->metadata ?? null) ? $message->metadata : [];
                $toolKey = strtolower((string) ($metadata['tool'] ?? ''));
                $subToolId = (int) ($metadata['sub_tool_id'] ?? 0);

                return (
                    $toolKey === self::PARAPHRASER_TOOL_KEY
                    || $subToolId === self::PARAPHRASER_SUB_TOOL_ID
                )
                && is_array($metadata['state'] ?? null);
            });

        if (! $latestStateMessage) {
            return $this->normalizeParaphraserState([]);
        }

        $metadata = is_array($latestStateMessage->metadata ?? null) ? $latestStateMessage->metadata : [];

        return $this->normalizeParaphraserState($metadata['state'] ?? []);
    }

    protected function normalizeParaphraserState(mixed $state): array
    {
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            $state = is_array($decoded) ? $decoded : [];
        }

        $state = is_array($state) ? $state : [];

        $base = [
            'content' => null,
            'language' => null,
            'tone' => null,
            'rewrite_mode' => null,
            'change_level' => null,
            'results_count' => 2,
            'extra_options' => [],
            'last_output' => null,
        ];

        $merged = array_merge($base, $state);
        $merged['content'] = $this->toNullableString($merged['content'] ?? null);
        $merged['language'] = $this->toNullableString($merged['language'] ?? null);
        $merged['tone'] = $this->toNullableString($merged['tone'] ?? null);
        $merged['rewrite_mode'] = $this->toNullableString($merged['rewrite_mode'] ?? null);
        $merged['change_level'] = $this->toNullableString($merged['change_level'] ?? null);
        $merged['last_output'] = $this->toNullableString($merged['last_output'] ?? null);

        $resultsCount = $merged['results_count'] ?? null;
        $merged['results_count'] = is_numeric($resultsCount)
            ? max(1, min(10, (int) $resultsCount))
            : 2;

        $extraOptions = $merged['extra_options'] ?? [];
        if (! is_array($extraOptions)) {
            $extraOptions = [];
        }

        $merged['extra_options'] = collect($extraOptions)
            ->map(fn ($item) => $this->toNullableString($item))
            ->filter()
            ->values()
            ->all();

        return $merged;
    }

    protected function mergeParaphraserState(array $oldState, array $newState): array
    {
        $merged = $this->normalizeParaphraserState($oldState);
        $incoming = $this->normalizeParaphraserState($newState);

        foreach ($incoming as $key => $value) {
            if ($key === 'extra_options') {
                if (is_array($value) && count($value) > 0) {
                    $merged[$key] = $value;
                }

                continue;
            }

            if ($value !== null && $value !== '') {
                $merged[$key] = $value;
            }
        }

        return $this->normalizeParaphraserState($merged);
    }

    protected function buildParaphraserInstructionMessage(
        Conversation $conversation,
        string $userInstruction,
        array $state
    ): string {
        $state = $this->normalizeParaphraserState($state);
        $sourceContent = $this->toNullableString($state['content'] ?? null) ?? trim($userInstruction);
        $instructionText = trim($userInstruction);

        $latestAssistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->orderByDesc('id')
            ->first();
        $latestAssistantText = $latestAssistant ? trim((string) $latestAssistant->content) : '';

        if ($instructionText === '' || $instructionText === $sourceContent) {
            return $sourceContent;
        }

        $settingsLines = array_filter([
            $state['language'] ? 'language: '.$state['language'] : null,
            $state['tone'] ? 'tone: '.$state['tone'] : null,
            $state['rewrite_mode'] ? 'rewrite_mode: '.$state['rewrite_mode'] : null,
            $state['change_level'] ? 'change_level: '.$state['change_level'] : null,
            $state['results_count'] ? 'results_count: '.$state['results_count'] : null,
            count($state['extra_options']) > 0 ? 'extra_options: '.implode(', ', $state['extra_options']) : null,
        ]);

        $sections = [
            'Original text:',
            $sourceContent,
            '',
            'User edit instruction:',
            $instructionText,
        ];

        if ($latestAssistantText !== '') {
            $sections[] = '';
            $sections[] = 'Previous paraphraser output context:';
            $sections[] = mb_substr($latestAssistantText, 0, 1200);
        }

        if (count($settingsLines) > 0) {
            $sections[] = '';
            $sections[] = 'Paraphraser settings:';
            foreach ($settingsLines as $line) {
                $sections[] = '- '.$line;
            }
        }

        return implode("\n", $sections);
    }

    protected function extractParaphraserReplyText(mixed $response, mixed $raw = null): string
    {
        $candidates = [
            data_get($response, 'reply'),
            data_get($response, 'content'),
            data_get($response, 'message.content'),
            data_get($response, 'choices.0.message.content'),
            data_get($response, 'data.reply'),
            data_get($response, 'data.content'),
            data_get($raw, 'reply'),
            data_get($raw, 'content'),
            data_get($raw, 'text'),
            data_get($raw, 'message.content'),
            data_get($raw, 'choices.0.message.content'),
            data_get($raw, 'data.reply'),
            data_get($raw, 'data.content'),
            data_get($raw, 'data.text'),
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate)) {
                $text = trim((string) $candidate);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    protected function normalizeParaphraserResults(mixed $results): array
    {
        if (! is_array($results)) {
            return [];
        }

        return collect($results)
            ->map(function ($result, int $index): array {
                $row = is_array($result) ? $result : [];
                $text = trim((string) ($row['text'] ?? (is_scalar($result) ? (string) $result : '')));
                $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : ($index + 1);

                return [
                    'id' => $id,
                    'text' => $text,
                ];
            })
            ->filter(fn (array $result) => $result['text'] !== '')
            ->values()
            ->all();
    }

    protected function buildParaphraserOutputFromResults(array $results): string
    {
        $texts = collect($results)
            ->map(fn (array $result) => trim((string) ($result['text'] ?? '')))
            ->filter()
            ->values()
            ->all();

        return implode("\n\n", $texts);
    }

    protected function buildHeadlineResponseFromAssistant(Message $assistantMessage, Conversation $conversation, int $userId): array
    {
        $metadata = is_array($assistantMessage->metadata ?? null) ? $assistantMessage->metadata : [];
        $headlines = $this->normalizeHeadlines($metadata['headlines'] ?? []);
        $wallet = Wallet::where('user_id', $userId)->first();

        return [
            'success' => true,
            'type' => (string) ($metadata['type'] ?? 'message'),
            'tool' => (string) ($metadata['tool'] ?? 'ai_headline_generator'),
            'provider' => $this->toNullableString($metadata['provider'] ?? null),
            'model_key' => $this->toNullableString($metadata['model_key'] ?? null),
            'user_id' => $userId,
            'sub_tool_id' => self::HEADLINE_GENERATOR_SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'message' => $this->toNullableString($metadata['message'] ?? null) ?? trim((string) $assistantMessage->content),
            'state' => $this->normalizeHeadlineState($metadata['state'] ?? []),
            'headlines' => $headlines,
            'count' => (int) ($metadata['count'] ?? count($headlines)),
            'request_id' => $this->toNullableString($metadata['request_id'] ?? null),
            'debug' => null,
            'usage' => $this->normalizeUsage($metadata['usage'] ?? []),
            'cost' => $this->normalizeCost($metadata['cost'] ?? []),
            'wallet' => [
                'balance' => $wallet ? (int) $wallet->balance : null,
                'payback_balance' => $wallet ? (int) ($wallet->payback_balance ?? 0) : null,
            ],
            'should_reset_state' => (string) ($metadata['type'] ?? 'message') === 'result',
            'assistant_message_id' => $assistantMessage->id,
        ];
    }

    protected function buildAssistantContent(string $type, string $message, array $headlines): string
    {
        $intro = trim($message);

        if ($type !== 'result' || count($headlines) === 0) {
            return $intro;
        }

        $rows = collect($headlines)
            ->map(function (array $headline, int $index): string {
                $number = isset($headline['id']) && is_numeric($headline['id'])
                    ? (int) $headline['id']
                    : ($index + 1);

                $title = trim((string) ($headline['text'] ?? ''));
                $subheadline = trim((string) ($headline['subheadline'] ?? ''));

                if ($subheadline !== '') {
                    return "{$number}. {$title}\n   {$subheadline}";
                }

                return "{$number}. {$title}";
            })
            ->values()
            ->all();

        return trim($intro."\n\n".implode("\n", $rows));
    }

    protected function resolveConversation(array $data, int $userId): ?Conversation
    {
        $conversationId = isset($data['conversation_id']) ? (int) $data['conversation_id'] : 0;
        $conversationUuid = trim((string) ($data['conversation_uuid'] ?? ''));

        $query = Conversation::query()->where('user_id', $userId);

        if ($conversationId > 0) {
            return (clone $query)->where('id', $conversationId)->first();
        }

        if ($conversationUuid !== '') {
            return (clone $query)->where('uuid', $conversationUuid)->first();
        }

        return null;
    }

    protected function resolveInputContent(array $data): string
    {
        return trim((string) (
            $data['user_message']
            ?? $data['message']
            ?? $data['content']
            ?? ''
        ));
    }

    protected function normalizeHeadlineState(mixed $state): array
    {
        $state = is_array($state) ? $state : [];

        $base = [
            'content' => null,
            'content_type' => null,
            'goal' => null,
            'language' => null,
            'tone' => null,
            'number_of_headlines' => null,
            'headline_length' => null,
            'extra_options' => [],
        ];

        $merged = array_merge($base, $state);
        $merged['content'] = $this->toNullableString($merged['content'] ?? null);
        $merged['content_type'] = $this->toNullableString($merged['content_type'] ?? null);
        $merged['goal'] = $this->toNullableString($merged['goal'] ?? null);
        $merged['language'] = $this->toNullableString($merged['language'] ?? null);
        $merged['tone'] = $this->toNullableString($merged['tone'] ?? null);
        $merged['headline_length'] = $this->toNullableString($merged['headline_length'] ?? null);

        $number = $merged['number_of_headlines'] ?? null;
        if (is_numeric($number)) {
            $merged['number_of_headlines'] = max(1, min(20, (int) $number));
        } else {
            $merged['number_of_headlines'] = null;
        }

        $extraOptions = $merged['extra_options'] ?? [];
        if (! is_array($extraOptions)) {
            $extraOptions = [];
        }

        $merged['extra_options'] = collect($extraOptions)
            ->map(fn ($item) => $this->toNullableString($item))
            ->filter()
            ->values()
            ->all();

        return $merged;
    }

    protected function mergeHeadlineState(array $oldState, array $newState): array
    {
        $merged = $this->normalizeHeadlineState($oldState);

        foreach ($newState as $key => $value) {
            if ($key === 'extra_options' && is_array($value)) {
                $merged[$key] = $value;
                continue;
            }

            if ($value !== null && $value !== '') {
                $merged[$key] = $value;
            }
        }

        return $this->normalizeHeadlineState($merged);
    }

    protected function normalizeHeadlines(mixed $headlines): array
    {
        if (! is_array($headlines)) {
            return [];
        }

        return collect($headlines)
            ->map(function ($headline, int $index): array {
                $row = is_array($headline) ? $headline : [];

                $id = isset($row['id']) && is_numeric($row['id']) ? (int) $row['id'] : ($index + 1);

                return [
                    'id' => $id,
                    'text' => trim((string) ($row['text'] ?? '')),
                    'subheadline' => $this->toNullableString($row['subheadline'] ?? null),
                ];
            })
            ->filter(fn (array $headline) => $headline['text'] !== '')
            ->values()
            ->all();
    }

    protected function normalizeUsage(mixed $usage): array
    {
        $usage = is_array($usage) ? $usage : [];

        return [
            'input_tokens' => is_numeric($usage['input_tokens'] ?? null) ? (int) $usage['input_tokens'] : 0,
            'output_tokens' => is_numeric($usage['output_tokens'] ?? null) ? (int) $usage['output_tokens'] : 0,
            'total_tokens' => is_numeric($usage['total_tokens'] ?? null)
                ? (int) $usage['total_tokens']
                : ((is_numeric($usage['input_tokens'] ?? null) ? (int) $usage['input_tokens'] : 0)
                    + (is_numeric($usage['output_tokens'] ?? null) ? (int) $usage['output_tokens'] : 0)),
        ];
    }

    private function getTokensToDeduct(array $aiResponse): int
    {
        $usage = is_array($aiResponse['usage'] ?? null) ? $aiResponse['usage'] : [];

        $total = (int) ($usage['total_tokens'] ?? 0);

        if ($total > 0) {
            return $total;
        }

        return (int) ($usage['input_tokens'] ?? 0) + (int) ($usage['output_tokens'] ?? 0);
    }

    private function deductWalletTokens(User $user, int $tokens, ?string $reason = null): array
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

        $walletBefore = (int) $wallet->balance;
        $paybackBefore = (int) ($wallet->payback_balance ?? 0);

        if ($tokens <= 0) {
            Log::warning('No tokens to deduct from wallet.', [
                'user_id' => $user->id,
                'tokens' => $tokens,
                'reason' => $reason,
            ]);

            return [
                'wallet_before' => $walletBefore,
                'wallet_after' => $walletBefore,
                'payback_before' => $paybackBefore,
                'payback_after' => $paybackBefore,
                'tokens' => 0,
            ];
        }

        if ($walletBefore >= $tokens) {
            $wallet->balance = $walletBefore - $tokens;
        } else {
            $wallet->balance = 0;
            $wallet->payback_balance = $paybackBefore + ($tokens - $walletBefore);
        }

        $wallet->save();
        $wallet->refresh();

        return [
            'wallet_before' => $walletBefore,
            'wallet_after' => (int) $wallet->balance,
            'payback_before' => $paybackBefore,
            'payback_after' => (int) ($wallet->payback_balance ?? 0),
            'tokens' => $tokens,
        ];
    }

    protected function normalizeCost(mixed $cost): array
    {
        $cost = is_array($cost) ? $cost : [];

        $inputCost = $this->toNullableFloat($cost['input_cost'] ?? null) ?? 0.0;
        $outputCost = $this->toNullableFloat($cost['output_cost'] ?? null) ?? 0.0;
        $webSearchCost = $this->toNullableFloat($cost['web_search_cost'] ?? null) ?? 0.0;
        $totalCost = $this->toNullableFloat($cost['total_cost'] ?? null) ?? ($inputCost + $outputCost + $webSearchCost);

        return [
            'input_cost' => $inputCost,
            'output_cost' => $outputCost,
            'web_search_cost' => $webSearchCost,
            'total_cost' => $totalCost,
            'currency' => strtoupper($this->toNullableString($cost['currency'] ?? 'USD') ?? 'USD'),
        ];
    }

    protected function toNullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    protected function toNullableFloat(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    protected function clearCache($userId): void
    {
        try {
            Cache::tags(['conversations', "user_{$userId}"])->flush();
        } catch (\Throwable $th) {
            Log::debug('Conversation tagged cache flush skipped.', [
                'error' => $th->getMessage(),
            ]);
        }
    }

    protected function dispatchAssistantReplyIfNeeded(
        Message $userMessage,
        ?array $taskOptions = null,
        ?array $state = null,
        bool $debug = false
    ): void
    {
        $assistantExists = Message::where('role', 'assistant')
            ->where('reply_to_message_id', $userMessage->id)
            ->exists();

        if ($assistantExists) {
            return;
        }

        $dispatchMarker = GenerateAssistantReplyJob::dispatchMarkerKey($userMessage->id);

        if (! Cache::add($dispatchMarker, true, now()->addMinutes(5))) {
            return;
        }

        if ((int) ($userMessage->conversation?->sub_tool_id ?? 0) === self::PARAPHRASER_SUB_TOOL_ID) {
            Log::info('Paraphraser state passed to GenerateAssistantReplyJob dispatch.', [
                'message_id' => $userMessage->id,
                'conversation_id' => $userMessage->conversation_id,
                'conversation_uuid' => $userMessage->conversation?->uuid,
                'sub_tool_id' => self::PARAPHRASER_SUB_TOOL_ID,
                'state' => $state,
            ]);
        }

        GenerateAssistantReplyJob::dispatch($userMessage->id, $taskOptions, $state, $debug)->afterResponse();
    }

    protected function requestLockKey(int $userId, int $conversationId, string $idempotencyKey): string
    {
        return "message-send:{$userId}:{$conversationId}:{$idempotencyKey}";
    }

    protected function shortTrace(Throwable $th, int $limit = 5): array
    {
        $trace = collect($th->getTrace())
            ->take($limit)
            ->map(function (array $frame): array {
                return [
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                    'function' => $frame['function'] ?? null,
                ];
            })
            ->values()
            ->all();

        array_unshift($trace, [
            'file' => $th->getFile(),
            'line' => $th->getLine(),
            'function' => 'throw',
        ]);

        return $trace;
    }

    protected function normalizeTaskOptions(mixed $taskOptions): ?array
    {
        if (! is_array($taskOptions)) {
            return [
                'search_mode' => 'off',
                'max_tokens' => 1000,
                'temperature' => 0.45,
            ];
        }

        $searchMode = (string) ($taskOptions['search_mode'] ?? 'off');

        if (! in_array($searchMode, ['on', 'off'], true)) {
            $searchMode = 'off';
        }

        $normalized = [
            'search_mode' => $searchMode,
            'max_tokens' => isset($taskOptions['max_tokens'])
                ? (int) $taskOptions['max_tokens']
                : 1000,
            'temperature' => isset($taskOptions['temperature'])
                ? (float) $taskOptions['temperature']
                : 0.45,
        ];

        if ($searchMode === 'on') {
            $normalized['web_search_max_results'] = isset($taskOptions['web_search_max_results'])
                ? (int) $taskOptions['web_search_max_results']
                : 3;

            $normalized['web_search_total_results'] = isset($taskOptions['web_search_total_results'])
                ? (int) $taskOptions['web_search_total_results']
                : 5;
        }

        return $normalized;
    }

    protected function countWords(?string $text): int
    {
        $text = trim((string) $text);

        if ($text === '') {
            return 0;
        }

        preg_match_all('/[\p{Arabic}\p{L}\p{N}]+/u', $text, $matches);

        return count($matches[0] ?? []);
    }
}
