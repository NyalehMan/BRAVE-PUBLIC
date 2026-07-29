<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicApiRateLimitTest extends TestCase
{
    public function test_public_api_routes_have_named_rate_limiters(): void
    {
        $routes = collect(
            app('router')->getRoutes()->getRoutes()
        );

        $mobileLogin = $routes->first(
            fn ($route) =>
                $route->uri() === 'api/mobile/login'
                && in_array('POST', $route->methods(), true)
        );

        $fireRoute = $routes->first(
            fn ($route) =>
                $route->uri() === 'api/fire/route'
                && in_array('POST', $route->methods(), true)
        );

        $this->assertNotNull($mobileLogin);
        $this->assertNotNull($fireRoute);

        $this->assertContains(
            'throttle:mobile-login',
            $mobileLogin->gatherMiddleware()
        );

        $this->assertContains(
            'throttle:fire-route',
            $fireRoute->gatherMiddleware()
        );
    }

    public function test_mobile_login_limiter_blocks_the_sixth_attempt(): void
    {
        Route::post('/__tests/mobile-login-limit', function () {
            return response()->json(['success' => true]);
        })->middleware('throttle:mobile-login');

        $this->withServerVariables([
            'REMOTE_ADDR' => '192.0.2.10',
        ]);

        $payload = [
            'ic_no' => '00-000000',
        ];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/__tests/mobile-login-limit', $payload)
                ->assertOk();
        }

        $this->postJson('/__tests/mobile-login-limit', $payload)
            ->assertStatus(429);
    }

    public function test_fire_route_limiter_blocks_the_eleventh_request(): void
    {
        Route::post('/__tests/fire-route-limit', function () {
            return response()->json(['success' => true]);
        })->middleware('throttle:fire-route');

        $this->withServerVariables([
            'REMOTE_ADDR' => '192.0.2.20',
        ]);

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postJson('/__tests/fire-route-limit')
                ->assertOk();
        }

        $this->postJson('/__tests/fire-route-limit')
            ->assertStatus(429);
    }
}