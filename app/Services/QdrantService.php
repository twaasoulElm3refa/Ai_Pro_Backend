<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QdrantService
{
    protected ?string $baseUrl;
    protected int $vectorSize;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.qdrant.url'), '/');
        $this->vectorSize = (int) config('services.qdrant.vector_size', 1536);
    }

    public function getCollections()
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            return Http::timeout(5)->get($this->baseUrl . '/collections')->json();
        } catch (\Throwable $th) {
            Log::warning('Qdrant collections fetch failed.', ['error' => $th->getMessage()]);

            return [];
        }
    }

    public function createCollection($name, $vectorSize = null): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::timeout(10)->put($this->baseUrl . "/collections/{$name}", [
                'vectors' => [
                    'size' => $vectorSize ?: $this->vectorSize,
                    'distance' => 'Cosine',
                ],
            ]);

            return $response->successful();
        } catch (\Throwable $th) {
            Log::warning('Qdrant collection creation failed.', [
                'collection' => $name,
                'error' => $th->getMessage(),
            ]);

            return false;
        }
    }

    public function createCollectionIfNotExists(string $name): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::timeout(5)->get($this->baseUrl . "/collections/{$name}");

            if ($response->successful()) {
                return true;
            }

            if ($response->status() !== 404) {
                Log::warning('Qdrant collection lookup failed.', [
                    'collection' => $name,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return $this->createCollection($name);
        } catch (\Throwable $th) {
            Log::warning('Qdrant collection lookup failed.', [
                'collection' => $name,
                'error' => $th->getMessage(),
            ]);

            return false;
        }
    }

    public function insertMessage(string $collection, array $data): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            if (! $this->createCollectionIfNotExists($collection)) {
                return false;
            }

            $messageId = $data['message_id'] ?? null;
            $content = (string) ($data['content'] ?? '');
            $pointId = $messageId ?: (string) Str::uuid();

            $response = Http::timeout(10)->put($this->baseUrl . "/collections/{$collection}/points?wait=true", [
                'points' => [
                    [
                        'id' => $pointId,
                        'vector' => $data['vector'] ?? $this->generateEmbedding($content),
                        'payload' => [
                            'conversation_id' => $data['conversation_id'] ?? null,
                            'message_id' => $messageId,
                            'content' => $content,
                            'user_id' => $data['user_id'] ?? null,
                            'created_at' => $data['created_at'] ?? now()->toISOString(),
                        ],
                    ],
                ],
            ]);

            if (! $response->successful()) {
                Log::warning('Qdrant message insert failed.', [
                    'collection' => $collection,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return $response->successful();
        } catch (\Throwable $th) {
            Log::warning('Qdrant message insert failed.', [
                'collection' => $collection,
                'error' => $th->getMessage(),
            ]);

            return false;
        }
    }

    public function searchMessages(string $collection, string $query, int $limit = 10): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        try {
            $response = Http::timeout(10)->post($this->baseUrl . "/collections/{$collection}/points/search", [
                'vector' => $this->generateEmbedding($query),
                'limit' => $limit,
                'with_payload' => true,
            ]);

            return $response->successful() ? ($response->json('result') ?? []) : [];
        } catch (\Throwable $th) {
            Log::warning('Qdrant message search failed.', [
                'collection' => $collection,
                'error' => $th->getMessage(),
            ]);

            return [];
        }
    }

    public function deleteCollection(string $name): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::timeout(10)->delete($this->baseUrl . "/collections/{$name}");

            return $response->successful() || $response->status() === 404;
        } catch (\Throwable $th) {
            Log::warning('Qdrant collection delete failed.', [
                'collection' => $name,
                'error' => $th->getMessage(),
            ]);

            return false;
        }
    }

    public function collectionName(int $conversationId): string
    {
        return "conversation_{$conversationId}";
    }

    public function generateEmbedding(string $text): array
    {
        $vector = array_fill(0, $this->vectorSize, 0.0);
        $tokens = preg_split('/\s+/', mb_strtolower(trim($text))) ?: [];

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            $index = abs(crc32($token)) % $this->vectorSize;
            $vector[$index] += 1.0;
        }

        $magnitude = sqrt(array_sum(array_map(static fn ($value) => $value * $value, $vector)));

        if ($magnitude <= 0) {
            return $vector;
        }

        return array_map(static fn ($value) => $value / $magnitude, $vector);
    }

    protected function isConfigured(): bool
    {
        if ($this->baseUrl !== '') {
            return true;
        }

        Log::debug('Qdrant URL is not configured.');

        return false;
    }
}
