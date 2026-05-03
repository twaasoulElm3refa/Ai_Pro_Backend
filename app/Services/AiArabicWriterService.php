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
        $this->url = (string) config('services.aiarabic.url', 'https://api.aiarabic.com/tasks/writer');
        $this->apiKey = (string) config('services.aiarabic.key', 'test123');
    }

    public function generateReply(array $payload): string
    {
        Log::debug('AI request started.', [
            'url' => $this->url,
            'payload' => $payload,
        ]);

        try {
            $response = Http::timeout(45)
                ->withHeaders([
                    'x-internal-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($this->url, $payload);
        } catch (Throwable $th) {
            Log::error('AI request failed before receiving response.', [
                'url' => $this->url,
                'payload' => $payload,
                'error_message' => $th->getMessage(),
                'error_file' => $th->getFile(),
                'error_line' => $th->getLine(),
                'error_trace' => $th->getTraceAsString(),
            ]);

            throw new AiServiceException(
                'AI request transport failure: '.$th->getMessage(),
                [
                    'url' => $this->url,
                    'payload' => $payload,
                    'error' => $th->getMessage(),
                ],
                0,
                $th
            );
        }

        if (! $response->successful()) {
            Log::error('AI request returned non-success status.', [
                'url' => $this->url,
                'payload' => $payload,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new AiServiceException(
                'AI request failed with status '.$response->status(),
                [
                    'url' => $this->url,
                    'payload' => $payload,
                    'status' => $response->status(),
                    'response_body' => $response->body(),
                ]
            );
        }

        Log::debug('AI request completed.', [
            'url' => $this->url,
            'status' => $response->status(),
            'response' => $response->json(),
        ]);

        $content = $this->extractContent($response->json());

        if ($content === '') {
            Log::error('AI response missing content.', [
                'url' => $this->url,
                'payload' => $payload,
                'body' => $response->body(),
            ]);

            throw new AiServiceException(
                'AI response is empty.',
                [
                    'url' => $this->url,
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

        return $content;
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
            ['content'],
            ['response'],
            ['result'],
            ['output'],
            ['text'],
            ['data', 'content'],
            ['data', 'response'],
            ['data', 'result'],
            ['data', 'output'],
            ['data', 'text'],
            ['message', 'content'],
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
}
