<?php

namespace Tests\Feature;

use App\Jobs\TranslateFreeAiModelJob;
use App\Models\MainFreeAiModels;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FreeAiModelAdminModuleTest extends TestCase
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
            ],
        ])->assertExitCode(0);
    }

    public function test_admin_can_manage_free_ai_models(): void
    {
        Storage::fake('public');
        Queue::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin, ['admin']);

        $createResponse = $this->adminRequest()->post('/api/admin/free-ai-models', [
            'name' => 'Free Model',
            'description' => 'A useful free AI model.',
            'image' => UploadedFile::fake()->image('model.jpg'),
            'is_active' => '1',
            'sort_order' => '10',
        ]);

        $createResponse->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('message', 'Free AI model created successfully.')
            ->assertJsonPath('data.name', 'Free Model');

        $model = MainFreeAiModels::firstOrFail();

        $this->assertMatchesRegularExpression('/^free-model-[A-Za-z0-9]{6}$/', $model->slug);
        $this->assertSame('Free Model', $model->meta_name);
        $this->assertStringContainsString('A useful free AI model.', $model->meta_description);
        Storage::disk('public')->assertExists($model->image);
        Queue::assertPushed(
            TranslateFreeAiModelJob::class,
            fn (TranslateFreeAiModelJob $job): bool => $job->modelId === $model->id
        );

        $model->translations()->create([
            'locale' => 'en',
            'name' => 'Free Model',
            'description' => 'Translated description.',
        ]);

        $this->assertDatabaseHas('main_free_ai_models_translations', [
            'main_free_ai_models_id' => $model->id,
            'locale' => 'en',
        ]);

        $this->adminRequest()->get("/api/admin/free-ai-models/{$model->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $model->id)
            ->assertJsonPath('data.translations.0.main_free_ai_models_id', $model->id);

        $originalImage = $model->image;

        $this->adminRequest()->post("/api/admin/free-ai-models/{$model->id}", [
            'name' => 'Free Model',
            'description' => 'Updated without replacing the image.',
            'is_active' => '0',
            'sort_order' => '7',
        ])->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.sort_order', 7);

        $model->refresh();
        $this->assertSame($originalImage, $model->image);
        $this->assertFalse($model->is_active);

        $this->adminRequest()->post("/api/admin/free-ai-models/{$model->id}", [
            'name' => 'Free Model',
            'description' => 'Updated with a replacement image.',
            'image' => UploadedFile::fake()->image('replacement.png'),
            'is_active' => '1',
            'sort_order' => '7',
        ])->assertOk();

        $model->refresh();
        $this->assertNotSame($originalImage, $model->image);
        $this->assertTrue($model->is_active);
        Storage::disk('public')->assertExists($model->image);

        MainFreeAiModels::create([
            'name' => 'First Model',
            'slug' => 'first-model-abcdef',
            'is_active' => false,
            'sort_order' => 1,
        ]);

        $this->adminRequest()->get('/api/admin/free-ai-models')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'First Model')
            ->assertJsonPath('data.0.is_active', false)
            ->assertJsonPath('data.1.name', 'Free Model');

        $this->adminRequest()->post('/api/admin/free-ai-models', [
            'name' => 'Free Model',
            'is_active' => '1',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->adminRequest()->delete("/api/admin/free-ai-models/{$model->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Free AI model deleted successfully.');

        $this->assertSoftDeleted('main_free_ai_models', [
            'id' => $model->id,
        ]);
    }

    private function adminRequest()
    {
        return $this->withHeaders([
            'Accept' => 'application/json',
            'X-API-KEY' => 'testing-api-key',
        ]);
    }
}
