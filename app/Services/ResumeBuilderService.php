<?php

namespace App\Services;

use App\Exceptions\AiServiceException;
use App\Models\Conversation;
use App\Models\CostLogger;
use App\Models\Message;
use App\Models\ResumeGeneratedFile;
use App\Models\User;
use App\Models\Wallet;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Smalot\PdfParser\Parser as PdfParser;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class ResumeBuilderService
{
    public const SUB_TOOL_ID = 19;
    public const TOOL_KEY = 'resume_builder';
    public const RESPONSE_TOOL = 'resume_builder_ai';
    public const MODEL_KEY = 'resume_builder';

    private const DEFAULT_ENDPOINT = 'tasks/resume-builder/chat';
    private const MAX_EXTRACTED_TEXT_LENGTH = 80000;

    public function __construct(
        private readonly AiArabicWriterService $writerService,
        private readonly ConversationMessageCacheService $messageCache
    ) {}

    public function handle(Conversation $conversation, array $data, ?UploadedFile $file, int $userId): array
    {
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? '')) ?: (string) Str::uuid();
        $lock = Cache::lock("resume-builder-handle:{$userId}:{$conversation->id}:{$idempotencyKey}", 120);

        return $lock->block(30, fn (): array => $this->handleLocked(
            $conversation,
            $data,
            $file,
            $userId,
            $idempotencyKey
        ));
    }

    private function handleLocked(
        Conversation $conversation,
        array $data,
        ?UploadedFile $file,
        int $userId,
        string $idempotencyKey
    ): array
    {
        if ((int) $conversation->user_id !== $userId) {
            abort(403, 'Conversation does not belong to the authenticated user.');
        }

        $state = $this->normalizeState(is_array($data['state'] ?? null) ? $data['state'] : []);
        $userMessageText = trim((string) ($data['user_message'] ?? $data['content'] ?? ''));

        if ($userMessageText === '') {
            $userMessageText = $this->defaultUserMessage($state);
        }

        $existingUserMessage = Message::where('conversation_id', $conversation->id)
            ->where('role', 'user')
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existingUserMessage) {
            $existingAssistant = Message::where('conversation_id', $conversation->id)
                ->where('role', 'assistant')
                ->where('reply_to_message_id', $existingUserMessage->id)
                ->first();

            if ($existingAssistant) {
                return $this->responseFromAssistant($existingAssistant, $conversation, $userId);
            }
        }

        $uploadInfo = $this->storeAndExtractUpload($file);
        $requestPayload = $this->requestPayload($conversation, $state, $userMessageText, $idempotencyKey, $uploadInfo);
        $userMessage = $this->storeUserMessage(
            $conversation,
            $userMessageText,
            $idempotencyKey,
            [
                'type' => 'resume_builder_request',
                'tool' => self::TOOL_KEY,
                'tool_key' => self::TOOL_KEY,
                'model_key' => self::MODEL_KEY,
                'sub_tool_id' => self::SUB_TOOL_ID,
                'conversation_uuid' => $conversation->uuid,
                'state' => $state,
                'request_payload' => $requestPayload,
                'uploaded_file' => $uploadInfo['metadata'],
            ]
        );

        $conversation->loadMissing('user.wallet', 'subTool');
        $existingAssistant = Message::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->where('reply_to_message_id', $userMessage->id)
            ->first();

        if ($existingAssistant) {
            return $this->responseFromAssistant($existingAssistant, $conversation, $userId);
        }

        $endpoint = $this->resolveEndpoint($conversation);
        $providerPayload = $this->buildProviderPayload($conversation, $requestPayload, $state, $uploadInfo['text']);
        $providerResponse = $this->writerService->generateReplyWithUsage($providerPayload, $endpoint);
        $providerResponse = is_array($providerResponse)
            ? $providerResponse
            : ['reply' => (string) $providerResponse];

        if ($this->providerResponseIsError($providerResponse)) {
            throw new AiServiceException(
                $this->providerErrorMessage($providerResponse),
                [
                    'endpoint' => $endpoint,
                    'tool' => self::TOOL_KEY,
                    'model_key' => self::MODEL_KEY,
                    'sub_tool_id' => self::SUB_TOOL_ID,
                    'provider_response' => $this->summarizeProviderResponse($providerResponse),
                ]
            );
        }

        $rawOutput = $this->extractRawOutput($providerResponse);
        $normalizedResults = $this->normalizeResults($providerResponse, $rawOutput);

        if ($normalizedResults === []) {
            $fallbackText = $this->cleanOutputText($rawOutput);
            if ($fallbackText === '') {
                throw ValidationException::withMessages([
                    'results' => ['The resume builder response could not be formatted.'],
                ]);
            }

            $normalizedResults = [[
                'id' => 1,
                'text' => $fallbackText,
                'title' => 'Improved resume',
                'subject' => 'Resume Builder',
                'meta' => [],
            ]];
        }

        $resumeText = $this->resultsText($normalizedResults);
        $providerFile = $this->normalizeProviderFile($providerResponse['file'] ?? data_get($providerResponse, 'raw.file'));
        $generatedOutput = $this->generateOutput($resumeText, $state, $providerFile);
        $fileMetadata = $this->publicFileMetadata($generatedOutput);
        $normalizedResults = $this->mergeFileMetadataIntoResults(
            $normalizedResults,
            $fileMetadata,
            $state,
            [
                'original_filename' => $uploadInfo['metadata']['original_filename'] ?? null,
                'sections' => $state['sections_to_include'],
            ]
        );

        $providerState = is_array($providerResponse['state'] ?? null)
            ? $providerResponse['state']
            : (is_array(data_get($providerResponse, 'raw.state')) ? data_get($providerResponse, 'raw.state') : []);
        $responseState = $this->normalizeState(array_replace($state, $providerState));
        $responseState['last_output'] = $this->toNullableString($responseState['last_output'] ?? null) ?: $resumeText;
        $usage = $this->normalizeUsage($providerResponse['usage'] ?? data_get($providerResponse, 'raw.usage', []));
        $cost = $this->normalizeCost($providerResponse['cost'] ?? data_get($providerResponse, 'raw.cost', []));
        $tokensToDeduct = (int) ($usage['total_tokens'] ?? 0);
        $requestId = $this->toNullableString($providerResponse['request_id'] ?? data_get($providerResponse, 'raw.request_id'));
        $provider = $this->toNullableString($providerResponse['provider'] ?? data_get($providerResponse, 'raw.provider')) ?? 'openrouter';
        $modelKey = $this->toNullableString($providerResponse['model_key'] ?? data_get($providerResponse, 'raw.model_key')) ?? self::MODEL_KEY;
        $responseTool = $this->toNullableString($providerResponse['tool'] ?? data_get($providerResponse, 'raw.tool')) ?? self::RESPONSE_TOOL;
        $responseMessage = $this->toNullableString($providerResponse['message'] ?? data_get($providerResponse, 'raw.message'))
            ?? 'Resume generated successfully.';

        $assistantMessage = null;
        $walletSnapshot = ['balance' => null, 'payback_balance' => null];
        DB::transaction(function () use (
            $conversation,
            $provider,
            $modelKey,
            $responseTool,
            $responseMessage,
            $requestId,
            $usage,
            $cost,
            $tokensToDeduct,
            $userMessage,
            $requestPayload,
            $responseState,
            $normalizedResults,
            $fileMetadata,
            $generatedOutput,
            $rawOutput,
            $userId,
            &$assistantMessage,
            &$walletSnapshot
        ): void {
            $assistantMessage = Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $this->resultsText($normalizedResults),
                'is_error' => false,
                'reply_to_message_id' => $userMessage->id,
                'metadata' => [
                    'type' => 'result',
                    'tool' => $responseTool,
                    'tool_key' => self::TOOL_KEY,
                    'model_key' => $modelKey,
                    'provider' => $provider,
                    'request_id' => $requestId,
                    'sub_tool_id' => self::SUB_TOOL_ID,
                    'conversation_uuid' => $conversation->uuid,
                    'message' => $responseMessage,
                    'state' => $responseState,
                    'request_payload' => $requestPayload,
                    'results' => $normalizedResults,
                    'normalized_results' => $normalizedResults,
                    'file' => $fileMetadata,
                    'raw_output' => $rawOutput,
                    'usage' => $usage,
                    'cost' => $cost,
                    'tokens_deducted' => $tokensToDeduct,
                ],
            ]);

            if ($fileMetadata !== [] && $generatedOutput !== []) {
                ResumeGeneratedFile::updateOrCreate(
                    ['file_id' => $fileMetadata['file_id']],
                    [
                        'user_id' => $userId,
                        'conversation_id' => $conversation->id,
                        'message_id' => $assistantMessage->id,
                        'conversation_uuid' => $conversation->uuid,
                        'sub_tool_id' => self::SUB_TOOL_ID,
                        'filename' => $fileMetadata['filename'],
                        'path' => $generatedOutput['path'],
                        'disk' => $generatedOutput['disk'] ?? 'local',
                        'content_type' => $fileMetadata['content_type'] ?? null,
                        'output_format' => $fileMetadata['output_format'] ?? null,
                        'size' => $generatedOutput['size'] ?? null,
                        'metadata' => [
                            'request_id' => $requestId,
                            'provider' => $provider,
                            'model_key' => $modelKey,
                        ],
                    ]
                );
            }

            $walletSnapshot = $conversation->user
                ? $this->deductWalletTokens($conversation->user, $tokensToDeduct)
                : $this->walletSnapshot($userId, true);

            if ($tokensToDeduct > 0 || array_sum(array_map('floatval', [
                $cost['input_cost'] ?? 0,
                $cost['output_cost'] ?? 0,
                $cost['web_search_cost'] ?? 0,
                $cost['total_cost'] ?? 0,
            ])) > 0) {
                CostLogger::create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $userId,
                    'sub_tool_id' => self::SUB_TOOL_ID,
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
            throw new \RuntimeException('Assistant message could not be saved.');
        }

        $this->messageCache->updateAfterMessage($assistantMessage);
        $this->clearCache($userId);

        return $this->responseFromAssistant($assistantMessage, $conversation, $userId, $walletSnapshot);
    }

    private function requestPayload(
        Conversation $conversation,
        array $state,
        string $userMessage,
        string $idempotencyKey,
        array $uploadInfo
    ): array {
        return [
            'user_id' => (int) $conversation->user_id,
            'sub_tool_id' => self::SUB_TOOL_ID,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => $userMessage,
            'content' => $userMessage,
            'tool' => self::TOOL_KEY,
            'tool_key' => self::TOOL_KEY,
            'model_key' => self::MODEL_KEY,
            'state' => $state,
            'debug' => false,
            'idempotency_key' => $idempotencyKey,
            'uploaded_file' => $uploadInfo['metadata'],
        ];
    }

    private function buildProviderPayload(Conversation $conversation, array $requestPayload, array $state, string $extractedText): array
    {
        return [
            'user_id' => $requestPayload['user_id'],
            'sub_tool_id' => self::SUB_TOOL_ID,
            'title' => 'Resume Builder',
            'conversation_uuid' => $conversation->uuid,
            'body' => $this->buildUserPrompt($requestPayload['user_message'], $state, $extractedText),
            'user_message' => $requestPayload['user_message'],
            'content' => $requestPayload['content'],
            'extracted_resume_text' => $extractedText,
            'tool' => self::TOOL_KEY,
            'tool_key' => self::TOOL_KEY,
            'model_key' => self::MODEL_KEY,
            'state' => $state,
            'instruction' => $this->instruction(),
            'system_prompt' => $this->systemPrompt(),
            'response_format' => 'results',
            'normalize_results' => true,
            'sensitive' => true,
            'debug' => false,
        ];
    }

    private function buildUserPrompt(string $userMessage, array $state, string $extractedText): string
    {
        $lines = [
            'User message:',
            $userMessage,
            '',
            'Resume options:',
        ];

        foreach ($state as $key => $value) {
            if ($key === 'last_output' || $value === null || $value === '' || $value === []) {
                continue;
            }

            $lines[] = '- '.$key.': '.(is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value);
        }

        $lines[] = '';
        $lines[] = 'Uploaded resume text:';
        $lines[] = $extractedText !== '' ? $extractedText : '[No uploaded resume. Build from the provided user details and add placeholders for missing information.]';
        $lines[] = '';
        $lines[] = $this->instruction();

        return implode("\n", $lines);
    }

    private function instruction(): string
    {
        return implode(' ', [
            'Improve or build a resume for the target role.',
            'Keep it honest.',
            'Do not invent jobs, companies, degrees, certifications, dates, or achievements.',
            'Use strong action verbs.',
            'Improve clarity and ATS formatting.',
            'If information is missing, add placeholders or suggestions instead of fake facts.',
        ]);
    }

    private function defaultUserMessage(array $state): string
    {
        $targetRole = $this->toNullableString($state['target_role'] ?? null) ?: 'the target role';

        return "Improve this resume for {$targetRole}.";
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Return valid JSON only.
Do not include markdown fences.
You are a professional resume builder and resume improver.
Use the uploaded resume text when provided.
Never invent jobs, companies, schools, degrees, certifications, dates, metrics, or achievements.
If a useful fact is missing, add a bracketed placeholder or a suggestion.
Use ATS-friendly formatting, clear sections, concise bullets, and strong action verbs.
Follow the requested language, tone, experience level, resume style, output format, sections, and extra options.

Use this exact schema:
{
  "results": [
    {
      "id": 1,
      "text": "Improved resume text here",
      "title": "Improved resume",
      "subject": "Resume Builder",
      "meta": {}
    }
  ]
}
PROMPT;
    }

    private function storeAndExtractUpload(?UploadedFile $file): array
    {
        if (! $file) {
            return [
                'text' => '',
                'path' => null,
                'metadata' => [
                    'original_filename' => null,
                    'mime_type' => null,
                    'extension' => null,
                    'size' => null,
                    'uploaded' => false,
                ],
            ];
        }

        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension()));

        if (! in_array($extension, ['pdf', 'doc', 'docx'], true)) {
            throw ValidationException::withMessages([
                'file' => ['Please upload a PDF, DOC, or DOCX resume file.'],
            ]);
        }

        $path = $file->store('resume_uploads', 'local');
        $absolutePath = Storage::disk('local')->path($path);
        $text = $this->extractTextFromFile($absolutePath, $extension);

        if ($text === '') {
            throw ValidationException::withMessages([
                'file' => ['Could not extract text from this file.'],
            ]);
        }

        return [
            'text' => $text,
            'path' => $path,
            'metadata' => [
                'original_filename' => $this->sanitizeFilename($file->getClientOriginalName()),
                'mime_type' => $file->getClientMimeType(),
                'extension' => $extension,
                'size' => $file->getSize(),
                'uploaded' => true,
            ],
        ];
    }

    private function extractTextFromFile(string $path, string $extension): string
    {
        try {
            $text = match ($extension) {
                'pdf' => (new PdfParser)->parseFile($path)->getText(),
                'docx' => $this->extractDocxText($path),
                'doc' => throw new HttpException(415, 'DOC files are not supported yet. Please upload PDF or DOCX.'),
                default => throw ValidationException::withMessages([
                    'file' => ['Please upload a PDF, DOC, or DOCX resume file.'],
                ]),
            };
        } catch (HttpException $th) {
            throw $th;
        } catch (ValidationException $th) {
            throw $th;
        } catch (Throwable $th) {
            Log::warning('Resume text extraction failed.', [
                'extension' => $extension,
                'message' => $th->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'file' => ['Could not extract text from this file.'],
            ]);
        }

        return $this->sanitizeExtractedText($text);
    }

    private function extractDocxText(string $path): string
    {
        $phpWord = IOFactory::load($path);
        $parts = [];

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                array_push($parts, ...$this->extractElementText($element));
            }
        }

        return implode("\n", array_filter(array_map('trim', $parts)));
    }

    private function extractElementText(mixed $element): array
    {
        $parts = [];

        if (is_object($element) && method_exists($element, 'getText')) {
            $text = $element->getText();
            if (is_scalar($text) && trim((string) $text) !== '') {
                $parts[] = trim((string) $text);
            }
        }

        if (is_object($element) && method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                array_push($parts, ...$this->extractElementText($child));
            }
        }

        if (is_object($element) && method_exists($element, 'getRows')) {
            foreach ($element->getRows() as $row) {
                if (! method_exists($row, 'getCells')) {
                    continue;
                }

                foreach ($row->getCells() as $cell) {
                    if (! method_exists($cell, 'getElements')) {
                        continue;
                    }

                    foreach ($cell->getElements() as $child) {
                        array_push($parts, ...$this->extractElementText($child));
                    }
                }
            }
        }

        return $parts;
    }

    private function generateOutput(string $text, array $state, array $providerFile = []): array
    {
        $format = strtolower((string) ($state['output_format'] ?? 'docx'));

        if ($format === 'text') {
            return [
                'output_format' => 'text',
            ];
        }

        return match ($format) {
            'pdf' => $this->generatePdfOutput($text, $state, $providerFile),
            default => $this->generateDocxOutput($text, $state, $providerFile),
        };
    }

    private function generateDocxOutput(string $text, array $state, array $providerFile = []): array
    {
        $fileId = (string) Str::uuid();
        $filename = $this->outputFilename($state, $providerFile, 'docx', $fileId);
        $path = 'resume_outputs/'.$filename;
        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);
        $section = $phpWord->addSection();

        foreach ($this->splitResumeLines($text) as $line) {
            if ($this->looksLikeHeading($line)) {
                $section->addText($line, ['bold' => true, 'size' => 14], ['spaceAfter' => 120]);
                continue;
            }

            $section->addText($line, [], ['spaceAfter' => 80]);
        }

        Storage::disk('local')->makeDirectory('resume_outputs');
        $absolutePath = Storage::disk('local')->path($path);
        IOFactory::createWriter($phpWord, 'Word2007')->save($absolutePath);

        return [
            'file_id' => $fileId,
            'filename' => $filename,
            'path' => $path,
            'disk' => 'local',
            'content_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'download_url' => $this->downloadUrlForFileId($fileId),
            'file_url' => $this->downloadUrlForFileId($fileId),
            'output_format' => 'docx',
            'size' => Storage::disk('local')->size($path),
        ];
    }

    private function generatePdfOutput(string $text, array $state, array $providerFile = []): array
    {
        $fileId = (string) Str::uuid();
        $filename = $this->outputFilename($state, $providerFile, 'pdf', $fileId);
        $path = 'resume_outputs/'.$filename;
        $options = new DompdfOptions;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,Arial,sans-serif;font-size:12px;line-height:1.55;color:#111827;} h1,h2,h3{margin:0 0 8px;} p{margin:0 0 8px;white-space:pre-wrap;}</style></head><body><p>'
            .nl2br(e($text), false)
            .'</p></body></html>';

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();
        Storage::disk('local')->put($path, $dompdf->output());

        return [
            'file_id' => $fileId,
            'filename' => $filename,
            'path' => $path,
            'disk' => 'local',
            'content_type' => 'application/pdf',
            'download_url' => $this->downloadUrlForFileId($fileId),
            'file_url' => $this->downloadUrlForFileId($fileId),
            'output_format' => 'pdf',
            'size' => Storage::disk('local')->size($path),
        ];
    }

    private function responseFromAssistant(
        Message $assistantMessage,
        Conversation $conversation,
        int $userId,
        ?array $wallet = null
    ): array
    {
        $metadata = is_array($assistantMessage->metadata ?? null) ? $assistantMessage->metadata : [];
        $state = $this->normalizeState(is_array($metadata['state'] ?? null) ? $metadata['state'] : []);
        $results = $this->normalizeResultItems($metadata['normalized_results'] ?? $metadata['results'] ?? []);
        $file = is_array($metadata['file'] ?? null) ? $metadata['file'] : [];
        $usage = $this->normalizeUsage($metadata['usage'] ?? []);
        $cost = $this->normalizeCost($metadata['cost'] ?? []);

        return [
            'success' => true,
            'type' => 'result',
            'sub_tool_id' => self::SUB_TOOL_ID,
            'tool' => (string) ($metadata['tool'] ?? self::RESPONSE_TOOL),
            'tool_key' => (string) ($metadata['tool_key'] ?? self::TOOL_KEY),
            'provider' => (string) ($metadata['provider'] ?? 'openrouter'),
            'model_key' => (string) ($metadata['model_key'] ?? self::MODEL_KEY),
            'user_id' => $userId,
            'conversation_uuid' => $conversation->uuid,
            'message_id' => $assistantMessage->id,
            'assistant_message_id' => $assistantMessage->id,
            'request_id' => $this->toNullableString($metadata['request_id'] ?? null),
            'state' => $state,
            'results' => $results,
            'normalized_results' => $results,
            'count' => count($results),
            'file' => $file,
            'message' => $this->toNullableString($metadata['message'] ?? null) ?? 'Resume generated successfully.',
            'request_payload' => is_array($metadata['request_payload'] ?? null) ? $metadata['request_payload'] : null,
            'usage' => $usage,
            'cost' => $cost,
            'tokens_deducted' => (int) ($metadata['tokens_deducted'] ?? ($usage['total_tokens'] ?? 0)),
            'wallet' => $wallet ?? $this->walletSnapshot($userId),
        ];
    }

    private function storeUserMessage(Conversation $conversation, string $content, string $idempotencyKey, array $metadata): Message
    {
        $lock = Cache::lock("resume-message-send:{$conversation->user_id}:{$conversation->id}:{$idempotencyKey}", 15);

        $message = $lock->block(5, function () use ($conversation, $content, $idempotencyKey, $metadata) {
            return DB::transaction(function () use ($conversation, $content, $idempotencyKey, $metadata) {
                $existing = Message::where('conversation_id', $conversation->id)
                    ->where('role', 'user')
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    return $existing;
                }

                return Message::create([
                    'conversation_id' => $conversation->id,
                    'role' => 'user',
                    'content' => $content,
                    'idempotency_key' => $idempotencyKey,
                    'is_error' => false,
                    'metadata' => $metadata,
                ]);
            }, 3);
        });

        $this->messageCache->updateAfterMessage($message);
        $this->clearCache((int) $conversation->user_id);

        return $message;
    }

    private function normalizeState(array $state): array
    {
        $base = [
            'target_role' => 'Senior Laravel Developer',
            'candidate_name' => null,
            'language' => 'English',
            'tone' => 'Professional',
            'experience_level' => 'Senior',
            'resume_style' => 'ATS-friendly modern',
            'output_format' => 'docx',
            'sections_to_include' => ['Summary', 'Skills', 'Experience', 'Education', 'Certifications', 'Projects', 'Languages'],
            'extra_options' => ['Improve clarity', 'Use strong action verbs', 'Keep it honest', 'Do not invent experience'],
            'last_output' => null,
        ];

        $merged = array_replace($base, $state);
        $merged['target_role'] = $this->toNullableString($merged['target_role'] ?? null) ?: $base['target_role'];
        $merged['candidate_name'] = $this->toNullableString($merged['candidate_name'] ?? null);
        $merged['language'] = $this->toNullableString($merged['language'] ?? null) ?: $base['language'];
        $merged['tone'] = $this->toNullableString($merged['tone'] ?? null) ?: $base['tone'];
        $merged['experience_level'] = $this->toNullableString($merged['experience_level'] ?? null) ?: $base['experience_level'];
        $merged['resume_style'] = $this->toNullableString($merged['resume_style'] ?? null) ?: $base['resume_style'];
        $outputFormat = strtolower($this->toNullableString($merged['output_format'] ?? null) ?: $base['output_format']);
        $merged['output_format'] = in_array($outputFormat, ['docx', 'pdf', 'text'], true) ? $outputFormat : 'docx';
        $merged['sections_to_include'] = $this->normalizeStringList($merged['sections_to_include'] ?? []) ?: $base['sections_to_include'];
        $merged['extra_options'] = $this->normalizeStringList($merged['extra_options'] ?? []) ?: $base['extra_options'];
        $merged['last_output'] = $this->toNullableString($merged['last_output'] ?? null);

        return $merged;
    }

    private function normalizeResults(mixed $providerResponse, string $rawOutput): array
    {
        $candidates = [
            data_get($providerResponse, 'normalized_results'),
            data_get($providerResponse, 'results'),
            data_get($providerResponse, 'raw.normalized_results'),
            data_get($providerResponse, 'raw.results'),
            data_get($providerResponse, 'raw.data.normalized_results'),
            data_get($providerResponse, 'raw.data.results'),
            $this->decodeJsonLike($rawOutput),
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeResultItems($this->extractResults($candidate));

            if ($normalized !== []) {
                return $normalized;
            }
        }

        return [];
    }

    private function normalizeResultItems(mixed $results): array
    {
        if (! is_array($results)) {
            return [];
        }

        $normalized = [];

        foreach ($results as $index => $result) {
            if (is_string($result)) {
                $decoded = $this->decodeJsonLike($result);
                if (is_array($decoded)) {
                    array_push($normalized, ...$this->normalizeResultItems($this->extractResults($decoded)));
                    continue;
                }

                $result = ['text' => $result];
            }

            if (! is_array($result)) {
                continue;
            }

            $text = $this->cleanOutputText($result['text'] ?? $result['content'] ?? $result['output'] ?? '');
            if ($text === '') {
                continue;
            }

            $normalized[] = [
                'id' => is_numeric($result['id'] ?? null) ? (int) $result['id'] : $index + 1,
                'text' => $text,
                'title' => $this->toNullableString($result['title'] ?? null) ?: 'Improved resume',
                'subject' => $this->toNullableString($result['subject'] ?? null),
                'meta' => is_array($result['meta'] ?? null) ? $result['meta'] : [],
            ];
        }

        return array_values($normalized);
    }

    private function extractResults(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        if (is_array($value['results'] ?? null)) {
            return $value['results'];
        }

        if (is_array($value['normalized_results'] ?? null)) {
            return $value['normalized_results'];
        }

        if (array_is_list($value)) {
            return $value;
        }

        return [];
    }

    private function normalizeProviderFile(mixed $file): array
    {
        if (! is_array($file)) {
            return [];
        }

        return [
            'file_id' => $this->toNullableString($file['file_id'] ?? null),
            'filename' => $this->sanitizeFilename($file['filename'] ?? null),
            'content_type' => $this->toNullableString($file['content_type'] ?? null),
            'download_url' => $this->toNullableString($file['download_url'] ?? null),
            'file_url' => $this->toNullableString($file['file_url'] ?? null),
        ];
    }

    private function publicFileMetadata(array $generatedOutput): array
    {
        if (! $this->toNullableString($generatedOutput['file_id'] ?? null)) {
            return [];
        }

        return [
            'file_id' => $generatedOutput['file_id'],
            'filename' => $generatedOutput['filename'] ?? null,
            'content_type' => $generatedOutput['content_type'] ?? null,
            'download_url' => $generatedOutput['download_url'] ?? null,
            'file_url' => $generatedOutput['file_url'] ?? null,
            'output_format' => $generatedOutput['output_format'] ?? null,
        ];
    }

    private function mergeFileMetadataIntoResults(array $results, array $file, array $state, array $extraMeta = []): array
    {
        if ($results === []) {
            return [];
        }

        $outputFormat = $this->toNullableString($state['output_format'] ?? null);

        return array_values(array_map(function (array $result) use ($file, $extraMeta, $outputFormat): array {
            $meta = is_array($result['meta'] ?? null) ? $result['meta'] : [];
            $fallbackFileMeta = array_filter([
                'file_id' => $file['file_id'] ?? null,
                'download_url' => $file['download_url'] ?? null,
                'file_url' => $file['file_url'] ?? null,
                'filename' => $file['filename'] ?? null,
                'content_type' => $file['content_type'] ?? null,
                'output_format' => $file['output_format'] ?? $outputFormat,
            ], fn ($value): bool => $value !== null && $value !== '');

            $result['meta'] = array_filter(
                array_merge($extraMeta, $meta, $fallbackFileMeta),
                fn ($value): bool => $value !== null && $value !== ''
            );

            return $result;
        }, $results));
    }

    private function outputFilename(array $state, array $providerFile, string $extension, string $fileId): string
    {
        $providerFilename = $this->sanitizeFilename($providerFile['filename'] ?? null);

        if ($providerFilename && strtolower(pathinfo($providerFilename, PATHINFO_EXTENSION)) === $extension) {
            return $providerFilename;
        }

        $name = $this->toNullableString($state['candidate_name'] ?? null)
            ?: $this->toNullableString($state['target_role'] ?? null)
            ?: 'resume';
        $slug = Str::slug($name, '_') ?: 'resume';

        return "{$slug}_resume_{$fileId}.{$extension}";
    }

    private function downloadUrlForFileId(string $fileId): string
    {
        return "/tasks/resume-builder/download/{$fileId}";
    }

    private function generatePdfFallbackMessage(): string
    {
        return 'PDF output could not be generated. Please choose DOCX or text.';
    }

    private function splitResumeLines(string $text): array
    {
        $lines = preg_split('/\R/u', trim($text)) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $line): bool => $line !== ''));
    }

    private function looksLikeHeading(string $line): bool
    {
        $clean = trim($line, " \t\n\r\0\x0B#:");

        return mb_strlen($clean) <= 60
            && ! str_contains($clean, '.')
            && preg_match('/^[\p{L}\p{N}\s&\/+-]+$/u', $clean) === 1
            && ($line === strtoupper($line) || str_starts_with(trim($line), '#'));
    }

    private function temporaryDownloadUrl(string $filename): string
    {
        return URL::temporarySignedRoute(
            'resume-builder.download',
            now()->addHour(),
            ['filename' => $filename]
        );
    }

    private function resolveEndpoint(Conversation $conversation): string
    {
        $conversation->loadMissing('subTool');
        $config = is_array($conversation->subTool?->config ?? null) ? $conversation->subTool->config : [];

        return trim((string) ($config['endpoint'] ?? ''))
            ?: (trim((string) ($conversation->subTool?->endpoint ?? '')) ?: self::DEFAULT_ENDPOINT);
    }

    private function providerResponseIsError(array $providerResponse): bool
    {
        $raw = is_array($providerResponse['raw'] ?? null) ? $providerResponse['raw'] : [];
        $success = $providerResponse['success']
            ?? ($raw['success'] ?? ($raw['data']['success'] ?? true));
        $type = strtolower(trim((string) (
            $providerResponse['type']
            ?? ($raw['type'] ?? ($raw['data']['type'] ?? ''))
        )));

        return $success === false || $type === 'error';
    }

    private function providerErrorMessage(array $providerResponse): string
    {
        $message = $this->toNullableString(
            $providerResponse['message']
            ?? ($providerResponse['reply'] ?? null)
            ?? data_get($providerResponse, 'raw.message')
            ?? data_get($providerResponse, 'raw.data.message')
            ?? data_get($providerResponse, 'raw.error')
            ?? data_get($providerResponse, 'raw.data.error')
        );

        return $message ?: 'Could not improve the resume right now. Please try again.';
    }

    private function summarizeProviderResponse(array $providerResponse): array
    {
        unset($providerResponse['extracted_resume_text']);

        return $providerResponse;
    }

    private function extractRawOutput(mixed $providerResponse): string
    {
        $candidates = [
            data_get($providerResponse, 'reply'),
            data_get($providerResponse, 'message'),
            data_get($providerResponse, 'content'),
            data_get($providerResponse, 'raw.reply'),
            data_get($providerResponse, 'raw.message'),
            data_get($providerResponse, 'raw.content'),
            data_get($providerResponse, 'raw.data.reply'),
            data_get($providerResponse, 'raw.data.message'),
            data_get($providerResponse, 'raw.data.content'),
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                return trim((string) $candidate);
            }
        }

        return '';
    }

    private function decodeJsonLike(string $value): mixed
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/^```(?:json)?\s*/iu', '', $value) ?? $value;
        $value = preg_replace('/\s*```$/u', '', $value) ?? $value;
        $decoded = json_decode(trim($value), true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    private function cleanOutputText(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $text = trim((string) $value);
        $text = preg_replace('/^```(?:json|markdown)?\s*/iu', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/u', '', $text) ?? $text;

        if (! str_contains($text, "\n") && preg_match('/\\\\[nr]/u', $text) === 1) {
            $text = str_replace(['\\r\\n', '\\n', '\\r'], "\n", $text);
        }

        return trim(str_replace('\\"', '"', $text));
    }

    private function sanitizeExtractedText(string $text): string
    {
        $text = preg_replace('/[^\P{C}\r\n\t]+/u', ' ', $text) ?? $text;
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\R{3,}/u", "\n\n", $text) ?? $text;

        return trim(mb_substr($text, 0, self::MAX_EXTRACTED_TEXT_LENGTH));
    }

    private function sanitizeFilename(?string $filename): ?string
    {
        $filename = trim((string) $filename);

        return $filename === '' ? null : mb_substr(basename($filename), 0, 255);
    }

    private function normalizeStringList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\r?\n|,/u', $value) ?: [];
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn ($item) => trim((string) $item), $value),
            fn ($item) => $item !== ''
        ));
    }

    private function resultsText(array $results): string
    {
        return collect($results)
            ->map(fn (array $result) => trim((string) ($result['text'] ?? '')))
            ->filter()
            ->implode("\n\n");
    }

    private function normalizeUsage(mixed $usage): array
    {
        $usage = is_array($usage) ? $usage : [];
        $input = (int) ($usage['input_tokens'] ?? 0);
        $output = (int) ($usage['output_tokens'] ?? 0);
        $total = (int) ($usage['total_tokens'] ?? ($input + $output));

        return [
            'input_tokens' => $input,
            'output_tokens' => $output,
            'total_tokens' => $total,
        ];
    }

    private function normalizeCost(mixed $cost): array
    {
        $cost = is_array($cost) ? $cost : [];

        return [
            'input_cost' => (float) ($cost['input_cost'] ?? 0),
            'output_cost' => (float) ($cost['output_cost'] ?? 0),
            'web_search_cost' => (float) ($cost['web_search_cost'] ?? 0),
            'total_cost' => (float) ($cost['total_cost'] ?? 0),
            'currency' => strtoupper(trim((string) ($cost['currency'] ?? 'USD')) ?: 'USD'),
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

        if ($tokens > 0) {
            $balance = (int) $wallet->balance;
            $payback = (int) ($wallet->payback_balance ?? 0);

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

    private function walletSnapshot(int $userId, bool $lock = false): array
    {
        $query = Wallet::where('user_id', $userId);
        if ($lock) {
            $query->lockForUpdate();
        }

        $wallet = $query->first();

        return [
            'balance' => $wallet ? (int) $wallet->balance : null,
            'payback_balance' => $wallet ? (int) ($wallet->payback_balance ?? 0) : null,
        ];
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

    private function toNullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
