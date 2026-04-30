<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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
        $response = Http::timeout(45)
            ->withHeaders([
                'x-internal-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->post($this->url, $payload);

        if (! $response->successful()) {
            Log::warning('AI Arabic writer request failed.', [
                'url' => $this->url,
                'status' => $response->status(),
                'body' => $response->body(),
                'payload_meta' => [
                    'keys' => array_keys($payload),
                    'user_id' => $payload['user_id'] ?? null,
                    'sub_tool_id' => $payload['sub_tool_id'] ?? null,
                    'title_length' => isset($payload['title']) ? mb_strlen((string) $payload['title']) : null,
                    'conversation_uuid' => $payload['conversation_uuid'] ?? null,
                    'body_length' => isset($payload['body']) ? mb_strlen((string) $payload['body']) : null,
                    'user_message_length' => isset($payload['user_message']) ? mb_strlen((string) $payload['user_message']) : null,
                ],
            ]);

            throw new RuntimeException('AI writer request failed.');
        }

        $content = $this->extractContent($response->json());

        if ($content === '') {
            Log::warning('AI Arabic writer response did not include content.', [
                'body' => $response->body(),
            ]);

            throw new RuntimeException('AI writer response is empty.');
        }

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
