<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\GeneratedImage;
use App\Models\MainTools;
use App\Models\Message;
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

    public function test_cup_lift_uses_subtool_41_downloads_persists_and_restores_image(): void
    {
        [$user, $conversation] = $this->makeContext(41, 'cup-lift');
        $taskId = (string) Str::uuid();
        $this->fakeSuccessfulGeneration('cup-lift', $taskId);
        Sanctum::actingAs($user);

        $response = $this->sendTrend($conversation, 'cup-lift', (string) Str::uuid());

        $response->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.selected_model_id', 46)
            ->assertJsonPath('data.sub_tool_id', 41)
            ->assertJsonPath('data.trend', 'cup-lift')
            ->assertJsonPath('data.files.0.content_type', 'image/png')
            ->assertJsonPath('data.billing.points_to_deduct', 45000)
            ->assertJsonMissingPath('data.provider');

        $this->assertSuccessfulPersistence($user, $conversation, 41, $taskId);

        $reloaded = $this->withHeaders(['X-API-KEY' => self::API_KEY])
            ->getJson('/api/v1/conversation/'.$conversation->uuid);
        $reloaded->assertOk()
            ->assertJsonPath('data.message.1.role', 'assistant')
            ->assertJsonPath('data.message.1.files.0.content_type', 'image/png');

        $previewUrl = $reloaded->json('data.message.1.files.0.preview_url');
        $this->get(parse_url($previewUrl, PHP_URL_PATH))->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->assertProviderRequest('cup-lift');
        $this->assertSecureDownloadRequest();
    }

    public function test_locker_room_uses_subtool_42_and_shared_persistence_flow(): void
    {
        [$user, $conversation] = $this->makeContext(42, 'locker-room');
        $taskId = (string) Str::uuid();
        $this->fakeSuccessfulGeneration('locker-room', $taskId, true, true);
        Sanctum::actingAs($user);

        $response = $this->sendTrend($conversation, 'locker-room', (string) Str::uuid());

        $response->assertOk()
            ->assertJsonPath('data.success', true)
            ->assertJsonPath('data.selected_model_id', 46)
            ->assertJsonPath('data.sub_tool_id', 42)
            ->assertJsonPath('data.trend', 'locker-room')
            ->assertJsonPath('data.billing.points_to_deduct', 45000);

        $this->assertSuccessfulPersistence($user, $conversation, 42, $taskId);
        $this->assertProviderRequest('locker-room');
        $this->assertSecureDownloadRequest();
    }

    public function test_same_idempotency_key_returns_existing_result_without_second_charge(): void
    {
        [$user, $conversation] = $this->makeContext(41, 'cup-lift');
        $this->fakeSuccessfulGeneration('cup-lift', (string) Str::uuid());
        Sanctum::actingAs($user);
        $key = (string) Str::uuid();

        $this->sendTrend($conversation, 'cup-lift', $key)->assertOk();
        $this->sendTrend($conversation, 'cup-lift', $key)->assertOk();

        $this->assertSame(55_000, Wallet::where('user_id', $user->id)->value('balance'));
        $this->assertDatabaseCount('generated_images', 1);
        $this->assertDatabaseCount('messages', 2);
        Http::assertSentCount(2);
    }

    public function test_missing_cost_fails_without_charging_or_persisting_an_image(): void
    {
        [$user, $conversation] = $this->makeContext(42, 'locker-room');
        $this->fakeSuccessfulGeneration('locker-room', (string) Str::uuid(), false);
        Sanctum::actingAs($user);

        $this->sendTrend($conversation, 'locker-room', (string) Str::uuid())->assertStatus(502);

        $this->assertSame(100_000, Wallet::where('user_id', $user->id)->value('balance'));
        $this->assertDatabaseCount('generated_images', 0);
        $this->assertDatabaseCount('cost_loggers', 0);
        $this->assertTrue((bool) Message::where('role', 'assistant')->value('is_error'));
        Http::assertSentCount(1);
    }

    public function test_insufficient_wallet_is_rejected_before_calling_provider(): void
    {
        [$user, $conversation] = $this->makeContext(42, 'locker-room', 0);
        Http::fake();
        Sanctum::actingAs($user);

        $this->sendTrend($conversation, 'locker-room', (string) Str::uuid())
            ->assertStatus(402);

        Http::assertNothingSent();
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_endpoint_rejects_a_conversation_for_the_other_trend(): void
    {
        [$user, $conversation] = $this->makeContext(42, 'locker-room');
        Http::fake();
        Sanctum::actingAs($user);

        $this->sendTrend($conversation, 'cup-lift', (string) Str::uuid())
            ->assertStatus(422);

        Http::assertNothingSent();
        $this->assertDatabaseCount('messages', 0);
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
            'name' => $slug === 'cup-lift' ? 'Cup Lift' : 'Locker Room',
            'slug' => $slug,
            'endpoint' => "tasks/trends/{$slug}",
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

    private function sendTrend(Conversation $conversation, string $slug, string $idempotencyKey)
    {
        return $this->withHeaders(['X-API-KEY' => self::API_KEY])
            ->post("/api/v1/tasks/trends/{$slug}", [
                'payload' => json_encode([
                    'conversation_uuid' => $conversation->uuid,
                    'user_message' => 'Create a realistic football celebration',
                    'selected_model_id' => 46,
                    'state' => ['parameters' => []],
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
        $this->assertDatabaseHas('cost_loggers', [
            'conversation_id' => $conversation->id,
            'sub_tool_id' => $subtoolId,
            'total_tokens' => 45_000,
            'total_cost' => 0.045,
            'provider_request_id' => $taskId,
        ]);
        $image = GeneratedImage::firstOrFail();
        $this->assertSame('local', $image->disk);
        Storage::disk('local')->assertExists($image->path);
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
