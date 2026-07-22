<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DepartementController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Mairie de Sandiara (Pointage)
|--------------------------------------------------------------------------
|
| Auth : Sanctum Bearer tokens
| Autorisation fine : Policies (UserPolicy, AgentPolicy, DepartementPolicy)
| Middleware role: conservé pour les gardes rapides optionnels
|
*/

Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'service' => 'Backend_api',
        'app' => 'Pointage Mairie de Sandiara',
        'version' => '0.4.1',
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
});
