<?php

use App\Http\Controllers\Auth\NiatAuthController;
use App\Http\Controllers\CadIntakeController;
use App\Http\Controllers\FireIncidentController;
use App\Http\Controllers\FireNewsController;
use App\Http\Controllers\FloodIncidentController;
use App\Http\Controllers\MobileAuthController;
use App\Http\Controllers\OperatorPublicReportController;
use App\Http\Controllers\PublicIncidentController;
use App\Http\Controllers\PublicIncidentReportController;
use App\Http\Controllers\PublicPsiController;
use App\Http\Controllers\TideController;
use Illuminate\Support\Facades\Route;

Route::get('/public/psi', PublicPsiController::class);

/*
|--------------------------------------------------------------------------
| Public Tide
|--------------------------------------------------------------------------
*/

Route::get(
    '/tide/nearest',
    [TideController::class, 'nearest']
);

/*
|--------------------------------------------------------------------------
| Public Fire Routing
|--------------------------------------------------------------------------
*/

Route::post(
    '/fire/route',
    [FireIncidentController::class, 'route']
)->middleware('throttle:fire-route');

/*
|--------------------------------------------------------------------------
| Fire News
|--------------------------------------------------------------------------
*/

Route::get(
    '/fire-news',
    [FireNewsController::class, 'index']
);

Route::get(
    '/fire-news/image',
    [FireNewsController::class, 'image']
);

/*
|--------------------------------------------------------------------------
| Public Incidents
|--------------------------------------------------------------------------
*/

Route::prefix('public')->group(function () {
    Route::get(
        '/incidents/ongoing',
        [PublicIncidentController::class, 'ongoing']
    );
});

/*
|--------------------------------------------------------------------------
| Mobile Authentication
|--------------------------------------------------------------------------
*/

Route::post(
    '/mobile/login',
    [MobileAuthController::class, 'login']
)->middleware('throttle:mobile-login');

Route::get(
    '/mobile/me',
    [MobileAuthController::class, 'me']
);

/*
|--------------------------------------------------------------------------
| Public Incident Reports
|--------------------------------------------------------------------------
*/

Route::post(
    '/public/reports',
    [PublicIncidentReportController::class, 'store']
)->middleware('throttle:public-reports');

Route::get(
    '/public/my-reports',
    [PublicIncidentReportController::class, 'myReports']
);

/*
|--------------------------------------------------------------------------
| Authenticated SPA Routes
|--------------------------------------------------------------------------
|
| Sanctum accepts the NIAT browser session for first-party frontend calls.
|
*/

Route::middleware(['auth:sanctum', 'niat.reviewer'])->group(function () {
    Route::get(
        '/niat/me',
        [NiatAuthController::class, 'me']
    );

    Route::get(
        '/operator/public-reports',
        [OperatorPublicReportController::class, 'index']
    );

    Route::get(
        '/operator/public-reports/{id}',
        [OperatorPublicReportController::class, 'show']
    )->whereNumber('id');

    Route::post(
        '/operator/public-reports/{id}/status',
        [OperatorPublicReportController::class, 'updateStatus']
    )->whereNumber('id');

    Route::get(
        '/operator/public-reports/{id}/photo',
        [OperatorPublicReportController::class, 'photo']
    )->whereNumber('id');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('cad')->group(function () {
        Route::post(
            '/calls',
            [CadIntakeController::class, 'storeCall']
        );

        Route::post(
            '/intakes',
            [CadIntakeController::class, 'storeIntake']
        );

        Route::post(
            '/intakes/{id}/validate',
            [CadIntakeController::class, 'validateIntake']
        );

        Route::post(
            '/intakes/{id}/submit-to-brave',
            [CadIntakeController::class, 'submitToBrave']
        );

        Route::post(
            '/intakes/{id}/duplicate-check',
            [CadIntakeController::class, 'duplicateCheck']
        );

        Route::get(
            '/queue',
            [CadIntakeController::class, 'queue']
        );
    });

    Route::post(
        '/fire-incidents',
        [FireIncidentController::class, 'store']
    );

    Route::delete(
        '/fire-incidents/{objectId}',
        [FireIncidentController::class, 'destroy']
    )->whereNumber('objectId');

    Route::put(
        '/fire-incidents/{objectId}',
        [FireIncidentController::class, 'update']
    )->whereNumber('objectId');

    Route::post(
        '/flood-incidents',
        [FloodIncidentController::class, 'store']
    );

    Route::put(
        '/flood-incidents/{objectId}',
        [FloodIncidentController::class, 'update']
    )->whereNumber('objectId');

    Route::delete(
        '/flood-incidents/{objectId}',
        [FloodIncidentController::class, 'destroy']
    )->whereNumber('objectId');
});
