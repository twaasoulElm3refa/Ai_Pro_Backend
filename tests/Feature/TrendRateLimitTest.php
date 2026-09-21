<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

class TrendRateLimitTest extends TestCase
{
    public function test_only_configured_trend_image_routes_use_the_higher_named_limiter(): void
    {
        foreach ([
            'trends.cup-lift',
            'trends.cup-lifting-moment',
            'trends.locker-room',
            'trends.players-tunnel',
            'trends.paparazzi',
            'trends.80s-photo',
            'trends.meet-past-self',
        ] as $routeName) {
            $route = RouteFacade::getRoutes()->getByName($routeName);

            $this->assertNotNull($route);
            $this->assertContains('auth:sanctum', $route->gatherMiddleware());
            $this->assertContains('throttle:trend-image-generation', $route->gatherMiddleware());
        }

        $this->assertSame(
            [28, 29, 30, 31, 32, 33],
            array_values(array_column(config('trends.tools'), 'sub_tool_id'))
        );
        $this->assertSame('image_edit', config('trends.tools.80s-photo.operation'));
        $this->assertTrue(config('trends.tools.80s-photo.send_trend_parameter'));
    }

    public function test_trend_limiter_is_per_user_with_minute_and_short_burst_limits(): void
    {
        $user = new User(['id' => 123]);
        $request = Request::create('/api/v1/tasks/trends/paparazzi', 'POST');
        $request->setUserResolver(fn (): User => $user);
        $route = RouteFacade::getRoutes()->getByName('trends.paparazzi');
        $request->setRouteResolver(fn (): Route => $route);

        $limits = RateLimiter::limiter('trend-image-generation')($request);

        $this->assertCount(2, $limits);
        $this->assertSame(30, $limits[0]->maxAttempts);
        $this->assertSame(60, $limits[0]->decaySeconds);
        $this->assertSame('trend-image:user:123:minute', $limits[0]->key);
        $this->assertSame(5, $limits[1]->maxAttempts);
        $this->assertSame(1, $limits[1]->decaySeconds);
        $this->assertSame('trend-image:user:123:burst', $limits[1]->key);
    }

    public function test_unrecognized_routes_do_not_receive_the_higher_limit(): void
    {
        $request = Request::create('/api/v1/other-tool', 'POST');
        $request->setRouteResolver(fn (): Route => (new Route('POST', '/other-tool', fn () => null))
            ->name('other-tool'));

        $limits = RateLimiter::limiter('trend-image-generation')($request);

        $this->assertCount(1, $limits);
        $this->assertSame(10, $limits[0]->maxAttempts);
        $this->assertSame(60, $limits[0]->decaySeconds);
    }
}
