<?php

namespace App\Services;

use App\Exceptions\AiServiceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiArabicWriterService
{
    private const PROVIDER_BUSY_MESSAGE = 'الموديل مشغول مؤقتا بسبب كثرة الطلبات. يرجى المحاولة بعد قليل.';

    private const KEYWORD_GENERATOR_SUB_TOOL_ID = 13;

    private const RESUME_BUILDER_SUB_TOOL_ID = 19;

    private const KEYWORD_GENERATOR_TOOL_KEY = 'ai_keyword_generator';

    private const KEYWORD_GENERATOR_MODEL_KEY = 'keyword_generator';

    protected string $url;

    protected string $apiKey;

    public function __construct()
    {
        $this->url = (string) config('services.aiarabic.url', 'https://api.aiarabic.com');
        $this->apiKey = (string) config('services.aiarabic.key', 'L5W9R2Qx1T7p4Z8Vn6Hj3KcDmBaDsEUy');
    }

    public function generateReply(array $payload, ?string $endpoint = null): string
    {
        $response = $this->generateReplyWithUsage($payload, $endpoint);

        if (is_array($response)) {
            return (string) ($response['reply'] ?? $response['content'] ?? '');
        }

        return (string) $response;
    }


    public function generateReplyWithUsage(array $payload, ?string $endpoint = null): array|string
    {
        if (empty($payload) || ! is_array($payload)) {
            throw new AiServiceException('AI payload body is required.');
        }

        $targetUrl = $this->buildTargetUrl($endpoint);
        $isKeywordGenerator = $this->isKeywordGeneratorPayload($payload);
        $shouldSummarizePayload = $isKeywordGenerator || $this->isSensitivePayload($payload);
        $timeout = $isKeywordGenerator ? 90 : 300;

        Log::debug('AI request started.', [
            'base_url' => $this->url,
            'endpoint' => $endpoint,
            'target_url' => $targetUrl,
            'payload_is_array' => is_array($payload),
            'payload_keys' => array_keys($payload),
        ]);
        Log::info('AI model payload prepared', [
            'base_url' => $this->url,
            'endpoint' => $endpoint,
            'target_url' => $targetUrl,
            'payload_is_array' => is_array($payload),
            'payload_keys_count' => is_array($payload) ? count($payload) : 0,
            'payload_keys' => is_array($payload) ? array_keys($payload) : [],
        ]);

        if ((int) ($payload['sub_tool_id'] ?? 0) === 3) {
            $rawContent = is_scalar($payload['content'] ?? null) ? (string) $payload['content'] : '';
            $rawUserMessage = is_scalar($payload['user_message'] ?? null) ? (string) $payload['user_message'] : '';
            $contentPreviewSource = $rawContent !== '' ? $rawContent : $rawUserMessage;

            $payloadWithoutLargeContent = $payload;
            if (array_key_exists('content', $payloadWithoutLargeContent)) {
                $payloadWithoutLargeContent['content'] = '[content hidden in log, see content_preview/content_length]';
            }
            if (array_key_exists('user_message', $payloadWithoutLargeContent)) {
                $payloadWithoutLargeContent['user_message'] = '[user_message hidden in log, see user_message_preview/user_message_length]';
            }

            Log::info('Paraphraser final AI payload', [
                'endpoint' => $endpoint,
                'target_url' => $targetUrl,
                'sub_tool_id' => 3,
                'conversation_uuid' => $payload['conversation_uuid'] ?? null,
                'user_id' => $payload['user_id'] ?? null,
                'state' => is_array($payload['state'] ?? null) ? $payload['state'] : null,
                'content_preview' => $contentPreviewSource !== '' ? mb_substr($contentPreviewSource, 0, 300) : null,
                'content_length' => $contentPreviewSource !== '' ? mb_strlen($contentPreviewSource) : 0,
                'user_message_preview' => $rawUserMessage !== '' ? mb_substr($rawUserMessage, 0, 300) : null,
                'user_message_length' => $rawUserMessage !== '' ? mb_strlen($rawUserMessage) : 0,
                'final_payload' => $payloadWithoutLargeContent,
            ]);
        }

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'x-internal-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($targetUrl, $payload);
        } catch (ConnectionException $th) {
            Log::error('AI request connection failure.', [
                'base_url' => $this->url,
                'endpoint' => $endpoint,
                'target_url' => $targetUrl,
                'sub_tool_id' => $payload['sub_tool_id'] ?? null,
                'payload' => $shouldSummarizePayload ? $this->summarizePayloadForLog($payload) : $payload,
                'timeout' => $timeout,
                'error_message' => $th->getMessage(),
            ]);

            if ($isKeywordGenerator) {
                return $this->keywordGeneratorConnectionError($payload);
            }

            throw new AiServiceException(
                'AI request transport failure: '.$th->getMessage(),
                [
                    'base_url' => $this->url,
                    'endpoint' => $endpoint,
                    'target_url' => $targetUrl,
                    'payload' => $shouldSummarizePayload ? $this->summarizePayloadForLog($payload) : $payload,
                    'error' => $th->getMessage(),
                ],
                0,
                $th
            );
        } catch (Throwable $th) {
            Log::error('AI request failed before receiving response.', [
                'base_url' => $this->url,
                'endpoint' => $endpoint,
                'target_url' => $targetUrl,
                'payload' => $shouldSummarizePayload ? $this->summarizePayloadForLog($payload) : $payload,
                'error_message' => $th->getMessage(),
                'error_file' => $th->getFile(),
                'error_line' => $th->getLine(),
                'error_trace' => $th->getTraceAsString(),
            ]);

            throw new AiServiceException(
                'AI request transport failure: '.$th->getMessage(),
                [
                    'base_url' => $this->url,
                    'endpoint' => $endpoint,
                    'target_url' => $targetUrl,
                    'payload' => $shouldSummarizePayload ? $this->summarizePayloadForLog($payload) : $payload,
                    'error' => $th->getMessage(),
                ],
                0,
                $th
            );
        }

        $responsePayload = null;

        try {
            $responsePayload = $response->json();
        } catch (Throwable $jsonThrowable) {
            Log::debug('AI response JSON decode skipped.', [
                'base_url' => $this->url,
                'endpoint' => $endpoint,
                'target_url' => $targetUrl,
                'status' => $response->status(),
                'error_message' => $jsonThrowable->getMessage(),
            ]);
        }

        if (! $response->successful()) {
            $status = $response->status();
            $body = $response->body();
            $providerMessage = $this->extractProviderFailureMessage($responsePayload, $body);
            $friendlyMessage = $this->friendlyProviderFailureMessage($status, $providerMessage);
            $requestId = $this->extractScalarString($responsePayload, [
                ['request_id'],
                ['data', 'request_id'],
                ['error', 'metadata', 'provider_request_id'],
                ['data', 'error', 'metadata', 'provider_request_id'],
            ]);

            Log::warning('OpenRouter provider error', [
                'base_url' => $this->url,
                'endpoint' => $endpoint,
                'target_url' => $targetUrl,
                'payload' => $this->summarizePayloadForLog($payload),
                'status' => $status,
                'body' => $body,
                'request_id' => $requestId,
                'response_json' => $responsePayload,
                'provider_message' => $providerMessage,
            ]);

            throw new AiServiceException(
                $friendlyMessage,
                [
                    'base_url' => $this->url,
                    'endpoint' => $endpoint,
                    'target_url' => $targetUrl,
                    'payload' => $shouldSummarizePayload ? $this->summarizePayloadForLog($payload) : $payload,
                    'status' => $status,
                    'response_body' => $body,
                    'response_json' => $responsePayload,
                    'provider_message' => $providerMessage,
                    'friendly_message' => $friendlyMessage,
                    'code' => $status === 429 ? 'AI_RATE_LIMITED' : 'AI_PROVIDER_ERROR',
                    'request_id' => $requestId,
                ],
                $status
            );
        }

        Log::debug('AI request completed.', [
            'base_url' => $this->url,
            'endpoint' => $endpoint,
            'target_url' => $targetUrl,
            'status' => $response->status(),
            'response' => $response->json(),
        ]);

        $responsePayload = is_array($responsePayload) ? $responsePayload : $response->json();
        $content = $this->extractContent($responsePayload);
        $structuredContent = $this->extractStructuredJson($content);
        $declaredType = $this->extractScalarString($responsePayload, [
            ['type'],
            ['data', 'type'],
        ]);
        $declaredSuccess = $responsePayload['success']
            ?? ($responsePayload['data']['success'] ?? true);
        $directResults = $this->extractArray($responsePayload, [
            ['results'],
            ['data', 'results'],
        ]);

        if (
            ($payload['response_format'] ?? null) === 'results'
            && $declaredSuccess !== false
            && ! in_array(strtolower((string) $declaredType), ['question', 'error'], true)
            && ($directResults === null || $directResults === [])
            && $structuredContent === null
        ) {
            throw new AiServiceException('AI returned invalid structured JSON.', [
                'base_url' => $this->url,
                'endpoint' => $endpoint,
                'target_url' => $targetUrl,
                'response_body' => $response->body(),
            ]);
        }

        if ($content === '') {
            Log::error('AI response missing content.', [
                'base_url' => $this->url,
                'endpoint' => $endpoint,
                'target_url' => $targetUrl,
                'payload' => $shouldSummarizePayload ? $this->summarizePayloadForLog($payload) : $payload,
                'body' => $response->body(),
            ]);

            throw new AiServiceException(
                'AI response is empty.',
                [
                    'base_url' => $this->url,
                    'endpoint' => $endpoint,
                    'target_url' => $targetUrl,
                    'payload' => $shouldSummarizePayload ? $this->summarizePayloadForLog($payload) : $payload,
                    'status' => $response->status(),
                    'response_body' => $response->body(),
                ]
            );
        }

        Log::debug('AI response extracted.', [
            'content' => $content,
            'content_length' => mb_strlen($content),
        ]);

        $rawResults = $directResults
            ?? (is_array($structuredContent['results'] ?? null) ? $structuredContent['results'] : []);
        $results = ($payload['normalize_results'] ?? false)
            ? $this->normalizeResults($rawResults)
            : $rawResults;
        $state = $this->extractArray($responsePayload, [
            ['state'],
            ['data', 'state'],
        ]) ?? (is_array($structuredContent['state'] ?? null) ? $structuredContent['state'] : null);
        $count = $responsePayload['count']
            ?? ($responsePayload['data']['count'] ?? null)
            ?? ($structuredContent['count'] ?? null);

        return [
            'reply' => $content,
            'type' => $declaredType ?? ($results !== [] ? 'result' : null),
            'tool' => $this->extractScalarString($responsePayload, [
                ['tool'],
                ['data', 'tool'],
            ]),
            'provider' => $this->extractScalarString($responsePayload, [
                ['provider'],
                ['data', 'provider'],
            ]),
            'model_key' => $this->extractScalarString($responsePayload, [
                ['model_key'],
                ['data', 'model_key'],
            ]),
            'request_id' => $this->extractScalarString($responsePayload, [
                ['request_id'],
                ['data', 'request_id'],
            ]),
            'usage' => $this->extractUsage($responsePayload),
            'cost' => $this->extractCost($responsePayload),
            'state' => $state,
            'headlines' => $this->extractArray($responsePayload, [
                ['headlines'],
                ['data', 'headlines'],
            ]) ?? [],
            'results' => $results,
            'count' => is_numeric($count) ? (int) $count : count($results),
            'raw' => is_array($responsePayload) ? $responsePayload : null,
        ];
    }

    protected function extractContent(mixed $payload): string
    {
        if (is_string($payload)) {
            return trim($payload);
        }

        if (! is_array($payload)) {
            return '';
        }

        $type = strtolower(trim((string) (
            $payload['type']
            ?? ($payload['data']['type'] ?? '')
        )));
        $results = $payload['results'] ?? ($payload['data']['results'] ?? []);

        if ($type === 'result' && is_array($results) && $results !== []) {
            $resultContent = $this->formatResultsAsText($results);

            if ($resultContent !== '') {
                return $resultContent;
            }
        }

        $paths = [
            ['reply'],
            ['message'],
            ['content'],
            ['response'],
            ['result'],
            ['output'],
            ['text'],

            ['data', 'reply'],
            ['data', 'message'],
            ['data', 'content'],
            ['data', 'response'],
            ['data', 'result'],
            ['data', 'output'],
            ['data', 'text'],

            ['message', 'content'],
            ['choices', 0, 'message', 'content'],
            ['choices', 0, 'text'],
        ];

        foreach ($paths as $path) {
            $value = $payload;

            foreach ($path as $segment) {
                if (! is_array($value) || ! array_key_exists($segment, $value)) {
                    $value = null;
                    break;
                }

                $value = $value[$segment];
            }

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        if (($payload['type'] ?? null) === 'result' && ! empty($payload['headlines']) && is_array($payload['headlines'])) {
            return $this->formatHeadlinesAsText($payload);
        }

        if (! empty($payload['headlines']) && is_array($payload['headlines'])) {
            return $this->formatHeadlinesAsText($payload);
        }

        if (is_array($results) && ! empty($results)) {
            return $this->formatResultsAsText($results);
        }

        return '';
    }

    protected function extractUsage(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $usage = $payload['usage'] ?? ($payload['data']['usage'] ?? []);

        if (! is_array($usage)) {
            return [];
        }

        $normalized = [
            'input_tokens' => isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : null,
            'output_tokens' => isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : null,
            'total_tokens' => isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : null,
        ];

        if (
            $normalized['input_tokens'] === null
            && $normalized['output_tokens'] === null
            && $normalized['total_tokens'] === null
        ) {
            return [];
        }

        return $normalized;
    }

    protected function extractCost(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $cost = $payload['cost'] ?? ($payload['data']['cost'] ?? []);

        if (! is_array($cost)) {
            return [];
        }

        $normalized = [
            'input_cost' => $this->normalizeDecimal($cost['input_cost'] ?? null),
            'output_cost' => $this->normalizeDecimal($cost['output_cost'] ?? null),
            'web_search_cost' => $this->normalizeDecimal($cost['web_search_cost'] ?? null),
            'total_cost' => $this->normalizeDecimal($cost['total_cost'] ?? null),
            'currency' => isset($cost['currency']) && is_scalar($cost['currency'])
                ? strtoupper(trim((string) $cost['currency']))
                : null,
        ];

        $hasAnyCostValue = $normalized['input_cost'] !== null
            || $normalized['output_cost'] !== null
            || $normalized['web_search_cost'] !== null
            || $normalized['total_cost'] !== null;

        if (! $hasAnyCostValue && $normalized['currency'] === null) {
            return [];
        }

        return array_filter(
            $normalized,
            static fn ($value) => $value !== null && $value !== ''
        );
    }

    protected function formatHeadlinesAsText(array $payload): string
    {
        $message = isset($payload['message']) && is_string($payload['message'])
            ? trim($payload['message'])
            : 'تم توليد العناوين بنجاح.';

        $headlines = $payload['headlines'] ?? [];

        if (! is_array($headlines) || empty($headlines)) {
            return $message;
        }

        $lines = [$message, ''];

        foreach ($headlines as $index => $headline) {
            if (! is_array($headline)) {
                continue;
            }

            $number = $headline['id'] ?? ($index + 1);
            $text = trim((string) ($headline['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $lines[] = $number.'. '.$text;

            if (isset($headline['subheadline']) && is_string($headline['subheadline']) && trim($headline['subheadline']) !== '') {
                $lines[] = '   '.trim($headline['subheadline']);
            }
        }

        return trim(implode("\n", $lines));
    }

    protected function formatResultsAsText(array $results): string
    {
        return collect($results)
            ->map(function ($result): string {
                if (is_array($result)) {
                    return trim((string) ($result['text'] ?? ''));
                }

                return is_scalar($result) ? trim((string) $result) : '';
            })
            ->filter()
            ->implode("\n\n");
    }

    protected function extractStructuredJson(string $content): ?array
    {
        $content = trim($content);

        if ($content === '') {
            return null;
        }

        $content = preg_replace('/^```(?:json)?\s*/i', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/', '', $content) ?? $content;
        $decoded = json_decode(trim($content), true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function normalizeResults(mixed $results): array
    {
        if (! is_array($results)) {
            return [];
        }

        $normalized = [];

        foreach ($results as $index => $result) {
            if (is_string($result)) {
                $nested = $this->extractStructuredJson($result);

                if (is_array($nested['results'] ?? null)) {
                    array_push($normalized, ...$this->normalizeResults($nested['results']));
                    continue;
                }

                $text = trim($result);
                if ($text === '') {
                    continue;
                }

                $result = ['text' => $text];
            }

            if (! is_array($result)) {
                continue;
            }

            $text = is_scalar($result['text'] ?? null)
                ? trim((string) $result['text'])
                : '';

            if ($text === '') {
                continue;
            }

            $normalized[] = [
                'id' => $result['id'] ?? ($index + 1),
                'text' => $text,
                'title' => isset($result['title']) && is_scalar($result['title'])
                    ? (string) $result['title']
                    : null,
                'subject' => isset($result['subject']) && is_scalar($result['subject'])
                    ? (string) $result['subject']
                    : null,
                'meta' => is_array($result['meta'] ?? null) ? $result['meta'] : [],
            ];
        }

        return array_values($normalized);
    }

    protected function extractArray(mixed $payload, array $paths): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        foreach ($paths as $path) {
            $value = $payload;

            foreach ($path as $segment) {
                if (! is_array($value) || ! array_key_exists($segment, $value)) {
                    $value = null;
                    break;
                }

                $value = $value[$segment];
            }

            if (is_array($value)) {
                return $value;
            }
        }

        return null;
    }

    protected function normalizeDecimal(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    protected function extractScalarString(mixed $payload, array $paths): ?string
    {
        if (! is_array($payload)) {
            return null;
        }

        foreach ($paths as $path) {
            $value = $payload;

            foreach ($path as $segment) {
                if (! is_array($value) || ! array_key_exists($segment, $value)) {
                    $value = null;
                    break;
                }

                $value = $value[$segment];
            }

            if (is_scalar($value)) {
                $text = trim((string) $value);

                if ($text !== '') {
                    return $text;
                }
            }
        }

        return null;
    }

    protected function extractProviderFailureMessage(mixed $payload, string $body): ?string
    {
        $message = $this->extractScalarString($payload, [
            ['message'],
            ['detail'],
            ['error'],
            ['error', 'message'],
            ['data', 'message'],
            ['data', 'detail'],
            ['data', 'error'],
            ['data', 'error', 'message'],
        ]);

        if ($message !== null) {
            return $message;
        }

        $body = trim($body);

        if ($body === '') {
            return null;
        }

        return mb_substr($body, 0, 1000);
    }

    protected function friendlyProviderFailureMessage(int $status, ?string $providerMessage = null): string
    {
        $message = strtolower((string) $providerMessage);

        if (
            $status === 429
            || str_contains($message, '429')
            || str_contains($message, 'rate limit')
            || str_contains($message, 'rate-limited')
            || str_contains($message, 'too many requests')
        ) {
            return self::PROVIDER_BUSY_MESSAGE;
        }

        if (
            str_contains($message, 'openrouter')
            || str_contains($message, 'provider returned error')
            || str_contains($message, 'provider error')
        ) {
            return 'A temporary error occurred while connecting to the AI provider. Please try again.';
        }

        return 'Could not complete the AI request right now. Please try again.';
    }

    protected function buildTargetUrl(?string $endpoint): string
    {
        $baseUrl = rtrim($this->url, '/');
        $endpoint = trim((string) $endpoint);

        if ($endpoint === '') {
            throw new AiServiceException('AI endpoint is missing.', [
                'base_url' => $baseUrl,
                'endpoint' => $endpoint,
            ]);
        }

        $endpoint = '/'.ltrim($endpoint, '/');

        return $baseUrl.$endpoint;
    }

    protected function isKeywordGeneratorPayload(array $payload): bool
    {
        return (int) ($payload['sub_tool_id'] ?? 0) === self::KEYWORD_GENERATOR_SUB_TOOL_ID
            || strtolower((string) ($payload['tool'] ?? $payload['tool_key'] ?? '')) === self::KEYWORD_GENERATOR_TOOL_KEY
            || strtolower((string) ($payload['model_key'] ?? '')) === self::KEYWORD_GENERATOR_MODEL_KEY;
    }

    protected function isSensitivePayload(array $payload): bool
    {
        return (int) ($payload['sub_tool_id'] ?? 0) === self::RESUME_BUILDER_SUB_TOOL_ID
            || strtolower((string) ($payload['tool'] ?? $payload['tool_key'] ?? '')) === 'resume_builder'
            || strtolower((string) ($payload['model_key'] ?? '')) === 'resume_builder'
            || (bool) ($payload['sensitive'] ?? false);
    }

    protected function keywordGeneratorConnectionError(array $payload): array
    {
        $message = 'تعذر الاتصال بخدمة توليد الكلمات المفتاحية. حاول مرة أخرى.';
        $state = is_array($payload['state'] ?? null) ? $payload['state'] : null;

        if (is_array($state)) {
            $state['last_output'] = null;
        }

        return [
            'reply' => $message,
            'message' => $message,
            'type' => 'error',
            'success' => false,
            'tool' => self::KEYWORD_GENERATOR_TOOL_KEY,
            'model_key' => self::KEYWORD_GENERATOR_MODEL_KEY,
            'sub_tool_id' => self::KEYWORD_GENERATOR_SUB_TOOL_ID,
            'retryable' => true,
            'state' => $state,
            'results' => [],
            'count' => 0,
            'raw' => [
                'success' => false,
                'type' => 'error',
                'tool' => self::KEYWORD_GENERATOR_TOOL_KEY,
                'model_key' => self::KEYWORD_GENERATOR_MODEL_KEY,
                'sub_tool_id' => self::KEYWORD_GENERATOR_SUB_TOOL_ID,
                'retryable' => true,
                'message' => $message,
            ],
        ];
    }

    protected function summarizePayloadForLog(array $payload): array
    {
        $summary = $payload;

        foreach (['body', 'content', 'user_message', 'previous_output', 'extracted_resume_text'] as $key) {
            if (! is_scalar($summary[$key] ?? null)) {
                continue;
            }

            $value = (string) $summary[$key];
            $summary[$key] = [
                'preview' => mb_substr($value, 0, 300),
                'length' => mb_strlen($value),
            ];
        }

        if (is_array($summary['state'] ?? null) && isset($summary['state']['last_output'])) {
            $summary['state']['last_output'] = '[hidden]';
        }

        return $summary;
    }
}
