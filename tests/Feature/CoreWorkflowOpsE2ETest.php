<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\Departement;
use App\Models\Holiday;
use App\Models\Pointage;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Couche 3 — ops admin & mobile :
 * dashboard, stats, today, acknowledge retard, holiday bloque scan,
 * realtime, profil, MDP, device token.
 */
class CoreWorkflowOpsE2ETest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Role> */
    private array $roles = [];

    private User $rh;

    private User $agentUser;

    private Agent $agent;

    private Departement $dept;

    private Site $site;

    private string $pwd = 'Admin@2026!';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (
            [
                'super_admin' => 'Super',
                'admin' => 'RH',
                'agent' => 'Agent',
                'conseiller' => 'Conseiller',
                'sous_admin' => 'Sous-admin',
            ] as $name => $label
        ) {
            $this->roles[$name] = Role::query()->updateOrCreate(
                ['name' => $name],
                ['display_name' => $label, 'description' => 'Ops E2E', 'is_active' => true],
            );
        }

        $this->rh = User::query()->create([
            'role_id' => $this->roles['admin']->id,
            'name' => 'RH Ops',
            'email' => 'rh.ops@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
        ]);

        $this->dept = Departement::query()->create([
            'code' => 'TECH',
            'nom' => 'Technique',
            'is_active' => true,
        ]);

        $this->site = Site::query()->create([
            'code' => 'borne-ops',
            'name' => 'Borne Ops',
            'latitude' => 14.4167,
            'longitude' => -16.8500,
            'radius_meters' => 150,
            'qr_payload' => 'SITE-OPS-E2E',
            'services_rule' => 'ALL',
            'is_active' => true,
        ]);

        $this->agentUser = User::query()->create([
            'role_id' => $this->roles['agent']->id,
            'name' => 'Agent Ops',
            'email' => 'agent.ops@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
            'phone' => '+221773333333',
        ]);

        $this->agent = Agent::query()->create([
            'user_id' => $this->agentUser->id,
            'departement_id' => $this->dept->id,
            'matricule' => 'EMP-OPS-01',
            'prenom' => 'Agent',
            'nom' => 'Ops',
            'sexe' => 'F',
            'date_naissance' => '1991-07-07',
            'lieu_naissance' => 'Sandiara',
            'date_entree' => '2024-06-01',
            'poste' => 'Agent',
            'statut' => 'Actif',
            'is_active' => true,
        ]);
    }

    // ─── Dashboard & stats ─────────────────────────────────────────────────

    public function test_01_dashboard_and_stats_for_rh_not_agent(): void
    {
        Sanctum::actingAs($this->rh);

        $summary = $this->getJson('/api/dashboard/summary?date=2026-08-21')->assertOk();
        $this->assertIsArray($summary->json());

        $this->getJson('/api/dashboard/alerts')->assertOk();
        $this->getJson('/api/stats/presence?date=2026-08-21')->assertOk();
        $this->getJson('/api/stats/presence-by-service')->assertOk();
        $this->getJson('/api/stats/retards')->assertOk();
        $this->getJson('/api/stats/demandes')->assertOk();
        $this->getJson('/api/reports/summary')->assertOk();
        $this->getJson('/api/reports/weekly-pointage')->assertOk();

        Sanctum::actingAs($this->agentUser);
        $this->getJson('/api/dashboard/summary')->assertForbidden();
        $this->getJson('/api/stats/presence')->assertForbidden();
    }

    // ─── Pointages du jour + acknowledge retard ────────────────────────────

    public function test_02_today_list_and_acknowledge_retard(): void
    {
        Sanctum::actingAs($this->agentUser);
        $this->postJson('/api/pointages/scan', [
            'qr_payload' => $this->site->qr_payload,
            'type' => 'ENTREE',
            'latitude' => 14.4167,
            'longitude' => -16.8500,
            'scanned_at' => '2026-08-21T08:00:00+00:00',
        ])->assertCreated();

        // Retard forcé pour tester acknowledge
        $retard = Pointage::query()->create([
            'agent_id' => $this->agent->id,
            'site_id' => $this->site->id,
            'type' => 'ENTREE',
            'date_pointage' => '2026-08-19',
            'heure_pointage' => '09:30:00',
            'statut' => 'RETARD',
            'late_minutes' => 45,
            'source' => 'QR',
        ]);

        Sanctum::actingAs($this->rh);
        $this->getJson('/api/pointages/today?agent_id='.$this->agent->id)->assertOk();

        $this->postJson('/api/pointages/'.$retard->id.'/acknowledge')
            ->assertOk()
            ->assertJsonPath('pointage.id', $retard->id);

        $retard->refresh();
        $this->assertNotNull($retard->acknowledged_at);
        $this->assertSame($this->rh->id, $retard->acknowledged_by);

        // Re-acknowledge / non-retard
        $ok = Pointage::query()->where('statut', '!=', 'RETARD')->first();
        if ($ok) {
            $this->postJson('/api/pointages/'.$ok->id.'/acknowledge')->assertStatus(422);
        }
    }

    // ─── Jour férié bloque le scan ─────────────────────────────────────────

    public function test_03_holiday_blocks_scan(): void
    {
        Holiday::query()->create([
            'libelle' => 'Fête Ops',
            'date_holiday' => '2026-08-18',
            'type_holiday' => 'FERIE',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->agentUser);
        $this->postJson('/api/pointages/scan', [
            'qr_payload' => $this->site->qr_payload,
            'type' => 'ENTREE',
            'latitude' => 14.4167,
            'longitude' => -16.8500,
            'scanned_at' => '2026-08-18T08:00:00+00:00',
        ])->assertStatus(422);
    }

    // ─── Realtime poll ─────────────────────────────────────────────────────

    public function test_04_realtime_poll_after_scan(): void
    {
        Sanctum::actingAs($this->agentUser);
        $this->postJson('/api/pointages/scan', [
            'qr_payload' => $this->site->qr_payload,
            'type' => 'ENTREE',
            'latitude' => 14.4167,
            'longitude' => -16.8500,
            'scanned_at' => '2026-08-21T08:15:00+00:00',
        ])->assertCreated();

        Sanctum::actingAs($this->rh);
        $poll = $this->getJson('/api/realtime/poll?after=0&limit=20')->assertOk();
        $this->assertArrayHasKey('events', $poll->json());
        $this->assertArrayHasKey('last_id', $poll->json());
    }

    // ─── Profil + mot de passe ─────────────────────────────────────────────

    public function test_05_profile_update_and_change_password(): void
    {
        Sanctum::actingAs($this->agentUser);

        $this->patchJson('/api/auth/profile', [
            'name' => 'Agent Ops Maj',
            'phone' => '+221774444444',
        ])->assertOk()
            ->assertJsonPath('user.name', 'Agent Ops Maj');

        $this->postJson('/api/auth/change-password', [
            'current_password' => $this->pwd,
            'password' => 'NewPass@2026!',
            'password_confirmation' => 'NewPass@2026!',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'login' => 'agent.ops@sandiara.sn',
            'password' => 'NewPass@2026!',
            'device_name' => 'ops-relogin',
        ])->assertOk();

        $this->postJson('/api/auth/login', [
            'login' => 'agent.ops@sandiara.sn',
            'password' => $this->pwd,
        ])->assertStatus(422);
    }

    // ─── Device token mobile ───────────────────────────────────────────────

    public function test_06_device_token_register_and_list(): void
    {
        Sanctum::actingAs($this->agentUser);

        $this->postJson('/api/device-tokens', [
            'token' => 'fcm-token-ops-e2e-'.uniqid(),
            'platform' => 'android',
            'device_name' => 'Pixel E2E',
        ])->assertSuccessful();

        $this->getJson('/api/device-tokens')->assertOk();
    }

    // ─── Anomalie sur pointage ─────────────────────────────────────────────

    public function test_07_report_anomalie_on_pointage(): void
    {
        Sanctum::actingAs($this->agentUser);
        $scan = $this->postJson('/api/pointages/scan', [
            'qr_payload' => $this->site->qr_payload,
            'type' => 'ENTREE',
            'latitude' => 14.4167,
            'longitude' => -16.8500,
            'scanned_at' => '2026-08-20T08:00:00+00:00',
        ])->assertCreated();

        $pid = $scan->json('pointage.id');

        Sanctum::actingAs($this->rh);
        $this->postJson('/api/pointages/'.$pid.'/anomalies', [
            'type' => 'HORAIRE',
            'severite' => 'moyenne',
            'description' => 'Incohérence horaire E2E',
        ])->assertCreated();

        $this->getJson('/api/pointage-anomalies')->assertOk();
    }

    // ─── Heartbeat présence ────────────────────────────────────────────────

    public function test_08_heartbeat_keeps_session_alive(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'login' => $this->rh->email,
            'password' => $this->pwd,
            'device_name' => 'ops-heartbeat',
        ])->assertOk();

        $this->withToken($login->json('token'))
            ->postJson('/api/auth/heartbeat')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    // ─── Planning lecture RH ───────────────────────────────────────────────

    public function test_09_planning_and_holidays_list(): void
    {
        Sanctum::actingAs($this->rh);

        $this->postJson('/api/holidays', [
            'libelle' => 'Jour special',
            'date_holiday' => '2026-11-11',
            'type_holiday' => 'SPECIAL',
            'is_active' => true,
        ])->assertCreated();

        $this->getJson('/api/holidays')->assertOk();
        $this->getJson('/api/planning-shifts')->assertOk();
        $this->getJson('/api/work-schedules')->assertOk();
    }

    // ─── Intrusion : agent ne traite pas les retards ───────────────────────

    public function test_10_agent_cannot_acknowledge_retard(): void
    {
        $retard = Pointage::query()->create([
            'agent_id' => $this->agent->id,
            'site_id' => $this->site->id,
            'type' => 'ENTREE',
            'date_pointage' => '2026-08-17',
            'heure_pointage' => '09:45:00',
            'statut' => 'RETARD',
            'late_minutes' => 60,
            'source' => 'QR',
        ]);

        Sanctum::actingAs($this->agentUser);
        $this->postJson('/api/pointages/'.$retard->id.'/acknowledge')
            ->assertForbidden();
    }
}
