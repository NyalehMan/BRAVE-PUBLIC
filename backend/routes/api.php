<?php

use App\Http\Controllers\TideController;
use App\Http\Controllers\FireIncidentController;
use App\Http\Controllers\FireNewsController;
use App\Http\Controllers\CadIntakeController;
use App\Http\Controllers\PublicIncidentController;
use App\Http\Controllers\MobileAuthController;
use App\Http\Controllers\PublicIncidentReportController;
use App\Http\Controllers\OperatorPublicReportController;
use Illuminate\Support\Facades\Route;

Route::get('/tide/nearest', [TideController::class, 'nearest']);

Route::delete(
    '/fire-incidents/{objectId}',
    [FireIncidentController::class, 'destroy']
);

Route::put(
    '/fire-incidents/{objectId}',
    [FireIncidentController::class, 'update']
);

Route::post(
    '/fire/route',
    [FireIncidentController::class, 'route']
);

Route::get('/arcgis/token', function () {
    return response()->json([
        'token' => env('ARCGIS_TOKEN'),
        'expires' => now()->addHours(2)->timestamp * 1000,
    ]);
});

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
| CAD
|--------------------------------------------------------------------------
*/

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
);

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
);

Route::get(
    '/public/my-reports',
    [PublicIncidentReportController::class, 'myReports']
);

/*
|--------------------------------------------------------------------------
| Operator Public Reports
|--------------------------------------------------------------------------
*/

Route::get(
    '/operator/public-reports',
    [OperatorPublicReportController::class, 'index']
);

Route::post(
    '/operator/public-reports/{id}/status',
    [OperatorPublicReportController::class, 'updateStatus']
);