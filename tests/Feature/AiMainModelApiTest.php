<?php

namespace Tests\Feature;

use App\Models\AiMainModel;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AiMainModelApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';

        $this->artisan('migrate', [
            '--path' => [
                'database/migrations/2026_08_16_090458_create_ai_main_models_table.php',
                'database/migrations/2026_08_16_090658_create_ai_main_model_translations_table.php',
            ],
        ])->assertExitCode(0);
    }

    public function test_endpoint_returns_the_single_ai_main_model_in_the_requested_locale(): void
    {
        $model = $this->createAiMainModel();
        $this->createTranslation($model, 'en', 'AI Tools', 'English description');
        $this->createTranslation($model, 'ar', 'أدوات الذكاء الاصطناعي', 'وصف عربي للأدوات.');

        $this->apiRequest('ar')->get('/api/v1/ai-main-model')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $model->id)
            ->assertJsonPath('data.slug', 'ai-tools')
            ->assertJsonPath('data.name', 'أدوات الذكاء الاصطناعي')
            ->assertJsonPath('data.description', 'وصف عربي للأدوات.')
            ->assertJsonPath('data.translation.locale', 'ar')
            ->assertJsonPath('data.translation.name', 'أدوات الذكاء الاصطناعي')
            ->assertJsonPath('data.translation.description', 'وصف عربي للأدوات.');

        $this->apiRequest('en')->get('/api/v1/ai-main-model')
            ->assertOk()
            ->assertJsonPath('data.translation.locale', 'en')
            ->assertJsonPath('data.translation.name', 'AI Tools');
    }

    public function test_endpoint_uses_the_configured_fallback_translation(): void
    {
        $model = $this->createAiMainModel();
        $this->createTranslation($model, 'en', 'AI Tools', 'English fallback description');

        $this->apiRequest('ru')->get('/api/v1/ai-main-model')
            ->assertOk()
            ->assertJsonPath('data.id', $model->id)
            ->assertJsonPath('data.translation.locale', 'en')
            ->assertJsonPath('data.translation.description', 'English fallback description');
    }

    private function createAiMainModel(): AiMainModel
    {
        return AiMainModel::create([
            'name' => 'AI Tools',
            'description' => 'Base description',
            'slug' => 'ai-tools',
        ]);
    }

    private function createTranslation(
        AiMainModel $model,
        string $locale,
        string $name,
        string $description
    ): void {
        DB::table('ai_main_model_translations')->insert([
            'tool_id' => $model->id,
            'locale' => $locale,
            'name' => $name,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function apiRequest(string $locale)
    {
        return $this->withHeaders([
            'Accept' => 'application/json',
            'Accept-Language' => $locale,
            'X-API-KEY' => 'testing-api-key',
        ]);
    }
}
