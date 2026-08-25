<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\GeneratedImage;
use App\Models\MainTools;
use App\Models\SubTools;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BackgroundRemoverFlowTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'testing-public-api-key';

    private const INTERNAL_KEY = 'testing-internal-background-key';

    private const AI_BASE_URL = 'https://ai.internal.test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ai.base_url', self::AI_BASE_URL);
        config()->set('services.ai.internal_api_key', self::INTERNAL_KEY);
        config()->set('services.aiarabic.internal_api_key', self::INTERNAL_KEY);
        putenv('API_KEY='.self::API_KEY);
        $_ENV['API_KEY'] = self::API_KEY;
        $_SERVER['API_KEY'] = self::API_KEY;
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        putenv('API_KEY');
        unset($_ENV['API_KEY'], $_SERVER['API_KEY']);
        parent::tearDown();
    }

    public function test_it_sends_multipart_preserves_files_and_returns_local_urls(): void
    {
        [$user, $conversation] = $this->makeContext();
        $this->fakeSuccessfulRemoval();
        Sanctum::actingAs($user);

        $response = $this->sendRemoval($conversation);

        $response->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.tool', 'ai_background_remover')
            ->assertJsonPath('data.sub_tool_id', 22)
            ->assertJsonPath('data.files.0.content_type', 'image/png');

        $previewUrl = $response->json('data.files.0.preview_url');
        $downloadUrl = $response->json('data.files.0.download_url');
        $this->assertStringContainsString('/api/v1/background-remover/files/', $previewUrl);
        $this->assertStringEndsWith('/preview', $previewUrl);
        $this->assertStringEndsWith('/download', $downloadUrl);
        $this->assertStringNotContainsString(self::INTERNAL_KEY, $response->getContent());
        $this->assertStringNotContainsString(self::AI_BASE_URL, $response->getContent());

        $this->withHeaders(['X-API-KEY' => self::API_KEY])
            ->getJson('/api/v1/conversation/'.$conversation->uuid)
            ->assertOk()
            ->assertJsonPath('data.message.1.metadata.images.0.content_type', 'image/png');

        $file = GeneratedImage::firstOrFail();
        $this->assertSame(22, $file->sub_tool_id);
        Storage::disk('public')->assertExists($file->path);
        $this->assertSame(9_400, Wallet::where('user_id', $user->id)->value('balance'));
        $this->assertDatabaseHas('cost_loggers', [
            'conversation_id' => $conversation->id,
            'sub_tool_id' => 22,
            'total_cost' => 0.0006,
            'currency' => 'USD',
        ]);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'POST'
                && $request->url() === self::AI_BASE_URL.'/tasks/background-remover'
                && str_starts_with(strtolower($request->header('Content-Type')[0] ?? ''), 'multipart/form-data')
                && ($request->header('x-internal-api-key')[0] ?? null) === self::INTERNAL_KEY;
        });
    }

    public function test_background_remover_files_are_owner_protected(): void
    {
        [$owner, $conversation] = $this->makeContext();
        $this->fakeSuccessfulRemoval();
        Sanctum::actingAs($owner);
        $response = $this->sendRemoval($conversation);
        $previewUrl = $response->json('data.files.0.preview_url');

        Sanctum::actingAs(User::factory()->create());
        $this->get($previewUrl)->assertForbidden();
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
            'id' => 22,
            'main_tool_id' => $mainTool->id,
            'name' => 'AI Background Remover '.Str::random(6),
            'slug' => 'ai-background-remover-'.Str::random(6),
            'endpoint' => 'tasks/background-remover',
            'config' => [
                'tool_key' => 'ai_background_remover',
                'endpoint' => 'tasks/background-remover',
            ],
        ]);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => $subTool->id,
            'uuid' => (string) Str::uuid(),
        ]);
        Wallet::create([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'balance' => 10_000,
            'payback_balance' => 0,
            'is_active' => true,
        ]);

        return [$user, $conversation];
    }

    private function sendRemoval(Conversation $conversation)
    {
        return $this->withHeaders(['X-API-KEY' => self::API_KEY])
            ->post('/api/v1/message/send', [
                'file' => UploadedFile::fake()->image('LEGO_logo.svg.webp'),
                'conversation_uuid' => $conversation->uuid,
                'sub_tool_id' => 22,
                'user_message' => 'Remove the background from the uploaded image and return a transparent PNG.',
                'tool' => 'ai_background_remover',
                'tool_key' => 'ai_background_remover',
                'model_key' => 'background_remover',
                'state' => json_encode(['provider' => null, 'last_output' => null]),
                'idempotency_key' => (string) Str::uuid(),
            ]);
    }

    private function fakeSuccessfulRemoval(): void
    {
        Http::fake([
            self::AI_BASE_URL.'/tasks/background-remover' => Http::response([
                'success' => true,
                'type' => 'result',
                'tool' => 'ai_background_remover',
                'provider' => 'runware',
                'model' => 'runware:112@5',
                'message' => 'Background removed successfully.',
                'files' => [[
                    'file_id' => 'background-file-1',
                    'filename' => 'LEGO_logo.svg-no-background.png',
                    'content_type' => 'image/png',
                    'download_url' => '/tasks/generated-files/download/background-file-1',
                ]],
                'count' => 1,
                'request_id' => (string) Str::uuid(),
                'cost' => [
                    'total_cost' => 0.0006,
                    'currency' => 'USD',
                ],
                'metadata' => ['provider_cost_usd' => 0.0006],
            ]),
            self::AI_BASE_URL.'/tasks/generated-files/download/background-file-1' => Http::response(
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);
    }
}
