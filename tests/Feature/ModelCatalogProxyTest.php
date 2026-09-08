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

    public function test_translation_proxy_accepts_the_real_top_level_sample_and_uses_the_server_only_key(): void
    {
        $sample = json_decode(file_get_contents(base_path('tests/Fixtures/general-translation-catalog.json')), true, 512, JSON_THROW_ON_ERROR);
        $endpoint = config('model_catalogs.sources.general_translation.endpoint');

        $this->assertSame(
            'https://api.aiarabic.com/tasks/general-tools/general_translation/models',
            $endpoint
        );

        Http::preventStrayRequests();
        Http::fake([$endpoint => Http::response($sample)]);

        $response = $this->withHeaders(['X-API-KEY' => 'testing-api-key'])
            ->getJson('/api/v1/model-catalogs/general_translation')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.tool', 'general_translation')
            ->assertJsonCount(7, 'data.items')
            ->assertJsonPath('data.items', $sample['items'])
            ->assertJsonPath('data.items.1.name', 'Free Translation Router')
            ->assertJsonPath('data.items.1.description', null)
            ->assertJsonPath('data.items.1.is_free', true)
            ->assertJsonPath('data.items.0.is_available', true)
            ->assertJsonPath('data.items.0.is_recommended', true)
            ->assertJsonPath('data.items.0.sort_order', 40)
            ->assertJsonPath('data.items.0.parameter_schema.source_language.default', 'auto')
            ->assertJsonPath('data.items.0.parameter_schema.target_language.required', true)
            ->assertJsonPath('data.items.0.recommended_parameters.preserve_formatting', true)
            ->assertJsonMissingPath('data.data');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === $endpoint
            && $request->hasHeader('x-internal-api-key', 'server-only-test-key'));
        $this->assertStringNotContainsString('server-only-test-key', $response->getContent());
    }

    public function test_media_proxy_sends_the_image_generation_filter_and_preserves_the_full_contract(): void
    {
        $sample = json_decode(file_get_contents(base_path('tests/Fixtures/general-media-catalog.json')), true, 512, JSON_THROW_ON_ERROR);
        $endpoint = config('model_catalogs.sources.general_media.endpoint');

        $this->assertSame(
            'https://api.aiarabic.com/tasks/general-tools/general_media/models',
            $endpoint
        );
        $this->assertSame(
            ['operation' => 'image_generation'],
            config('model_catalogs.sources.general_media.query')
        );

        Http::preventStrayRequests();
        Http::fake(["{$endpoint}*" => Http::response($sample)]);

        $response = $this->withHeaders(['X-API-KEY' => 'testing-api-key'])
            ->getJson('/api/v1/model-catalogs/general_media')
            ->assertOk()
            ->assertJsonPath('data.tool', 'general_media')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.provider', 'runware')
            ->assertJsonPath('data.items.0.operation', 'image_generation')
            ->assertJsonPath('data.items.0.parameter_schema.size.default', '1024x1024')
            ->assertJsonPath('data.items.0.recommended_parameters.output_format', 'webp')
            ->assertJsonPath('data.items.0.pricing.source', 'runware_response')
            ->assertJsonPath('data.pagination.total', 2);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === "{$endpoint}?operation=image_generation"
            && $request->data() === ['operation' => 'image_generation']
            && $request->hasHeader('x-internal-api-key', 'server-only-test-key')
        );
        $this->assertStringNotContainsString('server-only-test-key', $response->getContent());
    }

    public function test_audio_proxy_sends_the_speech_to_text_filter_and_preserves_the_full_contract(): void
    {
        $sample = json_decode(file_get_contents(base_path('tests/Fixtures/general-audio-catalog.json')), true, 512, JSON_THROW_ON_ERROR);
        $endpoint = config('model_catalogs.sources.general_audio.endpoint');

        $this->assertSame(
            'https://api.aiarabic.com/tasks/general-tools/general_audio/models',
            $endpoint
        );
        $this->assertSame(
            ['operation' => 'speech_to_text'],
            config('model_catalogs.sources.general_audio.operations.speech_to_text.query')
        );

        Http::preventStrayRequests();
        Http::fake(["{$endpoint}*" => Http::response($sample)]);

        $response = $this->withHeaders(['X-API-KEY' => 'testing-api-key'])
            ->getJson('/api/v1/model-catalogs/general_audio?operation=speech_to_text')
            ->assertOk()
            ->assertJsonPath('data.tool', 'general_audio')
            ->assertJsonCount(8, 'data.items')
            ->assertJsonPath('data.items.0.name', 'Whisper Large V3')
            ->assertJsonPath('data.items.1.name', 'GPT-4o Mini Transcribe')
            ->assertJsonPath('data.items.2.name', 'Whisper Large V3 Turbo')
            ->assertJsonPath('data.items.3.name', 'Nemotron 3.5 ASR Streaming 0.6B')
            ->assertJsonPath('data.items.4.name', 'Qwen3 ASR 0.6B')
            ->assertJsonPath('data.items.5.name', 'Qwen3 ASR 1.7B')
            ->assertJsonPath('data.items.6.name', 'GPT Transcribe')
            ->assertJsonPath('data.items.7.name', 'Chirp 3')
            ->assertJsonPath('data.items.0.provider', 'openrouter')
            ->assertJsonPath('data.items.0.provider_model_id', 'openai/whisper-large-v3')
            ->assertJsonPath('data.items.0.operation', 'speech_to_text')
            ->assertJsonPath('data.items.1.parameter_schema.language.nullable', true)
            ->assertJsonPath('data.items.1.recommended_parameters.include_segments', false)
            ->assertJsonPath('data.items.2.pricing.audio_per_hour', 0.04)
            ->assertJsonPath('data.items.4.capabilities.4', 'timestamps')
            ->assertJsonPath('data.pagination.total', 8);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === "{$endpoint}?operation=speech_to_text"
            && $request->data() === ['operation' => 'speech_to_text']
            && $request->hasHeader('x-internal-api-key', 'server-only-test-key')
        );
        $this->assertStringNotContainsString('server-only-test-key', $response->getContent());
    }

    public function test_audio_proxy_sends_the_text_to_speech_filter_and_preserves_the_full_contract(): void
    {
        $sample = json_decode(file_get_contents(base_path('tests/Fixtures/general-audio-text-to-speech-catalog.json')), true, 512, JSON_THROW_ON_ERROR);
        $endpoint = config('model_catalogs.sources.general_audio.endpoint');

        $this->assertSame(
            ['operation' => 'text_to_speech'],
            config('model_catalogs.sources.general_audio.operations.text_to_speech.query')
        );

        Http::preventStrayRequests();
        Http::fake(["{$endpoint}*" => Http::response($sample)]);

        $response = $this->withHeaders(['X-API-KEY' => 'testing-api-key'])
            ->getJson('/api/v1/model-catalogs/general_audio?operation=text_to_speech')
            ->assertOk()
            ->assertJsonPath('data.tool', 'general_audio')
            ->assertJsonCount(7, 'data.items')
            ->assertJsonPath('data.items.0.name', 'GPT-4o Mini TTS')
            ->assertJsonPath('data.items.0.operation', 'text_to_speech')
            ->assertJsonPath('data.items.0.parameter_schema.voice.default', 'nova')
            ->assertJsonPath('data.items.0.parameter_schema.speed.maximum', 4)
            ->assertJsonPath('data.items.0.recommended_parameters.response_format', 'mp3')
            ->assertJsonPath('data.items.0.pricing.source', 'openrouter_response')
            ->assertJsonPath('data.items.1.name', 'Grok Voice TTS 1.0')
            ->assertJsonPath('data.items.2.name', 'Flux TTS Free')
            ->assertJsonPath('data.items.3.is_available', false)
            ->assertJsonPath('data.items.6.name', 'MAI Voice 2')
            ->assertJsonPath('data.pagination.total', 7);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === "{$endpoint}?operation=text_to_speech"
            && $request->data() === ['operation' => 'text_to_speech']
            && $request->hasHeader('x-internal-api-key', 'server-only-test-key')
        );
        $this->assertStringNotContainsString('server-only-test-key', $response->getContent());
    }

    public function test_audio_proxy_rejects_an_unknown_operation_without_an_upstream_request(): void
    {
        Http::fake();

        $this->withHeaders(['X-API-KEY' => 'testing-api-key'])
            ->getJson('/api/v1/model-catalogs/general_audio?operation=voice_conversion')
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_audio_tool_mapping_keeps_the_verified_slug_and_supports_an_optional_additional_slug(): void
    {
        $key = 'FREE_AI_GENERAL_AUDIO_TOOL_SLUG';
        $previousEnv = $_ENV[$key] ?? null;
        $previousServer = $_SERVER[$key] ?? null;
        $previousProcess = getenv($key);

        try {
            foreach (['', 'verified-speech-tool'] as $slug) {
                $_ENV[$key] = $_SERVER[$key] = $slug;
                putenv("{$key}={$slug}");
                $catalogs = require config_path('model_catalogs.php');

                $this->assertSame('general_audio', $catalogs['free_ai_tools']['audio-voice']);
                if ($slug === '') {
                    $this->assertArrayNotHasKey('verified-speech-tool', $catalogs['free_ai_tools']);
                } else {
                    $this->assertSame('general_audio', $catalogs['free_ai_tools'][$slug]);
                }

                $this->assertSame('general_media', $catalogs['free_ai_tools']['images-video']);
                $this->assertSame('general_translation', $catalogs['free_ai_tools']['translation']);
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
                        'images-video' => 'general_media',
                        'audio-voice' => 'general_audio',
                        'translation' => 'general_translation',
                    ], $catalogs['free_ai_tools']);
                }
                $this->assertSame('general_code', $catalogs['free_ai_tools']['programming-technology']);
                $this->assertSame('general_media', $catalogs['free_ai_tools']['images-video']);
                $this->assertSame('general_audio', $catalogs['free_ai_tools']['audio-voice']);
                $this->assertSame('general_translation', $catalogs['free_ai_tools']['translation']);
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

    public function test_translation_failure_returns_502_without_requesting_another_catalog(): void
    {
        $endpoint = config('model_catalogs.sources.general_translation.endpoint');
        Http::preventStrayRequests();
        Http::fake([$endpoint => Http::response(['message' => 'Unavailable'], 503)]);

        $this->withHeaders(['X-API-KEY' => 'testing-api-key'])
            ->getJson('/api/v1/model-catalogs/general_translation')
            ->assertStatus(502)
            ->assertJsonPath('message', 'Model catalog is currently unavailable.');

        Http::assertNotSent(fn (Request $request) => $request->url() !== $endpoint);
    }
}
