<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Arr;

class DynamicToolPayloadBuilder
{
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
        $state = $this->mergeState(
            $this->configService->defaultState($config),
            is_array($requestState) ? $requestState : [],
            (string) ($config['default_state_mode'] ?? '')
        );
        $state = $this->applyStateExtractors($state, $config, [
            'user_message' => $userMessage,
            'state' => $state,
        ]);

        $payload = $this->basePayloadBuilder->build($conversation, $latestUserMessage);
        $payload['user_id'] = (int) $conversation->user_id;
        $payload['sub_tool_id'] = (int) $conversation->sub_tool_id;
        $payload['conversation_uuid'] = (string) $conversation->uuid;
        $payload['user_message'] = $userMessage;
        $payload['state'] = $state;
        $payload['debug'] = $debug;

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
