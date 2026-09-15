<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('mobile-login', function (Request $request): array {
            $ip = $request->ip() ?? 'unknown';

            $identity = Str::lower(
                trim((string) $request->input('ic_no', 'missing'))
            );

            $loginKey = hash('sha256', $identity . '|' . $ip);

            return [
                Limit::perMinute(20)
                    ->by('mobile-login-ip-minute:' . $ip),

                Limit::perMinute(5)
                    ->by('mobile-login-identity-minute:' . $loginKey),

                Limit::perHour(30)
                    ->by('mobile-login-identity-hour:' . $loginKey),
            ];
        });

        RateLimiter::for('fire-route', function (Request $request): array {
            $ip = $request->ip() ?? 'unknown';

            return [
                Limit::perMinute(10)
                    ->by('fire-route-ip-minute:' . $ip),

                Limit::perHour(100)
                    ->by('fire-route-ip-hour:' . $ip),

                Limit::perHour(500)
                    ->by('fire-route-global-hour'),
            ];
        });

        RateLimiter::for('public-reports', function (Request $request): array {
            $ip = $request->ip() ?? 'unknown';

            return [
                Limit::perMinute(5)
                    ->by('public-reports-ip-minute:' . $ip),

                Limit::perHour(30)
                    ->by('public-reports-ip-hour:' . $ip),
            ];
        });
    }
}
