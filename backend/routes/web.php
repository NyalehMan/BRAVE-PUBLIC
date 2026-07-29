<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\NiatAuthController;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/{any}', function () {
    return file_get_contents(public_path('index.html'));
})->where('any', '.*');

use App\Http\Controllers\FireNewsController;

Route::get('/fire-news', [FireNewsController::class, 'index']);
Route::get('/fire-news/thumbnail', [FireNewsController::class, 'thumbnail']);

//LOGIN//

Route::post('/login', [NiatAuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::middleware('auth')->group(function () {
    Route::get('/api/niat/me', [NiatAuthController::class, 'me']);
    Route::post('/logout', [NiatAuthController::class, 'logout']);
});