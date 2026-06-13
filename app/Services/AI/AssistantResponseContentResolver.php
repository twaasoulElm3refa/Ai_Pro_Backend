<?php

namespace App\Services\AI;

class AssistantResponseContentResolver
{
    private const PROMPT_GENERATOR_TOOL = 'ai_prompt_generator';

    private const PROMPT_GENERATOR_SUB_TOOL_ID = 9;

    private const STATUS_MESSAGES = [
        'prompt generated successfully',
        'prompt enhanced successfully',
        'تم توليد البرومبت بنجاح',
        'generated successfully',
        'success',
    ];

    public function sanitize(array|string $response): array|string
    {
        if (is_string($response)) {
            return $this->sanitizeText($response);
        }

        foreach ($response as $key => $value) {
            if (is_string($value)) {
                $response[$key] = $this->sanitizeText($value);
            } elseif (is_array($value)) {
                $response[$key] = $this->sanitize($value);
            }
        }

        return $response;
    }

    public function resolve(array|string $response, int $subToolId, array $config = []): string
    {
        $response = $this->sanitize($response);

        if (! is_array($response)) {
            return trim($response);
        }

        if (! $this->usesResultContent($response, $subToolId, $config)) {
            return trim((string) ($response['reply'] ?? $response['content'] ?? ''));
        }

        $layers = $this->payloadLayers($response);
        $type = strtolower($this->firstString($layers, 'type'));

        if ($type === 'question') {
            return $this->firstString($layers, 'message')
                ?: trim((string) ($response['reply'] ?? $response['content'] ?? ''));
        }

        $resultTexts = $this->extractResultTexts($layers);

        if ($resultTexts !== []) {
            return implode("\n\n", $resultTexts);
        }

        $lastOutput = $this->extractLastOutput($layers);

        if ($lastOutput !== '') {
            return $lastOutput;
        }

        $fallback = trim((string) ($response['reply'] ?? $response['content'] ?? ''));

        return $this->isStatusMessage($fallback) ? '' : $fallback;
    }

    private function usesResultContent(array $response, int $subToolId, array $config): bool
    {
        if (($config['response_format'] ?? null) === 'results') {
            return true;
        }

        if ($subToolId === self::PROMPT_GENERATOR_SUB_TOOL_ID) {
            return true;
        }

        foreach ($this->payloadLayers($response) as $payload) {
            if (($payload['tool'] ?? $payload['tool_key'] ?? null) === self::PROMPT_GENERATOR_TOOL) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function payloadLayers(array $response): array
    {
        $layers = [$response];
        $raw = $response['raw'] ?? null;

        if (is_array($raw)) {
            $layers[] = $raw;

            if (is_array($raw['data'] ?? null)) {
                $layers[] = $raw['data'];
            }
        }

        if (is_array($response['data'] ?? null)) {
            $layers[] = $response['data'];
        }

        return $layers;
    }

    /**
     * @param  array<int, array<string, mixed>>  $layers
     * @return array<int, string>
     */
    private function extractResultTexts(array $layers): array
    {
        foreach ($layers as $payload) {
            if (! is_array($payload['results'] ?? null)) {
                continue;
            }

            $texts = [];

            foreach ($payload['results'] as $result) {
                if (! is_array($result) || ! is_string($result['text'] ?? null)) {
                    continue;
                }

                array_push($texts, ...$this->extractTextValue($result['text']));
            }

            $texts = array_values(array_filter(
                $texts,
                fn (string $text): bool => $text !== '' && ! $this->isStatusMessage($text)
            ));

            if ($texts !== []) {
                return $texts;
            }
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function extractTextValue(string $text): array
    {
        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded) || ! is_array($decoded['results'] ?? null)) {
            return [$text];
        }

        $texts = [];

        foreach ($decoded['results'] as $result) {
            if (is_array($result) && is_string($result['text'] ?? null)) {
                $innerText = trim($result['text']);

                if ($innerText !== '') {
                    $texts[] = $innerText;
                }
            }
        }

        return $texts;
    }

    /**
     * @param  array<int, array<string, mixed>>  $layers
     */
    private function extractLastOutput(array $layers): string
    {
        foreach ($layers as $payload) {
            $state = $payload['state'] ?? null;
            $lastOutput = is_array($state) ? ($state['last_output'] ?? null) : null;

            if (! is_string($lastOutput)) {
                continue;
            }

            $texts = $this->extractTextValue($lastOutput);
            $text = implode("\n\n", array_filter(
                $texts,
                fn (string $value): bool => ! $this->isStatusMessage($value)
            ));

            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    /**
     * @param  array<int, array<string, mixed>>  $layers
     */
    private function firstString(array $layers, string $key): string
    {
        foreach ($layers as $payload) {
            if (is_string($payload[$key] ?? null) && trim($payload[$key]) !== '') {
                return trim($payload[$key]);
            }
        }

        return '';
    }

    private function isStatusMessage(string $message): bool
    {
        $normalized = mb_strtolower(trim($message));
        $normalized = preg_replace('/[\s.!؟]+$/u', '', $normalized) ?? $normalized;

        return in_array($normalized, self::STATUS_MESSAGES, true);
    }

    private function sanitizeText(string $text): string
    {
        $controlCharacters = '\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}\x{007F}';
        $text = preg_replace(
            "/(?<=[\p{L}\p{N}])[{$controlCharacters}]+(?=[\p{L}\p{N}])/u",
            '-',
            $text
        ) ?? $text;

        return trim(preg_replace("/[{$controlCharacters}]+/u", ' ', $text) ?? $text);
    }
}
