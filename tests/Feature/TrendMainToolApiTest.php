<?php

namespace Tests\Feature;

use App\Models\MainTools;
use Tests\TestCase;

class TrendMainToolApiTest extends TestCase
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
            ],
        ])->assertExitCode(0);
    }

    public function test_dedicated_endpoint_returns_only_main_tool_seven_in_the_requested_locale(): void
    {
        $trendTool = $this->createTrendTool();
        $trendTool->translations()->createMany([
            [
                'locale' => 'en',
                'name' => 'Trends',
                'description' => 'English trend description',
            ],
            [
                'locale' => 'ar',
                'name' => 'الترندات',
                'description' => 'أدوات صور الترندات بالذكاء الاصطناعي.',
            ],
        ]);

        $this->apiRequest('ar')->get('/api/v1/main-tools/trend-tools')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', 7)
            ->assertJsonPath('data.slug', 'trends')
            ->assertJsonPath('data.translation.locale', 'ar')
            ->assertJsonPath('data.translation.name', 'الترندات')
            ->assertJsonPath('data.translation.description', 'أدوات صور الترندات بالذكاء الاصطناعي.');

        $this->apiRequest('en')->get('/api/v1/main-tools/trend-tools')
            ->assertOk()
            ->assertJsonPath('data.id', 7)
            ->assertJsonPath('data.translation.locale', 'en')
            ->assertJsonPath('data.translation.name', 'Trends');
    }

    public function test_dedicated_endpoint_uses_english_fallback_without_changing_the_old_index(): void
    {
        MainTools::create([
            'id' => 1,
            'name' => 'Existing Tool',
            'slug' => 'existing-tool',
            'is_active' => true,
        ]);

        $trendTool = $this->createTrendTool();
        $trendTool->translations()->create([
            'locale' => 'en',
            'name' => 'Trends',
            'description' => 'English fallback description',
        ]);

        $this->apiRequest('fr')->get('/api/v1/main-tools/trend-tools')
            ->assertOk()
            ->assertJsonPath('data.id', 7)
            ->assertJsonPath('data.translation.locale', 'en')
            ->assertJsonPath('data.translation.description', 'English fallback description');

        $this->apiRequest('en')->get('/api/v1/tools')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 1);
    }

    private function createTrendTool(): MainTools
    {
        return MainTools::create([
            'id' => 7,
            'name' => 'Trends',
            'description' => 'Base trend description',
            'slug' => 'trends',
            'image' => 'tools/trends.webp',
            'is_active' => false,
            'sort_order' => 7,
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
