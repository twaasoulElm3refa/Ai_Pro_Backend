<?php

namespace Tests\Feature;

use App\Models\MainFreeAiModels;
use App\Models\ModelsConverstaions;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FreeAiModelFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';

        $this->artisan('migrate', [
            '--path' => [
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'database/migrations/2026_08_15_105532_create_main_free_ai_models_table.php',
                'database/migrations/2026_08_15_105804_create_main_free_ai_models_translations_table.php',
                'database/migrations/2026_08_16_082611_create_models_converstaions_table.php',
                'database/migrations/2026_08_31_130000_add_selected_catalog_model_to_models_conversations_table.php',
            ],
        ])->assertExitCode(0);
    }

    public function test_public_endpoints_only_return_active_models_with_locale_fallback(): void
    {
        $first = $this->createModel('first-free-model', true, 1);
        $first->translations()->createMany([
            [
                'locale' => 'en',
                'name' => 'English Model',
                'description' => 'English description',
                'meta_title' => 'English meta title',
                'meta_description' => 'English meta description',
                'seo_keywords' => 'english,model',
            ],
            [
                'locale' => 'ar',
                'name' => 'النموذج العربي',
                'description' => null,
                'meta_title' => 'عنوان عربي',
                'meta_description' => null,
                'seo_keywords' => 'عربي,نموذج',
            ],
        ]);

        $second = $this->createModel('second-free-model', true, 2);
        $second->translations()->create([
            'locale' => 'en',
            'name' => 'English fallback',
            'description' => 'Fallback description',
        ]);

        $this->createModel('inactive-free-model', false, 0);

        $this->apiRequest('ar')->get('/api/v1/free-ai-models')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.slug', 'first-free-model')
            ->assertJsonPath('data.0.name', 'النموذج العربي')
            ->assertJsonPath('data.0.meta_title', 'عنوان عربي')
            ->assertJsonPath('data.1.name', 'English fallback');

        $this->apiRequest('ar')->get('/api/v1/free-ai-models/first-free-model')
            ->assertOk()
            ->assertJsonPath('data.name', 'النموذج العربي')
            ->assertJsonPath('data.description', 'English description')
            ->assertJsonPath('data.meta_description', 'English meta description')
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.is_active');

        $this->apiRequest()->get('/api/v1/free-ai-models/inactive-free-model')
            ->assertNotFound();
    }

    public function test_authenticated_user_creates_and_loads_only_their_own_conversation(): void
    {
        $model = $this->createModel('secure-free-model', true, 1);
        $model->translations()->create([
            'locale' => 'en',
            'name' => 'Secure Free Model',
        ]);
        $otherModel = $this->createModel('other-free-model', true, 2);
        $this->createModel('inactive-chat-model', false, 3);
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->apiRequest()->post('/api/v1/free-ai-models/secure-free-model/conversations')
            ->assertUnauthorized();

        Sanctum::actingAs($user);

        $this->apiRequest()->post('/api/v1/free-ai-models/inactive-chat-model/conversations')
            ->assertNotFound();

        $createResponse = $this->apiRequest()->post(
            '/api/v1/free-ai-models/secure-free-model/conversations',
            ['user_id' => $otherUser->id, 'model_id' => $otherModel->id]
        );

        $createResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.model.slug', 'secure-free-model')
            ->assertJsonPath('data.user.id', $user->id);

        $uuid = $createResponse->json('data.uuid');

        $this->assertNotEmpty($uuid);
        $this->assertDatabaseHas('models_conversations', [
            'uuid' => $uuid,
            'user_id' => $user->id,
            'model_id' => $model->id,
            'is_pinned' => false,
            'is_archived' => false,
        ]);

        $conversation = ModelsConverstaions::where('uuid', $uuid)->firstOrFail();
        $this->assertTrue($conversation->user->is($user));
        $this->assertTrue($conversation->model->is($model));

        $this->apiRequest()->get("/api/v1/free-ai-models/secure-free-model/conversations/{$uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuid)
            ->assertJsonPath('data.user.id', $user->id);

        $secondUuid = $this->apiRequest()
            ->post('/api/v1/free-ai-models/secure-free-model/conversations')
            ->assertOk()
            ->json('data.uuid');

        $this->assertNotSame($uuid, $secondUuid);
        $this->assertDatabaseCount('models_conversations', 2);

        Sanctum::actingAs($otherUser);

        $this->apiRequest()->get("/api/v1/free-ai-models/secure-free-model/conversations/{$uuid}")
            ->assertNotFound();

        $this->apiRequest()->get("/api/v1/free-ai-models/other-free-model/conversations/{$uuid}")
            ->assertNotFound();
    }

    public function test_catalog_selection_is_persisted_and_history_is_scoped_to_the_main_tool(): void
    {
        config()->set('model_catalogs.free_ai_tools.chat-writing', 'general_chat');
        config()->set('model_catalogs.sources.general_chat', [
            'endpoint' => 'https://catalog.example.test/tasks/general-tools/general_chat/models',
            'requires_internal_key' => false,
        ]);

        Http::fake([
            'catalog.example.test/*' => Http::response([
                'tool' => 'general_chat',
                'items' => [
                    [
                        'id' => 2,
                        'name' => 'Qwen Free',
                        'provider_model_id' => 'qwen/free',
                        'is_available' => true,
                        'is_recommended' => true,
                        'sort_order' => 1,
                    ],
                    [
                        'id' => 14,
                        'name' => 'GPT-5.6 Sol',
                        'provider_model_id' => 'openai/gpt-5.6-sol',
                        'is_available' => true,
                        'sort_order' => 2,
                    ],
                    [
                        'id' => 99,
                        'name' => 'Unavailable',
                        'provider_model_id' => 'vendor/unavailable',
                        'is_available' => false,
                        'sort_order' => 3,
                    ],
                ],
            ]),
        ]);

        $chatTool = $this->createModel('chat-writing', true, 1);
        $otherTool = $this->createModel('other-tool', true, 2);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $created = $this->apiRequest()->post('/api/v1/free-ai-models/chat-writing/conversations', [
            'catalog_model_id' => 2,
            'provider_model_id' => 'qwen/free',
        ])->assertOk()
            ->assertJsonPath('data.catalog_source', 'general_chat')
            ->assertJsonPath('data.selected_model.id', 2)
            ->assertJsonPath('data.selected_model.name', 'Qwen Free');

        $uuid = $created->json('data.uuid');

        ModelsConverstaions::create([
            'user_id' => $user->id,
            'model_id' => $otherTool->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'is_pinned' => false,
            'is_archived' => false,
        ]);

        $this->apiRequest()->get('/api/v1/free-ai-models/chat-writing/conversations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.uuid', $uuid)
            ->assertJsonPath('data.0.selected_model.name', 'Qwen Free');

        $this->apiRequest()->patch("/api/v1/free-ai-models/chat-writing/conversations/{$uuid}/model", [
            'catalog_model_id' => 14,
            'provider_model_id' => 'openai/gpt-5.6-sol',
        ])->assertOk()
            ->assertJsonPath('data.selected_model.id', 14)
            ->assertJsonPath('data.selected_model.name', 'GPT-5.6 Sol');

        $this->assertDatabaseHas('models_conversations', [
            'uuid' => $uuid,
            'user_id' => $user->id,
            'model_id' => $chatTool->id,
            'selected_model_source' => 'general_chat',
            'selected_model_catalog_id' => 14,
            'selected_provider_model_id' => 'openai/gpt-5.6-sol',
            'selected_model_name' => 'GPT-5.6 Sol',
        ]);

        $this->apiRequest()->post('/api/v1/free-ai-models/chat-writing/conversations')
            ->assertOk()
            ->assertJsonPath('data.selected_model.id', 14)
            ->assertJsonPath('data.selected_model.name', 'GPT-5.6 Sol');

        $this->apiRequest()->patch("/api/v1/free-ai-models/chat-writing/conversations/{$uuid}/model", [
            'catalog_model_id' => 99,
            'provider_model_id' => 'vendor/unavailable',
        ])->assertUnprocessable();

        $this->apiRequest()->delete("/api/v1/free-ai-models/chat-writing/conversations/{$uuid}")
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuid);

        $this->assertSoftDeleted('models_conversations', ['uuid' => $uuid]);
    }

    public static function catalogSources(): array
    {
        return [['general_chat'], ['general_code']];
    }

    #[DataProvider('catalogSources')]
    public function test_catalog_defaults_switching_and_reload_use_the_tools_source(string $source): void
    {
        // An isolated test fixture, not a proposed production coding-tool slug.
        $slug = 'catalog-test-tool';
        config()->set("model_catalogs.free_ai_tools.{$slug}", $source);
        config()->set('services.aiarabic.internal_api_key', 'server-only-test-key');
        $endpoint = config("model_catalogs.sources.{$source}.endpoint");
        $items = [
            ['id' => 30, 'name' => 'Later', 'provider_model_id' => 'fixture/later', 'sort_order' => 30, 'is_available' => true],
            ['id' => 10, 'name' => 'Disabled recommendation', 'provider_model_id' => 'fixture/disabled', 'sort_order' => 1, 'is_available' => false, 'is_recommended' => true],
            ['id' => 20, 'name' => 'Recommended', 'provider_model_id' => 'fixture/recommended', 'sort_order' => 20, 'is_available' => true, 'is_recommended' => true],
            ['id' => 15, 'name' => 'First available', 'provider_model_id' => 'fixture/first', 'sort_order' => 15, 'is_available' => true],
        ];
        Http::preventStrayRequests();
        Http::fake([$endpoint => function () use ($source, &$items) {
            return Http::response(['tool' => $source, 'items' => $items]);
        }]);
        $tool = $this->createModel($slug, true, 1);
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $url = "/api/v1/free-ai-models/{$slug}/conversations";

        $uuid = $this->apiRequest()->postJson($url)->assertOk()
            ->assertJsonPath('data.catalog_source', $source)
            ->assertJsonPath('data.selected_model.id', 20)->json('data.uuid');

        $this->apiRequest()->patchJson("{$url}/{$uuid}/model", [
            'catalog_model_id' => 30, 'provider_model_id' => 'fixture/later',
        ])->assertOk()->assertJsonPath('data.selected_model.name', 'Later');
        $this->assertDatabaseHas('models_conversations', [
            'uuid' => $uuid, 'user_id' => $user->id, 'model_id' => $tool->id,
            'selected_model_source' => $source, 'selected_model_catalog_id' => 30,
            'selected_provider_model_id' => 'fixture/later', 'selected_model_name' => 'Later',
        ]);
        $this->apiRequest()->getJson("{$url}/{$uuid}")->assertOk()
            ->assertJsonPath('data.uuid', $uuid)
            ->assertJsonPath('data.selected_model.source', $source)
            ->assertJsonPath('data.selected_model.provider_model_id', 'fixture/later');
        $this->apiRequest()->postJson($url)->assertOk()->assertJsonPath('data.selected_model.id', 30);

        $this->apiRequest()->patchJson("{$url}/{$uuid}/model", [
            'catalog_model_id' => 10, 'provider_model_id' => 'fixture/disabled',
        ])->assertUnprocessable();
        $this->apiRequest()->patchJson("{$url}/{$uuid}/model", [
            'catalog_model_id' => 30, 'provider_model_id' => 'fixture/wrong-provider',
        ])->assertUnprocessable();

        // Once the remembered model becomes unavailable, select the recommendation.
        $items[0]['is_available'] = false;
        $this->apiRequest()->postJson($url)->assertOk()->assertJsonPath('data.selected_model.id', 20);
        // Without a valid remembered selection or recommendation, respect sort_order.
        $items[2]['is_available'] = false;
        $this->apiRequest()->postJson($url)->assertOk()->assertJsonPath('data.selected_model.id', 15);
        Http::assertNotSent(fn ($request) => $request->url() !== $endpoint);
    }

    public function test_code_default_failure_does_not_select_a_chat_model(): void
    {
        $slug = 'catalog-test-tool'; // Test fixture only; no production slug assumption.
        config()->set("model_catalogs.free_ai_tools.{$slug}", 'general_code');
        config()->set('services.aiarabic.internal_api_key', 'server-only-test-key');
        $endpoint = config('model_catalogs.sources.general_code.endpoint');
        Http::preventStrayRequests();
        Http::fake([$endpoint => Http::response(['message' => 'Unavailable'], 503)]);
        $this->createModel($slug, true, 1);
        Sanctum::actingAs(User::factory()->create());

        $url = "/api/v1/free-ai-models/{$slug}/conversations";
        $uuid = $this->apiRequest()->postJson($url)
            ->assertOk()
            ->assertJsonPath('data.catalog_source', 'general_code')
            ->assertJsonPath('data.selected_model', null)->json('data.uuid');
        $selection = ['catalog_model_id' => 17, 'provider_model_id' => 'fixture/code'];
        $this->apiRequest()->postJson($url, $selection)->assertStatus(502);
        $this->apiRequest()->patchJson("{$url}/{$uuid}/model", $selection)->assertStatus(502);
        $this->apiRequest()->getJson("{$url}/{$uuid}")->assertOk()->assertJsonPath('data.selected_model', null);
        $this->assertDatabaseCount('models_conversations', 1);
        Http::assertNotSent(fn ($request) => $request->url() !== $endpoint);
    }

    private function createModel(string $slug, bool $isActive, int $sortOrder): MainFreeAiModels
    {
        return MainFreeAiModels::create([
            'name' => str_replace('-', ' ', $slug),
            'description' => "Base description for {$slug}",
            'slug' => $slug,
            'is_active' => $isActive,
            'sort_order' => $sortOrder,
        ]);
    }

    private function apiRequest(string $locale = 'en')
    {
        return $this->withHeaders([
            'Accept' => 'application/json',
            'Accept-Language' => $locale,
            'X-API-KEY' => 'testing-api-key',
        ]);
    }
}
