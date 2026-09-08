<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class ModelCatalogService
{
    /**
     * @return array{tool: string, items: array<int, mixed>, pagination?: array<string, mixed>}
     *
     * @throws ConnectionException
     */
    public function getModels(string $sourceKey, ?string $operation = null): array
    {
        $source = config("model_catalogs.sources.{$sourceKey}");

        if (! is_array($source)) {
            throw new InvalidArgumentException('Unknown model catalog source.');
        }

        $resolvedOperation = $this->resolveOperationFromConfig($source, $operation);
        $operationConfig = $resolvedOperation !== null
            ? ($source['operations'][$resolvedOperation] ?? [])
            : [];

        if (! is_array($operationConfig)) {
            throw new RuntimeException('Model catalog operation configuration must be an array.');
        }

        $endpoint = trim((string) ($operationConfig['endpoint'] ?? $source['endpoint'] ?? ''));

        if ($endpoint === '') {
            throw new RuntimeException('Model catalog endpoint is not configured.');
        }

        $baseQuery = $source['query'] ?? [];
        $operationQuery = $operationConfig['query'] ?? [];

        if (! is_array($baseQuery) || ! is_array($operationQuery)) {
            throw new RuntimeException('Model catalog query configuration must be an array.');
        }

        $query = array_merge($baseQuery, $operationQuery);

        $headers = [];

        if ((bool) ($source['requires_internal_key'] ?? false)) {
            $keyConfig = (string) ($source['internal_key_config'] ?? 'services.aiarabic.internal_api_key');
            $internalKey = trim((string) config($keyConfig));

            if ($internalKey === '') {
                throw new RuntimeException('Model catalog internal key is not configured.');
            }

            $headers['x-internal-api-key'] = $internalKey;
        }

        $response = Http::acceptJson()
            ->withHeaders($headers)
            ->timeout(max(2, (int) config('model_catalogs.timeout', 20)))
            ->retry(2, 250, null, false)
            ->get($endpoint, $query);

        $payload = $response->json();

        if (! $response->successful() || ! is_array($payload) || ! is_array($payload['items'] ?? null)) {
            throw new RuntimeException('The model catalog service returned an invalid response.');
        }

        $catalog = [
            'tool' => (string) ($payload['tool'] ?? $sourceKey),
            'items' => array_values($payload['items']),
        ];

        if (is_array($payload['pagination'] ?? null)) {
            $catalog['pagination'] = $payload['pagination'];
        }

        return $catalog;
    }

    public function resolveOperation(string $sourceKey, ?string $operation = null): ?string
    {
        $source = config("model_catalogs.sources.{$sourceKey}");

        if (! is_array($source)) {
            throw new InvalidArgumentException('Unknown model catalog source.');
        }

        return $this->resolveOperationFromConfig($source, $operation);
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function resolveOperationFromConfig(array $source, ?string $operation): ?string
    {
        $operations = $source['operations'] ?? [];

        if (! is_array($operations)) {
            throw new RuntimeException('Model catalog operations configuration must be an array.');
        }

        $requested = trim((string) $operation);

        if ($operations === []) {
            if ($requested !== '') {
                throw new InvalidArgumentException('Unknown model catalog operation.');
            }

            return null;
        }

        $resolved = $requested !== ''
            ? $requested
            : trim((string) ($source['default_operation'] ?? ''));

        if ($resolved === '' || ! array_key_exists($resolved, $operations)) {
            throw new InvalidArgumentException('Unknown model catalog operation.');
        }

        return $resolved;
    }
}
