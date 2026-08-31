<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

        Http::assertSent(fn (Request $request) =>
            $request->url() === 'https://catalog.example.test/tasks/general-tools/general_chat/models'
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
}
