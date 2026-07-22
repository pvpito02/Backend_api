<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\AgentDocumentController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DemandeController;
use App\Http\Controllers\Api\DepartementController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\MissionController;
use App\Http\Controllers\Api\MobileFeatureController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OvertimeRequestController;
use App\Http\Controllers\Api\PlanningShiftController;
use App\Http\Controllers\Api\PointageAnomalieController;
use App\Http\Controllers\Api\PointageController;
use App\Http\Controllers\Api\QrCodeController;
use App\Http\Controllers\Api\RemoteConfigBundleController;
use App\Http\Controllers\Api\RemoteConfigController;
use App\Http\Controllers\Api\RetraiteController;
use App\Http\Controllers\Api\SanctionController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkScheduleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Mairie de Sandiara (Pointage)
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'service' => 'Backend_api',
        'app' => 'Pointage Mairie de Sandiara',
        'version' => '0.10.0',
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

    Route::post('/media/upload', [MediaController::class, 'store']);

    // Pointages
    Route::get('/pointages/today', [PointageController::class, 'today']);
    Route::post('/pointages/scan', [PointageController::class, 'scan']);
    Route::post('/pointages/sync', [PointageController::class, 'sync']);
    Route::post('/pointages/{pointage}/acknowledge', [PointageController::class, 'acknowledge']);
    Route::post('/pointages/{pointage}/anomalies', [PointageController::class, 'reportAnomalie']);
    Route::apiResource('pointages', PointageController::class);

    Route::get('/pointage-anomalies', [PointageAnomalieController::class, 'index']);
    Route::post('/pointage-anomalies/{pointageAnomalie}/resolve', [PointageAnomalieController::class, 'resolve']);

    // Demandes RH
    Route::post('/demandes/{demande}/decide', [DemandeController::class, 'decide']);
    Route::post('/demandes/{demande}/cancel', [DemandeController::class, 'cancel']);
    Route::apiResource('demandes', DemandeController::class)->except(['update']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

    // Paramètres admin / remote config mobile
    Route::get('/remote-config', RemoteConfigBundleController::class);
    Route::post('/remote-configs/bulk', [RemoteConfigController::class, 'bulkUpdate']);
    Route::apiResource('remote-configs', RemoteConfigController::class);
    Route::apiResource('mobile-features', MobileFeatureController::class)->only(['index', 'show', 'update']);
    Route::apiResource('work-schedules', WorkScheduleController::class)->only(['index', 'show', 'update']);
    Route::apiResource('announcements', AnnouncementController::class);

    // Calendrier / planning / missions
    Route::apiResource('holidays', HolidayController::class);
    Route::apiResource('planning-shifts', PlanningShiftController::class);
    Route::apiResource('missions', MissionController::class);

    // Sanctions / retraites / QR agents
    Route::apiResource('sanctions', SanctionController::class);
    Route::get('/retraites/alerts', [RetraiteController::class, 'alerts']);
    Route::apiResource('retraites', RetraiteController::class);
    Route::get('/qr-codes/mine', [QrCodeController::class, 'mine']);
    Route::post('/qr-codes/{qr_code}/revoke', [QrCodeController::class, 'revoke']);
    Route::apiResource('qr-codes', QrCodeController::class);

    // Dossiers agents / HS / audit
    Route::get('/dossiers-agents', [AgentDocumentController::class, 'dossiers']);
    Route::apiResource('agent-documents', AgentDocumentController::class);
    Route::post('/overtime-requests/{overtime_request}/decide', [OvertimeRequestController::class, 'decide']);
    Route::apiResource('overtime-requests', OvertimeRequestController::class);
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::get('/audit-logs/{audit_log}', [AuditLogController::class, 'show']);
});
