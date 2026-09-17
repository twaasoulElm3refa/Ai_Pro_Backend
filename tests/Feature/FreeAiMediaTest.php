<?php

namespace Tests\Feature;

use App\Models\MainFreeAiModels;
use App\Models\ModelsConverstaions;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FreeAiMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';
        config()->set('services.aiarabic.base_url', 'https://api.aiarabic.com');
        config()->set('services.aiarabic.internal_api_key', 'test-internal-key');
        config()->set('model_catalogs.sources.general_media', [
            'endpoint' => 'https://catalog.example.test/media-models',
            'default_operation' => 'image_generation',
            'operations' => [
                'image_generation' => ['query' => ['operation' => 'image_generation']],
                'resize' => ['query' => ['operation' => 'resize']],
            ],
            'requires_internal_key' => false,
        ]);
    }

    public function test_image_generation_persists_files_cost_and_wallet_charge_atomically(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'catalog.example.test/media-models*' => Http::response([
                'tool' => 'general_media',
                'items' => [[
                    'id' => 7,
                    'provider' => 'runware',
                    'provider_model_id' => 'runware:400@4',
                    'name' => 'Runware Fast Image',
                    'tool_key' => 'general_media',
                    'operation' => 'image_generation',
                    'is_available' => true,
                    'parameter_schema' => [
                        'quality' => ['type' => 'enum', 'values' => ['low', 'high'], 'default' => 'low'],
                        'count' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 4, 'default' => 1],
                    ],
                ]],
            ]),
            'api.aiarabic.com/tasks/general-media' => Http::response([
                'success' => true,
                'tool' => 'general_media',
                'content' => '',
                'files' => [[
                    'file_id' => 'generated-file-id',
                    'filename' => 'skyline.webp',
                    'content_type' => 'image/webp',
                    'url' => 'https://cdn.example.test/skyline.webp',
                ]],
                'cost' => ['total_cost' => '0.000007'],
            ]),
        ]);

        $user = User::factory()->create();
        $model = MainFreeAiModels::create([
            'name' => 'Images & Video',
            'slug' => 'images-video',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $conversation = ModelsConverstaions::create([
            'user_id' => $user->id,
            'model_id' => $model->id,
            'uuid' => (string) Str::uuid(),
            'catalog_operation' => 'image_generation',
            'selected_model_source' => 'general_media',
            'selected_model_id' => 7,
            'provider_model_id' => 'runware:400@4',
            'selected_model_name' => 'Runware Fast Image',
        ]);
        $wallet = Wallet::create([
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'balance' => 10,
            'payback_balance' => 0,
            'is_active' => true,
        ]);
        Sanctum::actingAs($user);
        $requestId = (string) Str::uuid();
        $payload = [
            'user_id' => $user->id,
            'model_id' => $model->id,
            'selected_model_id' => 7,
            'conversation_uuid' => $conversation->uuid,
            'user_message' => 'Create a skyline',
            'state' => [
                'operation' => 'image_generation',
                'parameters' => ['quality' => 'high', 'count' => 2],
            ],
            'debug' => true,
            'request_id' => $requestId,
        ];
        $url = "/api/v1/free-ai-models/images-video/conversations/{$conversation->uuid}/messages?catalog_operation=image_generation";

        $this->withHeaders(['Accept' => 'application/json', 'X-API-KEY' => 'testing-api-key'])
            ->post($url, ['payload' => json_encode($payload, JSON_THROW_ON_ERROR)])
            ->assertOk()
            ->assertJsonPath('data.assistant_message.content', 'Media generated successfully.')
            ->assertJsonPath('data.assistant_message.metadata.operation', 'image_generation')
            ->assertJsonPath('data.assistant_message.metadata.files.0.filename', 'skyline.webp')
            ->assertJsonPath('data.wallet.balance', 3);

        $this->assertSame(3, (int) $wallet->fresh()->balance);
        $this->assertDatabaseHas('models_cost_loggers', [
            'request_id' => $requestId,
            'provider' => 'runware',
            'provider_model_id' => 'runware:400@4',
            'total_tokens' => 7,
        ]);
        $this->assertDatabaseHas('wallet_transactions', [
            'type' => 'debit',
            'points' => 7,
            'balance_before' => 10,
            'balance_after' => 3,
        ]);
        $this->withHeaders(['Accept' => 'application/json', 'X-API-KEY' => 'testing-api-key'])
            ->get($url)
            ->assertOk()
            ->assertJsonPath('data.items.1.metadata.files.0.file_id', 'generated-file-id');

        Http::assertSent(fn ($request) => $request->url() === 'https://api.aiarabic.com/tasks/general-media'
            && $request->hasHeader('x-internal-api-key', 'test-internal-key'));
    }
}
