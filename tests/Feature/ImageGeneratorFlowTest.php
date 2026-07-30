<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\GeneratedImage;
use App\Models\MainTools;
use App\Models\Message;
use App\Models\SubTools;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImageGeneratorFlowTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'testing-public-api-key';

    private const INTERNAL_KEY = 'testing-internal-image-key';

    private const AI_BASE_URL = 'https://ai.internal.test';

    private string|false $previousApiKey;

    private bool $hadEnvApiKey;

    private ?string $previousEnvApiKey;

    private bool $hadServerApiKey;

    private ?string $previousServerApiKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousApiKey = getenv('API_KEY');
        $this->hadEnvApiKey = array_key_exists('API_KEY', $_ENV);
        $this->previousEnvApiKey = $_ENV['API_KEY'] ?? null;
        $this->hadServerApiKey = array_key_exists('API_KEY', $_SERVER);
        $this->previousServerApiKey = $_SERVER['API_KEY'] ?? null;

        putenv('API_KEY='.self::API_KEY);
        $_ENV['API_KEY'] = self::API_KEY;
        $_SERVER['API_KEY'] = self::API_KEY;

        config()->set('services.aiarabic.url', self::AI_BASE_URL);
        config()->set('services.aiarabic.base_url', self::AI_BASE_URL);
        config()->set('services.aiarabic.internal_api_key', self::INTERNAL_KEY);
        config()->set('services.ai.base_url', self::AI_BASE_URL);
        config()->set('services.ai.internal_api_key', self::INTERNAL_KEY);
        config()->set('services.aiarabic.inject_qdrant_context', false);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        $this->previousApiKey === false
            ? putenv('API_KEY')
            : putenv('API_KEY='.$this->previousApiKey);

        $this->restoreEnvironmentValue($_ENV, $this->hadEnvApiKey, $this->previousEnvApiKey);
        $this->restoreEnvironmentValue($_SERVER, $this->hadServerApiKey, $this->previousServerApiKey);

        parent::tearDown();
    }

    public function test_it_generates_downloads_stores_and_returns_local_image_urls(): void
    {
        [$user, $conversation] = $this->makeContext();
        $this->fakeSuccessfulGeneration([$this->providerFile('file-1')]);
        Sanctum::actingAs($user);

        $response = $this->sendGeneration($conversation);

        $response->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.tool', 'ai_image_generator')
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.images.0.filename', 'ai-image-1.png');

        $previewUrl = $response->json('data.images.0.preview_url');
        $downloadUrl = $response->json('data.images.0.download_url');
        $this->assertStringStartsWith('/api/v1/generated-images/', $previewUrl);
        $this->assertStringEndsWith('/preview', $previewUrl);
        $this->assertStringEndsWith('/download', $downloadUrl);
        $this->assertStringNotContainsString(self::INTERNAL_KEY, $response->getContent());
        $this->assertStringNotContainsString(self::AI_BASE_URL, $response->getContent());

        $image = GeneratedImage::firstOrFail();
        Storage::disk('local')->assertExists($image->path);
        $this->assertSame($user->id, $image->user_id);
        $this->assertSame($conversation->id, $image->conversation_id);
        $this->assertSame(21, $image->sub_tool_id);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && $request->url() === self::AI_BASE_URL.'/tasks/generated-files/download/file-1'
                && $request->header('x-internal-api-key')[0] === self::INTERNAL_KEY;
        });
    }

    public function test_preview_and_download_are_protected_and_do_not_expose_storage_paths(): void
    {
        [$owner, $conversation] = $this->makeContext();
        $this->fakeSuccessfulGeneration([$this->providerFile('file-1')]);
        Sanctum::actingAs($owner);
        $response = $this->sendGeneration($conversation);
        $previewUrl = $response->json('data.images.0.preview_url');
        $downloadUrl = $response->json('data.images.0.download_url');

        $this->get($previewUrl)
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename="ai-image-1.png"')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get($downloadUrl)
            ->assertOk()
            ->assertDownload('ai-image-1.png');

        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);
        $this->get($previewUrl)->assertForbidden();
        $this->get($downloadUrl)->assertForbidden();
    }

    public function test_it_rejects_a_non_image_file(): void
    {
        [$user, $conversation] = $this->makeContext();
        Http::fake([
            self::AI_BASE_URL.'/tasks/image-generator/chat' => Http::response(
                $this->providerResponse([$this->providerFile('not-image', 'image/png')])
            ),
            self::AI_BASE_URL.'/tasks/generated-files/download/not-image' => Http::response(
                'not an image',
                200,
                ['Content-Type' => 'text/plain']
            ),
        ]);
        Sanctum::actingAs($user);

        $response = $this->sendGeneration($conversation);

        $response->assertOk()
            ->assertJsonPath('data.success', false)
            ->assertJsonPath('data.type', 'error')
            ->assertJsonCount(0, 'data.images');
        $this->assertDatabaseCount('generated_images', 0);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'is_error' => true,
        ]);
    }

    public function test_it_rejects_an_untrusted_download_url_without_requesting_it(): void
    {
        [$user, $conversation] = $this->makeContext();
        $file = $this->providerFile('evil-file');
        $file['download_url'] = 'https://evil.example/files/image.png';
        Http::fake([
            self::AI_BASE_URL.'/tasks/image-generator/chat' => Http::response(
                $this->providerResponse([$file])
            ),
            '*' => Http::response('unexpected', 500),
        ]);
        Sanctum::actingAs($user);

        $this->sendGeneration($conversation)
            ->assertOk()
            ->assertJsonPath('data.success', false);

        Http::assertNotSent(
            fn (Request $request): bool => str_contains($request->url(), 'evil.example')
        );
        $this->assertDatabaseCount('generated_images', 0);
    }

    public function test_it_handles_download_failure_without_exposing_provider_details(): void
    {
        [$user, $conversation] = $this->makeContext();
        Http::fake([
            self::AI_BASE_URL.'/tasks/image-generator/chat' => Http::response(
                $this->providerResponse([$this->providerFile('missing-file')])
            ),
            self::AI_BASE_URL.'/tasks/generated-files/download/missing-file' => Http::response(
                ['detail' => 'invalid internal key'],
                401
            ),
        ]);
        Sanctum::actingAs($user);

        $response = $this->sendGeneration($conversation);

        $response->assertOk()
            ->assertJsonPath('data.success', false)
            ->assertJsonMissing(['detail' => 'invalid internal key']);
        $this->assertStringNotContainsString(self::INTERNAL_KEY, $response->getContent());
    }

    public function test_it_handles_multiple_images_and_reports_partial_downloads(): void
    {
        [$user, $conversation] = $this->makeContext();
        Http::fake([
            self::AI_BASE_URL.'/tasks/image-generator/chat' => Http::response(
                $this->providerResponse([
                    $this->providerFile('file-1'),
                    $this->providerFile('file-2'),
                    $this->providerFile('file-3'),
                ])
            ),
            self::AI_BASE_URL.'/tasks/generated-files/download/file-1' => Http::response(
                $this->pngBytes(),
                200,
                ['Content-Type' => 'image/png']
            ),
            self::AI_BASE_URL.'/tasks/generated-files/download/file-2' => Http::response(
                $this->pngBytes(),
                200,
                ['Content-Type' => 'image/png']
            ),
            self::AI_BASE_URL.'/tasks/generated-files/download/file-3' => Http::response('', 503),
        ]);
        Sanctum::actingAs($user);

        $response = $this->sendGeneration($conversation, ['results_count' => 3]);

        $response->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.count', 2)
            ->assertJsonCount(2, 'data.images')
            ->assertJsonCount(1, 'data.failed_files');
        $this->assertDatabaseCount('generated_images', 2);
    }

    public function test_conversation_history_restores_generated_images_and_settings(): void
    {
        [$user, $conversation] = $this->makeContext();
        $this->fakeSuccessfulGeneration([$this->providerFile('file-1')]);
        Sanctum::actingAs($user);
        $this->sendGeneration($conversation, [
            'size' => '1024x1536',
            'quality' => 'high',
            'seed' => 42,
        ])->assertOk();

        $history = $this->withHeaders(['X-API-KEY' => self::API_KEY])
            ->getJson("/api/v1/conversation/{$conversation->uuid}");

        $history->assertOk()
            ->assertJsonPath('data.message.1.role', 'assistant')
            ->assertJsonPath('data.message.1.metadata.images.0.filename', 'ai-image-1.png')
            ->assertJsonPath('data.message.1.metadata.state.size', '1024x1536')
            ->assertJsonPath('data.message.1.metadata.state.quality', 'high')
            ->assertJsonPath('data.message.1.metadata.state.seed', 42);
    }

    public function test_it_rejects_invalid_image_generation_state(): void
    {
        [$user, $conversation] = $this->makeContext();
        Sanctum::actingAs($user);

        $this->sendGeneration($conversation, [
            'size' => '9999x9999',
            'quality' => 'ultra',
            'results_count' => 8,
            'output_format' => 'svg',
        ])->assertStatus(422)
            ->assertJsonValidationErrors([
                'state.size',
                'state.quality',
                'state.results_count',
                'state.output_format',
            ]);
    }

    private function makeContext(): array
    {
        $user = User::factory()->create();
        $mainTool = MainTools::create([
            'id' => 5,
            'name' => 'Image Tools '.Str::random(6),
            'slug' => 'image-tools-'.Str::random(6),
        ]);
        $subTool = SubTools::create([
            'id' => 21,
            'main_tool_id' => $mainTool->id,
            'name' => 'AI Image Generator '.Str::random(6),
            'slug' => 'ai-image-generator-'.Str::random(6),
            'endpoint' => 'tasks/image-generator/chat',
            'config' => [
                'tool_key' => 'ai_image_generator',
                'endpoint' => 'tasks/image-generator/chat',
            ],
        ]);
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => $subTool->id,
            'uuid' => (string) Str::uuid(),
        ]);

        return [$user, $conversation];
    }

    private function sendGeneration(Conversation $conversation, array $stateOverrides = [])
    {
        return $this->withHeaders(['X-API-KEY' => self::API_KEY])
            ->postJson('/api/v1/message/send', [
                'sub_tool_id' => 21,
                'conversation_uuid' => $conversation->uuid,
                'user_message' => 'A cinematic futuristic Cairo skyline at sunset.',
                'tool' => 'ai_image_generator',
                'tool_key' => 'ai_image_generator',
                'idempotency_key' => (string) Str::uuid(),
                'state' => array_replace([
                    'provider' => null,
                    'negative_prompt' => 'blurry, text, watermark',
                    'size' => '1024x1024',
                    'quality' => 'medium',
                    'results_count' => 1,
                    'output_format' => 'png',
                    'seed' => null,
                    'extra_options' => [],
                    'last_output' => null,
                ], $stateOverrides),
                'debug' => false,
            ]);
    }

    private function fakeSuccessfulGeneration(array $files): void
    {
        $responses = [
            self::AI_BASE_URL.'/tasks/image-generator/chat' => Http::response(
                $this->providerResponse($files)
            ),
        ];

        foreach ($files as $file) {
            $responses[self::AI_BASE_URL.$file['download_url']] = Http::response(
                $this->pngBytes(),
                200,
                ['Content-Type' => 'image/png']
            );
        }

        Http::fake($responses);
    }

    private function providerResponse(array $files): array
    {
        return [
            'success' => true,
            'type' => 'result',
            'tool' => 'ai_image_generator',
            'provider' => 'runware',
            'model' => 'runware:100@1',
            'message' => 'Images generated successfully.',
            'files' => $files,
            'count' => count($files),
            'request_id' => (string) Str::uuid(),
            'metadata' => [
                'size' => '1024x1024',
                'quality' => 'medium',
                'seeds' => [2048415861],
                'provider_cost_usd' => 0.0006,
            ],
        ];
    }

    private function providerFile(string $fileId, string $contentType = 'image/png'): array
    {
        return [
            'file_id' => $fileId,
            'filename' => 'provider-controlled-name.png',
            'content_type' => $contentType,
            'download_url' => "/tasks/generated-files/download/{$fileId}",
            'size_bytes' => strlen($this->pngBytes()),
        ];
    }

    private function pngBytes(): string
    {
        return (string) base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );
    }

    private function restoreEnvironmentValue(array &$environment, bool $existed, ?string $value): void
    {
        if ($existed) {
            $environment['API_KEY'] = $value;

            return;
        }

        unset($environment['API_KEY']);
    }
}
