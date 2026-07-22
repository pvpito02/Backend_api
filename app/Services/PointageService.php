<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Pointage;
use App\Models\Site;
use App\Models\WorkSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PointageService
{
    public const COOLDOWN_MINUTES = 30;

    /**
     * Enregistre un pointage (scan live ou sync offline).
     *
     * @param  array{
     *   agent: Agent,
     *   qr_payload?: string|null,
     *   site_id?: int|null,
     *   type?: string|null,
     *   latitude?: float|null,
     *   longitude?: float|null,
     *   source?: string,
     *   device_id?: string|null,
     *   photo_path?: string|null,
     *   note?: string|null,
     *   scanned_at?: Carbon|null,
     *   pending_sync?: bool,
     *   skip_geo?: bool,
     * }  $payload
     */
    public function register(array $payload): Pointage
    {
        /** @var Agent $agent */
        $agent = $payload['agent'];
        $now = $payload['scanned_at'] ?? now();
        $source = $payload['source'] ?? 'QR';
        $pendingSync = (bool) ($payload['pending_sync'] ?? false);

        $this->assertWorkingDay($now);
        $this->assertCooldown($agent, $now);

        $site = $this->resolveSite(
            $payload['qr_payload'] ?? null,
            $payload['site_id'] ?? null,
            $payload['latitude'] ?? null,
            $payload['longitude'] ?? null,
        );

        $gpsStrict = $this->configBool('gps_strict', true);
        $offlineAllowed = $this->configBool('offline_allowed', true);
        $requirePhoto = $this->configBool('require_photo_on_scan', false);

        if ($source === 'OFFLINE' && ! $offlineAllowed) {
            throw ValidationException::withMessages([
                'source' => ['Le pointage hors-ligne n’est pas autorisé.'],
            ]);
        }

        if ($requirePhoto && empty($payload['photo_path']) && $source !== 'MANUEL') {
            throw ValidationException::withMessages([
                'photo_path' => ['Une photo est obligatoire au scan.'],
            ]);
        }

        $isVisitor = false;
        $distance = null;

        if ($site) {
            $this->assertSiteAllowedForAgent($agent, $site);
            $isVisitor = $this->isVisitor($agent, $site);

            if (! ($payload['skip_geo'] ?? false) && $gpsStrict && isset($payload['latitude'], $payload['longitude'])) {
                $distance = $this->haversineMeters(
                    (float) $site->latitude,
                    (float) $site->longitude,
                    (float) $payload['latitude'],
                    (float) $payload['longitude'],
                );

                if ($distance > (float) $site->radius_meters) {
                    throw ValidationException::withMessages([
                        'latitude' => ["Hors zone GPS ({$distance} m > rayon {$site->radius_meters} m)."],
                    ]);
                }
            } elseif ($gpsStrict && ! isset($payload['latitude'], $payload['longitude']) && $source !== 'MANUEL' && ! $pendingSync) {
                throw ValidationException::withMessages([
                    'latitude' => ['Coordonnées GPS requises.'],
                ]);
            }
        }

        $type = $payload['type'] ?? $this->nextType($agent, $now);
        $lateMinutes = $type === 'ENTREE' ? $this->computeLateMinutes($now) : 0;
        $statut = $lateMinutes > 0 ? 'RETARD' : 'A_L_HEURE';

        $note = $payload['note'] ?? null;
        if ($distance !== null) {
            $note = trim(($note ? $note.' | ' : '')."distance={$distance}m");
        }

        return Pointage::query()->create([
            'agent_id' => $agent->id,
            'site_id' => $site?->id,
            'type' => $type,
            'date_pointage' => $now->toDateString(),
            'heure_pointage' => $now->format('H:i:s'),
            'statut' => $statut,
            'late_minutes' => $lateMinutes,
            'source' => $source,
            'latitude' => $payload['latitude'] ?? null,
            'longitude' => $payload['longitude'] ?? null,
            'device_id' => $payload['device_id'] ?? null,
            'is_visitor' => $isVisitor,
            'pending_sync' => $pendingSync,
            'note' => $note,
            'photo_path' => $payload['photo_path'] ?? null,
        ])->load(['agent.departement', 'site']);
    }

    public function resolveSite(?string $qrPayload, ?int $siteId, ?float $lat, ?float $lng): ?Site
    {
        if ($siteId) {
            $site = Site::query()->where('is_active', true)->find($siteId);
            if (! $site) {
                throw ValidationException::withMessages(['site_id' => ['Site introuvable ou inactif.']]);
            }

            return $site;
        }

        if ($qrPayload) {
            $site = Site::query()->where('is_active', true)->where('qr_payload', $qrPayload)->first();
            if ($site) {
                return $site;
            }

            // QR agent personnel : SANDIARA:EMP001:... → on prend le site le plus proche si GPS fourni
            if ($lat !== null && $lng !== null) {
                return $this->nearestSite($lat, $lng);
            }

            throw ValidationException::withMessages([
                'qr_payload' => ['QR non reconnu. Scannez une borne site ou fournissez le GPS.'],
            ]);
        }

        if ($lat !== null && $lng !== null) {
            return $this->nearestSite($lat, $lng, withinRadiusOnly: true);
        }

        return null;
    }

    public function nearestSite(float $lat, float $lng, bool $withinRadiusOnly = false): ?Site
    {
        $best = null;
        $bestDistance = PHP_FLOAT_MAX;

        foreach (Site::query()->where('is_active', true)->get() as $site) {
            $d = $this->haversineMeters($site->latitude, $site->longitude, $lat, $lng);
            if ($withinRadiusOnly && $d > (float) $site->radius_meters) {
                continue;
            }
            if ($d < $bestDistance) {
                $bestDistance = $d;
                $best = $site;
            }
        }

        return $best;
    }

    public function nextType(Agent $agent, Carbon $at): string
    {
        $last = Pointage::query()
            ->where('agent_id', $agent->id)
            ->whereDate('date_pointage', $at->toDateString())
            ->orderByDesc('heure_pointage')
            ->orderByDesc('id')
            ->first();

        if (! $last || $last->type === 'SORTIE') {
            return 'ENTREE';
        }

        return 'SORTIE';
    }

    public function computeLateMinutes(Carbon $at): int
    {
        $schedule = WorkSchedule::activeDefault();
        if (! $schedule) {
            return 0;
        }

        $entry = Carbon::parse($at->toDateString().' '.$schedule->entry_time);
        $limit = $entry->copy()->addMinutes((int) $schedule->late_tolerance_minutes);

        if ($at->lte($limit)) {
            return 0;
        }

        return (int) $entry->diffInMinutes($at);
    }

    public function assertCooldown(Agent $agent, Carbon $at): void
    {
        $last = Pointage::query()
            ->where('agent_id', $agent->id)
            ->orderByDesc('date_pointage')
            ->orderByDesc('heure_pointage')
            ->orderByDesc('id')
            ->first();

        if (! $last) {
            return;
        }

        $lastAt = Carbon::parse($last->date_pointage->format('Y-m-d').' '.$last->heure_pointage);
        $diff = $lastAt->diffInMinutes($at, false);

        if ($diff >= 0 && $diff < self::COOLDOWN_MINUTES) {
            $wait = self::COOLDOWN_MINUTES - (int) $diff;
            throw ValidationException::withMessages([
                'scan' => ["Cooldown actif : attendez encore {$wait} minute(s)."],
            ]);
        }
    }

    public function assertWorkingDay(Carbon $at): void
    {
        $schedule = WorkSchedule::activeDefault();
        if (! $schedule) {
            return;
        }

        $dow = (int) $at->dayOfWeek; // 0 = dimanche

        if ($schedule->block_sunday && $dow === Carbon::SUNDAY) {
            throw ValidationException::withMessages([
                'scan' => ['Pointage bloqué le dimanche.'],
            ]);
        }

        if (! $schedule->work_saturday && $dow === Carbon::SATURDAY) {
            throw ValidationException::withMessages([
                'scan' => ['Pointage non autorisé le samedi.'],
            ]);
        }

        $isHoliday = DB::table('holidays')
            ->where('date_holiday', $at->toDateString())
            ->where('is_active', 1)
            ->exists();

        if ($isHoliday) {
            throw ValidationException::withMessages([
                'scan' => ['Jour férié : pointage non requis / bloqué.'],
            ]);
        }
    }

    public function assertSiteAllowedForAgent(Agent $agent, Site $site): void
    {
        $agent->loadMissing('departement');
        $deptCode = $agent->departement?->code;
        $isTech = $deptCode === 'TECH';

        $ok = match ($site->services_rule) {
            'ALL' => true,
            'TECHNIQUE_ONLY' => $isTech,
            'ALL_EXCEPT_TECHNIQUE' => ! $isTech,
            'CUSTOM' => $site->departements()->where('departements.id', $agent->departement_id)->exists(),
            default => true,
        };

        if (! $ok) {
            throw ValidationException::withMessages([
                'site_id' => ['Ce site n’est pas autorisé pour votre service.'],
            ]);
        }
    }

    public function isVisitor(Agent $agent, Site $site): bool
    {
        $agent->loadMissing('departement');
        $isTech = $agent->departement?->code === 'TECH';
        $homeCode = $isTech ? 'ancienne-mairie' : 'nouvelle-mairie';

        return $site->code !== $homeCode;
    }

    public function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): int
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return (int) round($earth * $c);
    }

    private function configBool(string $key, bool $default): bool
    {
        $value = DB::table('remote_configs')
            ->where('key_name', $key)
            ->where('is_active', 1)
            ->value('value_text');

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
