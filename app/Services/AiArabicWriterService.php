<?php

namespace App\Services;

use App\Exceptions\AiServiceException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiArabicWriterService
{
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
        $targetUrl = $this->buildTargetUrl($endpoint);

        Log::debug('AI request started.', [
            'base_url' => $this->url,
            'endpoint' => $endpoint,
            'target_url' => $targetUrl,
            'payload' => $payload,
        ]);
        Log::info('AI model payload prepared', [
            'base_url' => $this->url,
            'endpoint' => $endpoint,
            'target_url' => $targetUrl,
            'payload' => $payload,
        ]);

        try {
            $response = Http::timeout(300)
                ->withHeaders([
                    'x-internal-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($targetUrl, $payload);
        } catch (Throwable $th) {
            Log::error('AI request failed before receiving response.', [
                'base_url' => $this->url,
                'endpoint' => $endpoint,
                'target_url' => $targetUrl,
                'payload' => $payload,
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
                    'payload' => $payload,
                    'error' => $th->getMessage(),
                ],
                0,
                $th
            );
        }

        if (! $response->successful()) {
            Log::error('AI request returned non-success status.', [
                'base_url' => $this->url,
                'endpoint' => $endpoint,
                'target_url' => $targetUrl,
                'payload' => $payload,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new AiServiceException(
                'AI request failed with status '.$response->status(),
                [
                    'base_url' => $this->url,
                    'endpoint' => $endpoint,
                    'target_url' => $targetUrl,
                    'payload' => $payload,
                    'status' => $response->status(),
                    'response_body' => $response->body(),
                ]
            );
        }

        Log::debug('AI request completed.', [
            'base_url' => $this->url,
            'endpoint' => $endpoint,
            'target_url' => $targetUrl,
            'status' => $response->status(),
            'response' => $response->json(),
        ]);

        $responsePayload = $response->json();
        $content = $this->extractContent($responsePayload);

        if ($content === '') {
            Log::error('AI response missing content.', [
                'base_url' => $this->url,
                'endpoint' => $endpoint,
                'target_url' => $targetUrl,
                'payload' => $payload,
                'body' => $response->body(),
            ]);

            throw new AiServiceException(
                'AI response is empty.',
                [
                    'base_url' => $this->url,
                    'endpoint' => $endpoint,
                    'target_url' => $targetUrl,
                    'payload' => $payload,
                    'status' => $response->status(),
                    'response_body' => $response->body(),
                ]
            );
        }

        Log::debug('AI response extracted.', [
            'content' => $content,
            'content_length' => mb_strlen($content),
        ]);

        return [
            'reply' => $content,
            'usage' => $this->extractUsage($responsePayload),
            'cost' => $this->extractCost($responsePayload),
            'request_id' => $this->extractScalarString($responsePayload, [
                ['request_id'],
                ['data', 'request_id'],
            ]),
            'model_key' => $this->extractScalarString($responsePayload, [
                ['model_key'],
                ['data', 'model_key'],
            ]),
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

        $paths = [
            ['reply'],
            ['content'],
            ['response'],
            ['result'],
            ['output'],
            ['text'],

            ['data', 'reply'],
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

        return '';
    }

    protected function extractUsage(mixed $payload): array
    {
        if (! is_array($payload)) {
            return [];
        }

        $usage = $payload['usage'] ?? [];

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
}
