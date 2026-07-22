<?php

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
        'version' => '0.1.0',
    ]);
});
