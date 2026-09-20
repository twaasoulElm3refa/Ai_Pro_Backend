<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\GeneratedImage;
use App\Models\MainTools;
use App\Models\Message;
use App\Models\SubTools;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AI\DynamicToolConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrendCupLiftFlowTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'testing-public-api-key';
    private const INTERNAL_KEY = 'testing-internal-trend-key';
    private const AI_BASE_URL = 'https://ai.internal.test';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.ai.base_url', self::AI_BASE_URL);
        config()->set('services.ai.internal_api_key', self::INTERNAL_KEY);
        config()->set('services.ai.trends_debug', true);
        putenv('API_KEY='.self::API_KEY);
        $_ENV['API_KEY'] = self::API_KEY;
        $_SERVER['API_KEY'] = self::API_KEY;
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        putenv('API_KEY');
        unset($_ENV['API_KEY'], $_SERVER['API_KEY']);
        parent::tearDown();
    }

    public function test_cup_lift_uses_subtool_28_accepts_image_only_and_restores_both_images(): void
    {
        [$user, $conversation] = $this->makeContext(28, 'cup-lifting-moment');
        $taskId = (string) Str::uuid();
        $this->fakeSuccessfulGeneration('cup-lift', $taskId);
        Sanctum::actingAs($user);

        $response = $this->sendTrend($conversation, 'cup-lifting-moment', (string) Str::uuid(), '');

        $response->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.selected_model_id', 46)
            ->assertJsonPath('data.sub_tool_id', 28)
            ->assertJsonPath('data.trend', 'cup-lifting-moment')
            ->assertJsonPath('data.files.0.content_type', 'image/png')
            ->assertJsonPath('data.files.0.mime_type', 'image/png')
            ->assertJsonPath('data.metadata.sub_tool_id', 28)
            ->assertJsonPath('data.metadata.selected_model_id', 46)
            ->assertJsonPath('data.billing.points_to_deduct', 45000)
            ->assertJsonMissingPath('data.provider');

        $this->assertSuccessfulPersistence($user, $conversation, 28, $taskId);

        $reloaded = $this->withHeaders(['X-API-KEY' => self::API_KEY])
            ->getJson('/api/v1/conversation/'.$conversation->uuid);
        $reloaded->assertOk()
            ->assertJsonPath('data.message.0.role', 'user')
            ->assertJsonPath('data.message.0.content', '')
            ->assertJsonPath('data.message.0.input_image.content_type', 'image/png')
            ->assertJsonPath('data.message.1.role', 'assistant')
            ->assertJsonPath('data.message.1.files.0.content_type', 'image/png');

        $sourcePreviewUrl = $reloaded->json('data.message.0.input_image.preview_url');
        $this->get(parse_url($sourcePreviewUrl, PHP_URL_PATH))->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $previewUrl = $reloaded->json('data.message.1.files.0.preview_url');
        $this->get(parse_url($previewUrl, PHP_URL_PATH))->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->assertProviderRequest('cup-lift');
        $this->assertSecureDownloadRequest();
    }

    public function test_locker_room_uses_subtool_29_and_normalizes_image_url_response(): void
    {
        [$user, $conversation] = $this->makeContext(29, 'locker-room');
        $taskId = (string) Str::uuid();
        $this->fakeSuccessfulGeneration('locker-room', $taskId, true, true);
        Sanctum::actingAs($user);

        $response = $this->sendTrend($conversation, 'locker-room', (string) Str::uuid());

        $response->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.selected_model_id', 46)
            ->assertJsonPath('data.sub_tool_id', 29)
            ->assertJsonPath('data.trend', 'locker-room')
            ->assertJsonPath('data.billing.points_to_deduct', 45000);

        $this->assertSuccessfulPersistence($user, $conversation, 29, $taskId);
        $this->assertProviderRequest('locker-room');
        $this->assertSecureDownloadRequest();
    }

    public function test_same_idempotency_key_returns_existing_result_without_second_charge(): void
    {
        [$user, $conversation] = $this->makeContext(28, 'cup-lifting-moment');
        $this->fakeSuccessfulGeneration('cup-lift', (string) Str::uuid());
        Sanctum::actingAs($user);
        $key = (string) Str::uuid();

        $this->sendTrend($conversation, 'cup-lifting-moment', $key)->assertOk();
        $this->sendTrend($conversation, 'cup-lifting-moment', $key)->assertOk();

        $this->assertSame(55_000, Wallet::where('user_id', $user->id)->value('balance'));
        $this->assertDatabaseCount('generated_images', 2);
        $this->assertDatabaseCount('messages', 2);
        Http::assertSentCount(2);
    }

    public function test_missing_cost_fails_without_charging_or_persisting_generated_output(): void
    {
        [$user, $conversation] = $this->makeContext(29, 'locker-room');
        $this->fakeSuccessfulGeneration('locker-room', (string) Str::uuid(), false);
        Sanctum::actingAs($user);

        $this->sendTrend($conversation, 'locker-room', (string) Str::uuid())->assertStatus(502);

        $this->assertSame(100_000, Wallet::where('user_id', $user->id)->value('balance'));
        $this->assertDatabaseCount('generated_images', 1);
        $this->assertDatabaseCount('cost_loggers', 0);
        $this->assertTrue((bool) Message::where('role', 'assistant')->value('is_error'));
        Http::assertSentCount(1);
    }

    public function test_insufficient_wallet_is_rejected_before_calling_provider(): void
    {
        [$user, $conversation] = $this->makeContext(29, 'locker-room', 0);
        Http::fake();
        Sanctum::actingAs($user);

        $this->sendTrend($conversation, 'locker-room', (string) Str::uuid())
            ->assertStatus(402);

        Http::assertNothingSent();
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_endpoint_rejects_a_conversation_for_the_other_trend(): void
    {
        [$user, $conversation] = $this->makeContext(29, 'locker-room');
        Http::fake();
        Sanctum::actingAs($user);

        $this->sendTrend($conversation, 'cup-lifting-moment', (string) Str::uuid())
            ->assertStatus(422);

        Http::assertNothingSent();
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_missing_file_returns_a_clear_validation_error(): void
    {
        [$user, $conversation] = $this->makeContext(28, 'cup-lifting-moment');
        Sanctum::actingAs($user);

        $this->withHeaders([
            'X-API-KEY' => self::API_KEY,
            'Accept' => 'application/json',
        ])
            ->post('/api/v1/tasks/trends/cup-lifting-moment', [
                'payload' => json_encode([
                    'conversation_uuid' => $conversation->uuid,
                    'sub_tool_id' => 28,
                    'user_message' => '',
                    'selected_model_id' => 46,
                    'state' => ['parameters' => []],
                    'idempotency_key' => (string) Str::uuid(),
                ], JSON_THROW_ON_ERROR),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_null_database_endpoint_uses_the_trends_config_fallback(): void
    {
        [, $conversation] = $this->makeContext(28, 'cup-lifting-moment');
        $subTool = $conversation->subTool;
        $subTool->update(['endpoint' => null]);

        $endpoint = app(DynamicToolConfigService::class)->endpointFor($subTool->fresh());

        $this->assertSame('tasks/trends/cup-lift', $endpoint);
    }

    private function makeContext(int $subtoolId, string $slug, int $balance = 100_000): array
    {
        $user = User::factory()->create();
        $mainTool = MainTools::create([
            'id' => 7,
            'name' => 'Trends',
            'slug' => 'trends-'.Str::random(6),
        ]);
        $subTool = SubTools::create([
            'id' => $subtoolId,
            'main_tool_id' => $mainTool->id,
            'name' => $slug === 'cup-lifting-moment' ? 'Cup Lift Moment' : 'Locker Room',
            'slug' => $slug,
            'endpoint' => $slug === 'cup-lifting-moment'
                ? 'tasks/trends/cup-lift'
                : "tasks/trends/{$slug}",
            'is_active' => true,
            'allowed_model_ids' => json_encode([46], JSON_THROW_ON_ERROR),
        ]);
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => $subTool->id,
            'uuid' => (string) Str::uuid(),
        ]);
        Wallet::create([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'balance' => $balance,
            'payback_balance' => 0,
            'is_active' => true,
        ]);

        return [$user, $conversation];
    }

    private function sendTrend(
        Conversation $conversation,
        string $slug,
        string $idempotencyKey,
        string $message = 'Create a realistic football celebration'
    )
    {
        return $this->withHeaders(['X-API-KEY' => self::API_KEY])
            ->post("/api/v1/tasks/trends/{$slug}", [
                'payload' => json_encode([
                    'conversation_uuid' => $conversation->uuid,
                    'sub_tool_id' => $conversation->sub_tool_id,
                    'user_message' => $message,
                    'selected_model_id' => 46,
                    'state' => ['parameters' => []],
                    'debug' => true,
                    'idempotency_key' => $idempotencyKey,
                ], JSON_THROW_ON_ERROR),
                'file' => UploadedFile::fake()->createWithContent(
                    'portrait.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
                ),
            ]);
    }

    private function fakeSuccessfulGeneration(
        string $slug,
        string $taskId,
        bool $includeCost = true,
        bool $nestedProviderResponse = false
    ): void {
        $providerResponse = [
            'taskType' => 'imageInference',
            'imageUUID' => (string) Str::uuid(),
            'taskUUID' => $taskId,
        ];
        if ($includeCost) {
            $providerResponse['cost'] = 0.045;
        }

        $response = [
            'success' => true,
            'provider' => 'runware',
            'model' => 'bfl:5@1',
            'selected_model_id' => 46,
            'operation' => 'image_edit',
            'files' => [[
                'file_id' => 'trend-file-1',
                'filename' => 'general-media-image_edit.webp',
                'content_type' => 'image/webp',
                'download_url' => '/tasks/generated-files/download/trend-file-1',
            ]],
        ];
        if ($nestedProviderResponse) {
            $response['metadata']['provider_response'] = $providerResponse;
            $response['images'] = [[
                'id' => 'trend-file-1',
                'filename' => 'general-media-image_edit.webp',
                'mime_type' => 'image/webp',
                'image_url' => '/tasks/generated-files/download/trend-file-1',
            ]];
            unset($response['files']);
        } else {
            $response['provider_response'] = $providerResponse;
        }

        Http::fake([
            self::AI_BASE_URL."/tasks/trends/{$slug}" => Http::response($response),
            self::AI_BASE_URL.'/tasks/generated-files/download/trend-file-1' => Http::response(
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='),
                200,
                ['Content-Type' => 'image/png']
            ),
        ]);
    }

    private function assertSuccessfulPersistence(
        User $user,
        Conversation $conversation,
        int $subtoolId,
        string $taskId
    ): void {
        $this->assertSame(55_000, Wallet::where('user_id', $user->id)->value('balance'));
        $this->assertDatabaseCount('generated_images', 2);
        $this->assertDatabaseHas('cost_loggers', [
            'conversation_id' => $conversation->id,
            'sub_tool_id' => $subtoolId,
            'total_tokens' => 45_000,
            'total_cost' => 0.045,
            'provider_request_id' => $taskId,
        ]);
        foreach (GeneratedImage::all() as $image) {
            $this->assertSame('local', $image->disk);
            Storage::disk('local')->assertExists($image->path);
        }
    }

    private function assertProviderRequest(string $slug): void
    {
        Http::assertSent(function (Request $request) use ($slug): bool {
            if ($request->method() !== 'POST' || $request->url() !== self::AI_BASE_URL."/tasks/trends/{$slug}") {
                return false;
            }

            return ($request->header('x-internal-api-key')[0] ?? null) === self::INTERNAL_KEY
                && str_starts_with(strtolower($request->header('Content-Type')[0] ?? ''), 'multipart/form-data');
        });
    }

    private function assertSecureDownloadRequest(): void
    {
        Http::assertSent(fn (Request $request): bool =>
            $request->method() === 'GET'
            && $request->url() === self::AI_BASE_URL.'/tasks/generated-files/download/trend-file-1'
            && ($request->header('x-internal-api-key')[0] ?? null) === self::INTERNAL_KEY
        );
    }
}
