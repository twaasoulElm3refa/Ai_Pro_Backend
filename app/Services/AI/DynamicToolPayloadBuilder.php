<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Arr;

class DynamicToolPayloadBuilder
{
    private const KEYWORD_GENERATOR_SUB_TOOL_ID = 13;

    private const KEYWORD_GENERATOR_TOOL_KEY = 'ai_keyword_generator';

    private const KEYWORD_GENERATOR_MODEL_KEY = 'keyword_generator';

    public function __construct(
        private readonly AIPayloadBuilder $basePayloadBuilder,
        private readonly DynamicToolConfigService $configService
    ) {}

    public function build(
        Conversation $conversation,
        Message $latestUserMessage,
        ?array $requestState = null,
        bool $debug = false
    ): array {
        $conversation->loadMissing('subTool');
        $subTool = $conversation->subTool;
        $config = $this->configService->configFor($subTool);
        $userMessage = trim((string) $latestUserMessage->content);
        $isKeywordGenerator = $this->isKeywordGenerator($conversation, $config);

        if ($isKeywordGenerator) {
            $userMessage = $this->cleanKeywordText($userMessage, 2000);
        }

        $messageMetadata = is_array($latestUserMessage->metadata ?? null)
            ? $latestUserMessage->metadata
            : [];
        $state = $this->mergeState(
            $this->configService->defaultState($config),
            is_array($requestState) ? $requestState : [],
            (string) ($config['default_state_mode'] ?? '')
        );
        $state = $this->applyStateExtractors($state, $config, [
            'user_message' => $userMessage,
            'state' => $state,
        ]);

        if ($isKeywordGenerator) {
            $state['last_output'] = null;
        }

        $payload = $this->basePayloadBuilder->build($conversation, $latestUserMessage);
        $payload['user_id'] = (int) $conversation->user_id;
        $payload['sub_tool_id'] = (int) $conversation->sub_tool_id;
        $payload['conversation_uuid'] = (string) $conversation->uuid;
        $payload['user_message'] = $userMessage;
        $payload['state'] = $state;
        $payload['debug'] = $debug;

        if (($messageMetadata['regenerate'] ?? false) === true) {
            $payload['regenerate'] = true;
        }

        if (is_string($messageMetadata['previous_output'] ?? null)) {
            $previousOutput = $isKeywordGenerator
                ? $this->compactKeywordPreviousOutput($messageMetadata['previous_output'])
                : trim($messageMetadata['previous_output']);
            if ($previousOutput !== '') {
                $payload['previous_output'] = $previousOutput;
            }
        }

        $optionalFields = [
            'tool_key' => 'tool',
            'model_key' => 'model_key',
            'provider' => 'provider',
            'system_prompt' => 'system_prompt',
            'response_format' => 'response_format',
            'normalize_results' => 'normalize_results',
        ];

        foreach ($optionalFields as $configKey => $payloadKey) {
            if (array_key_exists($configKey, $config) && $config[$configKey] !== null && $config[$configKey] !== '') {
                $payload[$payloadKey] = $config[$configKey];
            }
        }

        $context = $payload;
        $context['config'] = $config;
        foreach ($this->payloadMap($config) as $target => $source) {
            $value = $this->mappedValue($source, $context);
            if ($value !== $this->missingValue()) {
                Arr::set($payload, $target, $value);
            }
        }

        if ($isKeywordGenerator) {
            $payload['body'] = $this->cleanKeywordText((string) ($payload['user_message'] ?? $userMessage), 2000);
            $payload['user_message'] = $payload['body'];

            if (is_array($payload['state'] ?? null)) {
                $payload['state']['last_output'] = null;
            }
        }

        return [
            'payload' => $payload,
            'endpoint' => $subTool
                ? $this->configService->endpointFor($subTool, $config)
                : '',
            'config' => $config,
        ];
    }

    private function payloadMap(array $config): array
    {
        return is_array($config['payload_map'] ?? null)
            ? $config['payload_map']
            : [];
    }

    private function mergeState(array $defaults, array $requested, string $mode): array
    {
        $state = array_replace($defaults, $requested);

        if (! in_array($mode, ['null', 'null_or_empty'], true)) {
            return $state;
        }

        foreach ($defaults as $field => $defaultValue) {
            if (! array_key_exists($field, $requested)) {
                continue;
            }

            $requestedValue = $requested[$field];
            $useDefault = $requestedValue === null
                || (
                    $mode === 'null_or_empty'
                    && ($requestedValue === '' || $requestedValue === [])
                );

            if ($useDefault) {
                $state[$field] = $defaultValue;
            }
        }

        return $state;
    }

