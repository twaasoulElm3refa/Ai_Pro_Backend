<?php

namespace Tests\Feature;

use App\Models\MainFreeAiModels;
use App\Models\MainTools;
use Tests\TestCase;

class HomeToolsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';

        $this->artisan('migrate', [
            '--path' => [
                'database/migrations/2026_04_18_131903_create_main_tools_table.php',
                'database/migrations/2026_04_18_135455_create_main_tool_tranlations_table.php',
                'database/migrations/2026_08_15_105532_create_main_free_ai_models_table.php',
                'database/migrations/2026_08_15_105804_create_main_free_ai_models_translations_table.php',
            ],
        ])->assertExitCode(0);
    }

    public function test_main_tool_seven_is_returned_with_the_requested_translation(): void
    {
        $trendTool = MainTools::create([
            'id' => 7,
            'name' => 'Trend Tools',
            'description' => 'Base description',
            'slug' => 'trend-tools',
            'image' => 'tools/trends.webp',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $trendTool->translations()->createMany([
            [
                'locale' => 'en',
                'name' => 'Trend Tools',
                'description' => 'English trend description',
            ],
            [
                'locale' => 'fr',
                'name' => 'Outils tendance',
                'description' => 'Description française',
            ],
        ]);

        $this->apiRequest('fr')->get('/api/v1/tools')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.0.id', 7)
            ->assertJsonPath('data.0.slug', 'trend-tools')
            ->assertJsonPath('data.0.image', 'tools/trends.webp')
            ->assertJsonPath('data.0.translation.locale', 'fr')
            ->assertJsonPath('data.0.translation.name', 'Outils tendance')
            ->assertJsonPath('data.0.translation.description', 'Description française')
            ->assertJsonMissingPath('data.0.translations');
    }

    public function test_main_tools_use_the_configured_fallback_translation(): void
    {
        $trendTool = MainTools::create([
            'id' => 7,
            'name' => 'Trend Tools',
            'slug' => 'trend-tools',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $trendTool->translations()->create([
            'locale' => 'en',
            'name' => 'Trend Tools',
            'description' => 'English fallback description',
        ]);

        $this->apiRequest('ru')->get('/api/v1/tools')
            ->assertOk()
            ->assertJsonPath('data.0.translation.locale', 'en')
            ->assertJsonPath('data.0.translation.name', 'Trend Tools')
            ->assertJsonPath('data.0.translation.description', 'English fallback description');
    }

    public function test_ai_models_endpoint_returns_every_active_tool_in_display_order(): void
    {
        foreach (range(1, 6) as $position) {
            $tool = MainFreeAiModels::create([
                'name' => "Trend {$position}",
                'description' => "Base description {$position}",
                'slug' => "trend-{$position}",
                'image' => "free-ai-models/trend-{$position}.webp",
                'is_active' => true,
                'sort_order' => $position,
            ]);

            $tool->translations()->create([
                'locale' => 'en',
                'name' => "Translated trend {$position}",
                'description' => "Translated description {$position}",
            ]);
        }

        MainFreeAiModels::create([
            'name' => 'Inactive trend',
            'slug' => 'inactive-trend',
            'is_active' => false,
            'sort_order' => 0,
        ]);

        $this->apiRequest('en')->get('/api/v1/tools/ai-tools')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.slug', 'trend-1')
            ->assertJsonPath('data.0.image', 'free-ai-models/trend-1.webp')
            ->assertJsonPath('data.0.translation.name', 'Translated trend 1')
            ->assertJsonPath('data.5.slug', 'trend-6');
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
