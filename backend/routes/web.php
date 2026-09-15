<?php

use App\Http\Controllers\Auth\NiatAuthController;
use App\Http\Controllers\FireNewsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Fire News
|--------------------------------------------------------------------------
*/

Route::get(
    '/fire-news',
    [FireNewsController::class, 'index']
);

Route::get(
    '/fire-news/thumbnail',
    [FireNewsController::class, 'thumbnail']
);

/*
|--------------------------------------------------------------------------
| NIAT Authentication
|--------------------------------------------------------------------------
*/

Route::post(
    '/login',
    [NiatAuthController::class, 'login']
)->middleware('throttle:5,1');

Route::middleware('auth')->group(function () {
    Route::post(
        '/logout',
        [NiatAuthController::class, 'logout']
    );
});

/*
|--------------------------------------------------------------------------
| Vue SPA Catch-All
|--------------------------------------------------------------------------
|
| This must remain the final route so it does not intercept backend routes.
|
*/

Route::get('/{any}', function () {
    return response()->file(public_path('index.html'));
})->where('any', '^(?!(?:api|storage)(?:/|$)).*');
