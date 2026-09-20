<?php

namespace Tests\Feature;

use App\Models\MainFreeAiModels;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AiModelsPageRateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('API_KEY=testing-api-key');
        $_ENV['API_KEY'] = 'testing-api-key';
        $_SERVER['API_KEY'] = 'testing-api-key';
    }

    public function test_ai_models_data_route_has_its_own_limiter_only(): void
    {
        $aiModelsRoute = collect(Route::getRoutes())->first(
            fn ($route) => $route->uri() === 'api/v1/tools/ai-tools'
        );
        $toolsIndexRoute = collect(Route::getRoutes())->first(
            fn ($route) => $route->uri() === 'api/v1/tools'
        );

        $this->assertNotNull($aiModelsRoute);
        $this->assertContains('throttle:ai-models-page', $aiModelsRoute->gatherMiddleware());
        $this->assertNotContains('throttle:30,1', $aiModelsRoute->gatherMiddleware());

        $this->assertNotNull($toolsIndexRoute);
        $this->assertContains('throttle:30,1', $toolsIndexRoute->gatherMiddleware());
    }

    public function test_ai_models_limiter_allows_sixty_requests_per_minute_per_identity(): void
    {
        $limiter = RateLimiter::limiter('ai-models-page');

        $guestRequest = Request::create('/api/v1/tools/ai-tools', 'GET', server: [
            'REMOTE_ADDR' => '203.0.113.10',
        ]);
        $guestLimit = $limiter($guestRequest);

        $user = new User;
        $user->id = 42;
        $userRequest = Request::create('/api/v1/tools/ai-tools');
        $userRequest->setUserResolver(fn () => $user);
        $userLimit = $limiter($userRequest);

        $this->assertSame(60, $guestLimit->maxAttempts);
        $this->assertSame(60, $guestLimit->decaySeconds);
        $this->assertSame('ai-models:ip:203.0.113.10', $guestLimit->key);
        $this->assertSame(60, $userLimit->maxAttempts);
        $this->assertSame('ai-models:user:42', $userLimit->key);
    }

    public function test_ai_models_endpoint_returns_429_after_sixty_requests(): void
    {
        $this->artisan('migrate', [
            '--path' => [
                'database/migrations/2026_08_15_105532_create_main_free_ai_models_table.php',
                'database/migrations/2026_08_15_105804_create_main_free_ai_models_translations_table.php',
            ],
        ])->assertExitCode(0);

        MainFreeAiModels::create([
            'name' => 'Chat and Writing',
            'slug' => 'chat-writing',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        for ($attempt = 1; $attempt <= 60; $attempt++) {
            $this->apiRequest()->get('/api/v1/tools/ai-tools')->assertOk();
        }

        $this->apiRequest()->get('/api/v1/tools/ai-tools')->assertTooManyRequests();
    }

    public function test_frontend_deduplicates_same_locale_fetches(): void
    {
        $source = file_get_contents(resource_path('js/views/home/ai_tools.vue'));

        $this->assertStringContainsString(
            'if (pendingFetch && pendingLocale === requestedLocale)',
            $source
        );
        $this->assertStringContainsString(
            'if (loadedLocale.value === requestedLocale)',
            $source
        );
    }

    private function apiRequest()
    {
        return $this->withHeaders([
            'Accept' => 'application/json',
            'Accept-Language' => 'en',
            'X-API-KEY' => 'testing-api-key',
        ]);
    }
}
