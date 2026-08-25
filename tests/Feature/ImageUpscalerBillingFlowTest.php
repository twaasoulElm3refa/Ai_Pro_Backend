<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\MainTools;
use App\Models\SubTools;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImageUpscalerBillingFlowTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'testing-public-api-key';

    private const INTERNAL_KEY = 'testing-internal-upscaler-key';

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

    public function test_successful_upscale_deducts_provider_total_cost(): void
    {
        [$user, $conversation] = $this->makeContext();
        $this->fakeSuccessfulUpscale();
        Sanctum::actingAs($user);

        $this->withHeaders(['X-API-KEY' => self::API_KEY])
            ->post('/api/v1/message/send', [
                'file' => UploadedFile::fake()->image('source.png'),
                'conversation_uuid' => $conversation->uuid,
                'sub_tool_id' => 23,
                'user_message' => 'Upscale this image.',
                'tool' => 'ai_image_upscaler',
                'tool_key' => 'ai_image_upscaler',
                'model_key' => 'image_upscaler',
                'state' => json_encode([
                    'scale' => 2,
                    'face_enhance' => false,
                    'last_output' => null,
                ]),
                'idempotency_key' => (string) Str::uuid(),
            ])
            ->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.sub_tool_id', 23);

        $this->assertSame(9_400, Wallet::where('user_id', $user->id)->value('balance'));
        $this->assertDatabaseHas('cost_loggers', [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'sub_tool_id' => 23,
            'total_cost' => 0.0006,
            'currency' => 'USD',
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
            'id' => 23,
            'main_tool_id' => $mainTool->id,
            'name' => 'AI Image Upscaler '.Str::random(6),
            'slug' => 'ai-image-upscaler-'.Str::random(6),
            'endpoint' => 'tasks/image-upscaler',
            'config' => [
                'tool_key' => 'ai_image_upscaler',
                'endpoint' => 'tasks/image-upscaler',
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

    private function fakeSuccessfulUpscale(): void
    {
        Http::fake([
            self::AI_BASE_URL.'/tasks/image-upscaler' => Http::response([
                'success' => true,
                'type' => 'result',
                'tool' => 'ai_image_upscaler',
                'provider' => 'runware',
                'model' => 'runware:upscaler@1',
                'message' => 'Image upscaled successfully.',
                'files' => [[
                    'file_id' => 'upscaled-file-1',
                    'filename' => 'source-upscaled.png',
                    'content_type' => 'image/png',
                    'download_url' => '/tasks/generated-files/download/upscaled-file-1',
                ]],
                'count' => 1,
                'request_id' => (string) Str::uuid(),
                'cost' => [
                    'total_cost' => 0.0006,
                    'currency' => 'USD',
                ],
            ]),
            self::AI_BASE_URL.'/tasks/generated-files/download/upscaled-file-1' => Http::response(
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);
    }
}
