<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModelCatalogProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';

        config()->set('model_catalogs.sources.general_chat', [
            'endpoint' => 'https://catalog.example.test/tasks/general-tools/general_chat/models',
            'requires_internal_key' => true,
            'internal_key_config' => 'services.aiarabic.internal_api_key',
        ]);
        config()->set('services.aiarabic.internal_api_key', 'server-only-test-key');
    }

    public function test_proxy_attaches_internal_key_only_to_the_upstream_catalog_request(): void
    {
        Http::fake([
            'catalog.example.test/*' => Http::response([
                'tool' => 'general_chat',
                'items' => [
                    [
                        'id' => 1,
                        'name' => 'Free Smart Router',
                        'is_free' => true,
                        'is_available' => true,
                    ],
                ],
            ]),
        ]);

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-API-KEY' => 'testing-api-key',
        ])->get('/api/v1/model-catalogs/general_chat')
            ->assertOk()
            ->assertJsonPath('data.tool', 'general_chat')
            ->assertJsonPath('data.items.0.name', 'Free Smart Router');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://catalog.example.test/tasks/general-tools/general_chat/models'
            && $request->hasHeader('x-internal-api-key', 'server-only-test-key')
        );

        $frontendClient = file_get_contents(resource_path('js/services/ApiClient.js'));
        $this->assertStringNotContainsString('x-internal-api-key', $frontendClient);
    }

    public function test_proxy_rejects_unknown_sources_without_an_upstream_request(): void
    {
        Http::fake();

        $this->withHeaders([
            'Accept' => 'application/json',
            'X-API-KEY' => 'testing-api-key',
        ])->get('/api/v1/model-catalogs/not_configured')
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_code_proxy_uses_the_configured_endpoint_and_server_only_key(): void
    {
        $endpoint = config('model_catalogs.sources.general_code.endpoint');
        $this->assertSame(
            'https://api.aiarabic.com/tasks/general-tools/general_code/models',
            $endpoint
        );
        Http::preventStrayRequests();
        Http::fake([
            $endpoint => Http::response([
                'tool' => 'general_code',
                'items' => [['id' => 17, 'name' => 'Code fixture', 'provider_model_id' => 'fixture/code']],
            ]),
        ]);

        $response = $this->withHeaders(['X-API-KEY' => 'testing-api-key'])
            ->getJson('/api/v1/model-catalogs/general_code')
            ->assertOk()
            ->assertJsonPath('data.tool', 'general_code')
            ->assertJsonPath('data.items.0.provider_model_id', 'fixture/code');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === $endpoint
            && $request->hasHeader('x-internal-api-key', 'server-only-test-key')
        );
        $this->assertStringNotContainsString('server-only-test-key', $response->getContent());

        config()->set('model_catalogs.sources.general_code.endpoint', 'https://catalog.example.test/code-override');
        Http::fake(['https://catalog.example.test/code-override' => Http::response(['tool' => 'general_code', 'items' => []])]);
        $this->withHeaders(['X-API-KEY' => 'testing-api-key'])
            ->getJson('/api/v1/model-catalogs/general_code')->assertOk();
        Http::assertSent(fn (Request $request) => $request->url() === 'https://catalog.example.test/code-override');
    }

    public static function failedCodeCatalogs(): array
    {
        return [
            'upstream unavailable' => [503, ['message' => 'Unavailable']],
            'missing items' => [200, ['tool' => 'general_code']],
            'invalid items' => [200, ['tool' => 'general_code', 'items' => 'invalid']],
        ];
    }

    public function test_proxy_accepts_the_real_top_level_code_sample_and_preserves_nullable_fields(): void
    {
        $sample = json_decode(file_get_contents(base_path('tests/Fixtures/general-code-catalog.json')), true, 512, JSON_THROW_ON_ERROR);
        $endpoint = config('model_catalogs.sources.general_code.endpoint');
        Http::preventStrayRequests();
        Http::fake([$endpoint => Http::response($sample)]);

        $this->withHeaders(['X-API-KEY' => 'testing-api-key'])
            ->getJson('/api/v1/model-catalogs/general_code')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.tool', 'general_code')
            ->assertJsonCount(7, 'data.items')
            ->assertJsonPath('data.items', $sample['items'])
            ->assertJsonPath('data.items.0.name', 'Qwen3 Coder Next')
            ->assertJsonPath('data.items.0.description', null)
            ->assertJsonPath('data.items.0.pricing', null)
            ->assertJsonMissingPath('data.data');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === $endpoint
            && $request->hasHeader('x-internal-api-key', 'server-only-test-key'));
    }

    public function test_code_tool_mapping_is_explicit_and_keeps_the_existing_chat_mapping(): void
    {
        $key = 'FREE_AI_GENERAL_CODE_TOOL_SLUG';
        $previousEnv = $_ENV[$key] ?? null;
        $previousServer = $_SERVER[$key] ?? null;
        $previousProcess = getenv($key);
        try {
            foreach (['', 'catalog-test-tool', 'chat-writing'] as $slug) {
                $_ENV[$key] = $_SERVER[$key] = $slug;
                putenv("{$key}={$slug}");
                $catalogs = require config_path('model_catalogs.php');
                $this->assertSame('general_chat', $catalogs['free_ai_tools']['chat-writing']);
                if ($slug === 'catalog-test-tool') {
                    $this->assertSame('general_code', $catalogs['free_ai_tools'][$slug]);
                } else {
                    $this->assertSame([
                        'chat-writing' => 'general_chat',
                        'programming-technology' => 'general_code',
                    ], $catalogs['free_ai_tools']);
                }
                $this->assertSame('general_code', $catalogs['free_ai_tools']['programming-technology']);
            }
        } finally {
            if ($previousEnv === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $previousEnv;
            }
            if ($previousServer === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $previousServer;
            }
            putenv($previousProcess === false ? $key : "{$key}={$previousProcess}");
        }
    }

    #[DataProvider('failedCodeCatalogs')]
    public function test_code_failure_returns_502_without_requesting_chat(int $status, array $payload): void
    {
        $endpoint = config('model_catalogs.sources.general_code.endpoint');
        Http::preventStrayRequests();
        Http::fake([$endpoint => Http::response($payload, $status)]);

        $this->withHeaders(['X-API-KEY' => 'testing-api-key'])
            ->getJson('/api/v1/model-catalogs/general_code')
            ->assertStatus(502)
            ->assertJsonPath('message', 'Model catalog is currently unavailable.');

        Http::assertNotSent(fn (Request $request) => $request->url() !== $endpoint);
    }
}
