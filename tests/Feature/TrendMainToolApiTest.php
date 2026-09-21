<?php

namespace Tests\Feature;

use App\Models\MainTools;
use App\Models\SubTools;
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
                'database/migrations/2026_04_18_132047_create_sub_tools_table.php',
                'database/migrations/2026_04_18_135455_create_main_tool_tranlations_table.php',
                'database/migrations/2026_04_18_135743_create_sub_tool_tranlations_table.php',
                'database/migrations/2026_05_13_091135_add_endpoint_table.php',
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

    public function test_home_endpoint_returns_six_translated_subtools_from_main_tool_seven(): void
    {
        $trendTool = $this->createTrendTool();
        $trendTool->translations()->create([
            'locale' => 'ar',
            'name' => 'الترندات',
            'description' => 'أدوات الترند',
        ]);

        foreach (range(1, 7) as $index) {
            $subTool = SubTools::create([
                'id' => 20 + $index,
                'main_tool_id' => 7,
                'name' => "Trend {$index}",
                'slug' => "trend-{$index}",
                'image' => "trends/trend-{$index}.webp",
                'endpoint' => "tasks/trends/trend-{$index}",
                'is_active' => true,
                'sort_order' => $index,
            ]);
            $subTool->translations()->create([
                'locale' => 'ar',
                'name' => "أداة ترند {$index}",
                'description' => "وصف أداة ترند {$index}",
            ]);
        }

        $this->apiRequest('ar')->get('/api/v1/home/trend-tools')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.main_tool.id', 7)
            ->assertJsonPath('data.main_tool.name', 'الترندات')
            ->assertJsonCount(6, 'data.tools')
            ->assertJsonPath('data.tools.0.id', 21)
            ->assertJsonPath('data.tools.0.name', 'أداة ترند 1')
            ->assertJsonPath('data.tools.0.slug', 'trend-1')
            ->assertJsonPath('data.tools.0.image', 'trends/trend-1.webp')
            ->assertJsonPath('data.tools.0.endpoint', 'tasks/trends/trend-1')
            ->assertJsonMissingPath('data.tools.6');
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