    private function applyStateExtractors(array $state, array $config, array $context): array
    {
        $extractors = is_array($config['state_extractors'] ?? null)
            ? $config['state_extractors']
            : [];

        foreach ($extractors as $field => $definition) {
            if (! is_string($field) || $field === '' || ! is_array($definition)) {
                continue;
            }

            $currentValue = $state[$field] ?? null;
            if (! ($definition['overwrite'] ?? false) && ! $this->isEmptyStateValue($currentValue)) {
                continue;
            }

            $source = $definition['source'] ?? null;
            if (! is_string($source) || $source === '') {
                continue;
            }

            $value = data_get($context, $source);
            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);
            $prefixes = is_array($definition['strip_prefixes'] ?? null)
                ? $definition['strip_prefixes']
                : [];

            foreach ($prefixes as $prefix) {
                if (! is_string($prefix) || trim($prefix) === '') {
                    continue;
                }

                $prefix = trim($prefix);
                if (mb_strtolower(mb_substr($value, 0, mb_strlen($prefix))) === mb_strtolower($prefix)) {
                    $value = ltrim(mb_substr($value, mb_strlen($prefix)), " \t\n\r\0\x0B:-");
                    break;
                }
            }

            if ($value !== '') {
                $state[$field] = $value;
                $context['state'] = $state;
            }
        }

        return $state;
    }

    private function isEmptyStateValue(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function isKeywordGenerator(Conversation $conversation, array $config): bool
    {
        return (int) ($conversation->sub_tool_id ?? 0) === self::KEYWORD_GENERATOR_SUB_TOOL_ID
            || strtolower((string) ($config['tool_key'] ?? '')) === self::KEYWORD_GENERATOR_TOOL_KEY
            || strtolower((string) ($config['model_key'] ?? '')) === self::KEYWORD_GENERATOR_MODEL_KEY;
    }

    private function cleanKeywordText(string $value, int $limit): string
    {
        $value = preg_replace('/^```(?:json)?\s*/iu', '', trim($value)) ?? $value;
        $value = preg_replace('/\s*```$/u', '', $value) ?? $value;
        $value = preg_replace('/`+/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? $value;

        return mb_substr(trim($value), 0, $limit);
    }

    private function compactKeywordPreviousOutput(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $texts = $this->extractKeywordTexts($this->decodeJsonLike($value));

        if ($texts === []) {
            if ($this->looksLikeJson($value)) {
                return '';
            }

            return $this->cleanKeywordText($value, 3000);
        }

        $texts = array_values(array_unique(array_filter(array_map(
            fn (string $text): string => $this->cleanKeywordText($text, 300),
            $texts
        ))));

        return mb_substr(implode("\n", $texts), 0, 3000);
    }

    private function decodeJsonLike(string $value): mixed
    {
        $value = preg_replace('/^```(?:json)?\s*/iu', '', trim($value)) ?? $value;
        $value = preg_replace('/\s*```$/u', '', $value) ?? $value;
        $decoded = json_decode(trim($value), true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /**
     * @return array<int, string>
     */
    private function extractKeywordTexts(mixed $value): array
    {
        if (is_string($value)) {
            return trim($value) === '' ? [] : [trim($value)];
        }

        if (! is_array($value)) {
            return [];
        }

        $texts = [];

        if (isset($value['text']) && is_scalar($value['text'])) {
            $texts[] = trim((string) $value['text']);
        }

        foreach (['results', 'normalized_results', 'keywords', 'data'] as $key) {
            if (is_array($value[$key] ?? null)) {
                array_push($texts, ...$this->extractKeywordTexts($value[$key]));
            }
        }

        if (array_is_list($value)) {
            foreach ($value as $item) {
                array_push($texts, ...$this->extractKeywordTexts($item));
            }
        }

        return array_values(array_filter($texts));
    }

    private function looksLikeJson(string $value): bool
    {
        $trimmed = ltrim($value);

        return str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[');
    }

    private function mappedValue(mixed $mapping, array $context): mixed
    {
        if (is_string($mapping)) {
            return data_get($context, $mapping, $this->missingValue());
        }

        if (! is_array($mapping)) {
            return $mapping;
        }

        if (array_key_exists('value', $mapping)) {
            return $mapping['value'];
        }

        $source = $mapping['source'] ?? null;
        if (! is_string($source) || $source === '') {
            return $this->missingValue();
        }

        return data_get($context, $source, $mapping['default'] ?? $this->missingValue());
    }

    private function missingValue(): object
    {
        static $missing;

        return $missing ??= new class {};
    }
}
