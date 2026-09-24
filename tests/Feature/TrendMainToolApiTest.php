<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\GeneratedImage;
use App\Models\MainTools;
use App\Models\Message;
use App\Models\SubTools;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
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
                'database/migrations/0001_01_01_000000_create_users_table.php',
                'database/migrations/2026_04_18_131903_create_main_tools_table.php',
                'database/migrations/2026_04_18_132047_create_sub_tools_table.php',
                'database/migrations/2026_04_18_132509_create_conversations_table.php',
                'database/migrations/2026_04_18_133026_create_messages_table.php',
                'database/migrations/2026_04_18_135455_create_main_tool_tranlations_table.php',
                'database/migrations/2026_04_18_135743_create_sub_tool_tranlations_table.php',
                'database/migrations/2026_05_13_091135_add_endpoint_table.php',
                'database/migrations/2026_07_30_000000_create_generated_images_table.php',
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

        $user = User::factory()->create();
        $conversation = Conversation::create([
            'user_id' => $user->id,
            'sub_tool_id' => 21,
            'uuid' => (string) Str::uuid(),
        ]);
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'content' => 'Generated trend result',
            'role' => 'assistant',
            'is_error' => false,
        ]);
        $publicId = (string) Str::uuid();
        Storage::fake('local');
        $generatedImage = GeneratedImage::create([
            'public_id' => $publicId,
            'user_id' => $user->id,
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'sub_tool_id' => 21,
            'filename' => 'trend-result.webp',
            'path' => "generated-images/{$user->id}/{$publicId}.webp",
            'disk' => 'local',
            'content_type' => 'image/webp',
            'size_bytes' => 1024,
        ]);
        Storage::disk('local')->put($generatedImage->path, 'fake-webp-content');

        $response = $this->apiRequest('ar')->get('/api/v1/home/trend-tools');

        $response
            ->assertOk()
            ->assertJsonPath('data.tools.0.image', null);

        Sanctum::actingAs($user, [], 'sanctum');

        $response = $this->apiRequest('ar')->get('/api/v1/home/trend-tools');

        $response
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.main_tool.id', 7)
            ->assertJsonPath('data.main_tool.name', 'الترندات')
            ->assertJsonCount(6, 'data.tools')
            ->assertJsonPath('data.tools.0.id', 21)
            ->assertJsonPath('data.tools.0.name', 'أداة ترند 1')
            ->assertJsonPath('data.tools.0.slug', 'trend-1')
            ->assertJsonPath('data.tools.0.image.id', $publicId)
            ->assertJsonPath('data.tools.0.image.content_type', 'image/webp')
            ->assertJsonPath('data.tools.1.image', null)
            ->assertJsonPath('data.tools.0.endpoint', 'tasks/trends/trend-1')
            ->assertJsonMissingPath('data.tools.6');

        $previewUrl = $response->json('data.tools.0.image.preview_url');
        $this->assertStringStartsWith(
            "/api/v1/generated-images/{$publicId}/home-preview?",
            $previewUrl
        );
        $this->get($previewUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('Cache-Control', 'immutable, max-age=18000, private');
        $this->get(strtok($previewUrl, '?'))->assertForbidden();
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
