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
        $state = array_replace(
            $this->configService->defaultState($config),
            is_array($requestState) ? $requestState : []
        );

        $payload = $this->basePayloadBuilder->build($conversation, $latestUserMessage);
        $payload['user_id'] = (int) $conversation->user_id;
        $payload['sub_tool_id'] = (int) $conversation->sub_tool_id;
        $payload['conversation_uuid'] = (string) $conversation->uuid;
        $payload['user_message'] = trim((string) $latestUserMessage->content);
        $payload['state'] = $state;
        $payload['debug'] = $debug;

        $optionalFields = [
            'tool_key' => 'tool',
            'model_key' => 'model_key',
            'provider' => 'provider',
            'system_prompt' => 'system_prompt',
            'response_format' => 'response_format',
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
