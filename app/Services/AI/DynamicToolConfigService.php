<?php

namespace App\Services\AI;

use App\Models\SubTools;

class DynamicToolConfigService
{
    private const CONFIG_KEYS = [
        'tool_key',
        'model_key',
        'endpoint',
        'provider',
        'system_prompt',
        'state_schema',
        'payload_map',
        'default_state',
        'response_format',
    ];

    public function findSubTool(int $subToolId): ?SubTools
    {
        if ($subToolId <= 0) {
            return null;
        }

        return SubTools::query()->find($subToolId);
    }

    public function configFor(SubTools|int|null $subTool): array
    {
        if (is_int($subTool)) {
            $subTool = $this->findSubTool($subTool);
        }

        if (! $subTool) {
            return [];
        }

        $config = $subTool->config;

        if (is_string($config)) {
            $decoded = json_decode($config, true);
            $config = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($config)) {
            return [];
        }

        return array_intersect_key($config, array_flip(self::CONFIG_KEYS));
    }

    public function isDynamic(SubTools|int|null $subTool): bool
    {
        return $this->configFor($subTool) !== [];
    }

    public function endpointFor(SubTools $subTool, ?array $config = null): string
    {
        $config ??= $this->configFor($subTool);
        $configuredEndpoint = trim((string) ($config['endpoint'] ?? ''));

        if ($configuredEndpoint !== '') {
            return $configuredEndpoint;
        }

        $databaseEndpoint = trim((string) ($subTool->endpoint ?? ''));

        if ($databaseEndpoint !== '') {
            return $databaseEndpoint;
        }

        return '';
    }

    public function defaultState(array $config): array
    {
        return is_array($config['default_state'] ?? null)
            ? $config['default_state']
            : [];
    }

    public function stateValidationRules(array $config): array
    {
        $schema = is_array($config['state_schema'] ?? null)
            ? $config['state_schema']
            : [];

        if ($schema === []) {
            return [];
        }

        $requiredFields = [];
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            $requiredFields = is_array($schema['required'] ?? null) ? $schema['required'] : [];
            $schema = $schema['properties'];
        }

        $rules = [];
        foreach ($schema as $field => $definition) {
            if (! is_string($field) || $field === '') {
                continue;
            }

            $path = str_starts_with($field, 'state.') ? $field : "state.{$field}";
            [$fieldRules, $itemRules] = $this->normalizeFieldRules(
                $definition,
                in_array($field, $requiredFields, true)
            );

            if ($fieldRules !== []) {
                $rules[$path] = $fieldRules;
            }

            if ($itemRules !== []) {
                $rules["{$path}.*"] = $itemRules;
            }
        }

        if ($this->schemaRequiresState($rules)) {
            $rules['state'] = ['required', 'array'];
        }

        return $rules;
    }

    private function normalizeFieldRules(mixed $definition, bool $required): array
    {
        if (is_string($definition)) {
            return [$this->normalizeExplicitRules(array_values(array_filter(explode('|', $definition)))), []];
        }

        if (! is_array($definition)) {
            return [[], []];
        }

        if (array_is_list($definition)) {
            return [$this->normalizeExplicitRules($definition), []];
        }

        $rules = [];
        $isRequired = (bool) ($definition['required'] ?? $required);
        $isNullable = (bool) ($definition['nullable'] ?? false);

        $rules[] = $isRequired
            ? ($isNullable ? 'present' : 'required')
            : 'sometimes';
        if ($isNullable) {
            $rules[] = 'nullable';
        }

        $type = strtolower(trim((string) ($definition['type'] ?? '')));
        $typeRule = match ($type) {
            'string' => 'string',
            'integer', 'int' => 'integer',
            'number', 'numeric', 'float' => 'numeric',
            'boolean', 'bool' => 'boolean',
            'array', 'object' => 'array',
            default => null,
        };

        if ($typeRule !== null) {
            $rules[] = $typeRule;
        }

        foreach (['min', 'max', 'size'] as $constraint) {
            if (isset($definition[$constraint]) && is_numeric($definition[$constraint])) {
                $rules[] = "{$constraint}:{$definition[$constraint]}";
            }
        }

        if (is_array($definition['in'] ?? null) && $definition['in'] !== []) {
            $rules[] = 'in:'.implode(',', $definition['in']);
        }

        $extraRules = $definition['rules'] ?? [];
        if (is_string($extraRules)) {
            $extraRules = array_filter(explode('|', $extraRules));
        }
        if (is_array($extraRules)) {
            $rules = array_merge($rules, $extraRules);
        }

        $itemRules = $definition['items'] ?? [];
        if (is_string($itemRules)) {
            $itemRules = array_values(array_filter(explode('|', $itemRules)));
        } elseif (is_array($itemRules) && ! array_is_list($itemRules)) {
            [$itemRules] = $this->normalizeFieldRules($itemRules, false);
            $itemRules = array_values(array_filter(
                $itemRules,
                static fn (mixed $rule): bool => ! in_array($rule, ['sometimes', 'required'], true)
            ));
        }

        return [array_values(array_unique($rules)), is_array($itemRules) ? $itemRules : []];
    }

    private function schemaRequiresState(array $rules): bool
    {
        foreach ($rules as $path => $fieldRules) {
            if ($path === 'state' || ! is_array($fieldRules)) {
                continue;
            }

            if (in_array('required', $fieldRules, true) || in_array('present', $fieldRules, true)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeExplicitRules(array $rules): array
    {
        if (in_array('required', $rules, true) && in_array('nullable', $rules, true)) {
            $rules = array_map(
                static fn (mixed $rule): mixed => $rule === 'required' ? 'present' : $rule,
                $rules
            );
        }

        return array_values(array_unique($rules));
    }
}
