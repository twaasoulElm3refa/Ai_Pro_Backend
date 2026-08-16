<?php

namespace Tests\Feature;

use App\Models\MainFreeAiModels;
use App\Models\ModelsConverstaions;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
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
        $this->assertDatabaseHas('models_converstaions', [
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
        $this->assertDatabaseCount('models_converstaions', 2);

        Sanctum::actingAs($otherUser);

        $this->apiRequest()->get("/api/v1/free-ai-models/secure-free-model/conversations/{$uuid}")
            ->assertNotFound();

        $this->apiRequest()->get("/api/v1/free-ai-models/other-free-model/conversations/{$uuid}")
            ->assertNotFound();
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
