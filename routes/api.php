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
| Authentification : Laravel Sanctum (Bearer tokens)
| Clients : Admin React + Mobile Flutter
|
*/

Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'service' => 'Backend_api',
        'app' => 'Pointage Mairie de Sandiara',
        'version' => '0.4.0',
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
    // —— Utilisateurs ——
    Route::get('/users', [UserController::class, 'index'])
        ->middleware('role:super_admin,admin,sous_admin');
    Route::get('/users/{user}', [UserController::class, 'show'])
        ->middleware('role:super_admin,admin,sous_admin');
    Route::post('/users', [UserController::class, 'store'])
        ->middleware('role:super_admin,admin');
    Route::put('/users/{user}', [UserController::class, 'update'])
        ->middleware('role:super_admin,admin');
    Route::patch('/users/{user}', [UserController::class, 'update'])
        ->middleware('role:super_admin,admin');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])
        ->middleware('role:super_admin,admin');

    // —— Départements ——
    Route::get('/departements', [DepartementController::class, 'index'])
        ->middleware('role:super_admin,admin,sous_admin,agent');
    Route::get('/departements/{departement}', [DepartementController::class, 'show'])
        ->middleware('role:super_admin,admin,sous_admin,agent');
    Route::post('/departements', [DepartementController::class, 'store'])
        ->middleware('role:super_admin,admin');
    Route::put('/departements/{departement}', [DepartementController::class, 'update'])
        ->middleware('role:super_admin,admin');
    Route::patch('/departements/{departement}', [DepartementController::class, 'update'])
        ->middleware('role:super_admin,admin');
    Route::delete('/departements/{departement}', [DepartementController::class, 'destroy'])
        ->middleware('role:super_admin,admin');

    // —— Agents ——
    Route::get('/agents', [AgentController::class, 'index'])
        ->middleware('role:super_admin,admin,sous_admin');
    Route::get('/agents/{agent}', [AgentController::class, 'show'])
        ->middleware('role:super_admin,admin,sous_admin,agent');
    Route::post('/agents', [AgentController::class, 'store'])
        ->middleware('role:super_admin,admin');
    Route::put('/agents/{agent}', [AgentController::class, 'update'])
        ->middleware('role:super_admin,admin');
    Route::patch('/agents/{agent}', [AgentController::class, 'update'])
        ->middleware('role:super_admin,admin');
    Route::delete('/agents/{agent}', [AgentController::class, 'destroy'])
        ->middleware('role:super_admin,admin');
});
