<?php

use App\Http\Middleware\EnsureNiatReviewer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Production runs behind Caddy in a private Docker network. Trusting
        // that proxy makes Laravel see the original HTTPS scheme and client IP
        // for secure cookies, generated URLs, and per-IP rate limits.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES') ?: null
        );

        $middleware->statefulApi();

        $middleware->alias([
            'niat.reviewer' => EnsureNiatReviewer::class,
        ]);

        $middleware->redirectGuestsTo(
            function (Request $request): ?string {
                if (
                    $request->is('api/*')
                    || $request->expectsJson()
                ) {
                    return null;
                }

                return '/niat/login';
            }
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            function (
                Request $request,
                Throwable $exception
            ): bool {
                return $request->is('api/*')
                    || $request->expectsJson();
            }
        );
    })
    ->create();
