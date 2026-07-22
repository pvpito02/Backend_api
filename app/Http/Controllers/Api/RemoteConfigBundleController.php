<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Http\Resources\MobileFeatureResource;
use App\Http\Resources\RemoteConfigResource;
use App\Http\Resources\SiteResource;
use App\Http\Resources\WorkScheduleResource;
use App\Models\Announcement;
use App\Models\MobileFeature;
use App\Models\RemoteConfig;
use App\Models\Site;
use App\Models\WorkSchedule;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bundle admin → mobile (équivalent sandiara_admin_settings).
 */
class RemoteConfigBundleController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RemoteConfig::class);

        $configs = RemoteConfig::query()->where('is_active', true)->get();
        $map = [];
        foreach ($configs as $row) {
            $map[$row->key_name] = (new RemoteConfigResource($row))->resolve()['value'];
        }

        $bool = static fn (string $key, bool $default = false) => in_array(
            strtolower((string) ($map[$key] ?? ($default ? '1' : '0'))),
            ['1', 'true', 'yes', 'on'],
            true
        );

        $schedule = WorkSchedule::activeDefault();
        $sites = Site::query()->where('is_active', true)->with('departements')->orderBy('name')->get();
        $features = MobileFeature::query()->orderBy('sort_order')->get();
        $announcements = Announcement::query()->activeForMobile()->orderByDesc('priority')->get();

        return response()->json([
            'synced_at' => now()->toIso8601String(),
            'app' => [
                'appName' => $map['app_name'] ?? 'Système de Pointage QR',
                'orgName' => $map['org_name'] ?? 'Mairie de Sandiara',
                'tagline' => $map['tagline'] ?? null,
                'logoUrl' => MediaUrl::public($map['logo_url'] ?? null) ?? ($map['logo_url'] ?? null),
                'supportPhone' => $map['support_phone'] ?? null,
                'supportEmail' => $map['support_email'] ?? null,
                'maintenanceMode' => $bool('maintenance_mode'),
            ],
            'mobileFeatures' => MobileFeatureResource::collection($features),
            'localisation' => [
                'sites' => SiteResource::collection($sites),
                'gpsStrict' => $bool('gps_strict', true),
                'missionException' => $bool('mission_exception', true),
                'offlineAllowed' => $bool('offline_allowed', true),
                'requirePhotoOnScan' => $bool('require_photo_on_scan'),
                'defaultRadius' => (int) ($map['default_radius_meters'] ?? 150),
            ],
            'securite' => [
                'sessionMinutes' => (int) ($map['session_minutes'] ?? 60),
                'maxLoginAttempts' => (int) ($map['max_login_attempts'] ?? 5),
                'lockMinutes' => (int) ($map['lock_minutes'] ?? 15),
                'forcePasswordChangeDays' => (int) ($map['force_password_change_days'] ?? 90),
                'minPasswordLength' => (int) ($map['min_password_length'] ?? 8),
                'require2faAdmin' => $bool('require_2fa_admin'),
                'logAdminConnections' => $bool('log_admin_connections', true),
                'biometricMobile' => $bool('biometric_mobile', true),
                'pinMobile' => $bool('pin_mobile'),
            ],
            'horaires' => $schedule ? [
                'entryTime' => substr((string) $schedule->entry_time, 0, 5),
                'exitTime' => substr((string) $schedule->exit_time, 0, 5),
                'fridayExit' => substr((string) $schedule->friday_exit_time, 0, 5),
                'lateTolerance' => (int) $schedule->late_tolerance_minutes,
                'workSaturday' => (bool) $schedule->work_saturday,
                'blockSunday' => (bool) $schedule->block_sunday,
            ] : null,
            'schedule' => $schedule ? new WorkScheduleResource($schedule) : null,
            'avance' => [
                'notifRetards' => $bool('notif_retards', true),
                'notifDailyReport' => $bool('notif_daily_report', true),
                'notifAbsence' => $bool('notif_absence', true),
                'notifReminderScan' => $bool('notif_reminder_scan', true),
                'forceAppUpdate' => $bool('force_app_update'),
                'minAppVersion' => $map['app_version'] ?? '1.0.0',
                'demoMode' => $bool('demo_mode', true),
            ],
            'retraite' => [
                'ageMinimum' => (int) ($map['retraite_age_minimum'] ?? 60),
                'ageLimite' => (int) ($map['retraite_age_limite'] ?? 65),
                'alerteMois' => array_map(
                    'intval',
                    array_filter(explode(',', (string) ($map['retraite_alerte_mois'] ?? '6,3,1')))
                ),
            ],
            'announcements' => AnnouncementResource::collection($announcements),
            'raw_configs' => $map,
        ]);
    }
}
