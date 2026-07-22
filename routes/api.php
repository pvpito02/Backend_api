<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DepartementController;
use App\Http\Controllers\Api\PointageAnomalieController;
use App\Http\Controllers\Api\PointageController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Mairie de Sandiara (Pointage)
|--------------------------------------------------------------------------
|
| Auth : Sanctum Bearer tokens
| Autorisation : Policies
|
*/

Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'service' => 'Backend_api',
        'app' => 'Pointage Mairie de Sandiara',
        'version' => '0.5.0',
    ]);
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('users', UserController::class);
    Route::apiResource('departements', DepartementController::class);
    Route::apiResource('agents', AgentController::class);
    Route::apiResource('sites', SiteController::class);

    // Pointages — routes custom avant apiResource
    Route::get('/pointages/today', [PointageController::class, 'today']);
    Route::post('/pointages/scan', [PointageController::class, 'scan']);
    Route::post('/pointages/sync', [PointageController::class, 'sync']);
    Route::post('/pointages/{pointage}/acknowledge', [PointageController::class, 'acknowledge']);
    Route::post('/pointages/{pointage}/anomalies', [PointageController::class, 'reportAnomalie']);
    Route::apiResource('pointages', PointageController::class);

    Route::get('/pointage-anomalies', [PointageAnomalieController::class, 'index']);
    Route::post('/pointage-anomalies/{pointageAnomalie}/resolve', [PointageAnomalieController::class, 'resolve']);
});
