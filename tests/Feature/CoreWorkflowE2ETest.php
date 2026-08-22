<?php

namespace Tests\Feature;

use App\Models\AbsenceRequest;
use App\Models\Agent;
use App\Models\AppNotification;
use App\Models\Departement;
use App\Models\Pointage;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cœur métier uniquement :
 * Auth → Agents → QR → Pointage → Demandes → Planning → Notifs
 * (hors sanctions, retraites, exports)
 */
class CoreWorkflowE2ETest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Role> */
    private array $roles = [];

    private User $super;

    private User $rh;

    private User $agentUser;

    private Agent $agent;

    private Departement $dept;

    private Site $site;

    private string $pwd = 'Admin@2026!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootCoreWorld();
    }

    private function bootCoreWorld(): void
    {
        foreach (
            [
                'super_admin' => 'Super',
                'admin' => 'RH',
                'sous_admin' => 'Sous-admin',
                'conseiller' => 'Conseiller',
                'agent' => 'Agent',
            ] as $name => $label
        ) {
            $this->roles[$name] = Role::query()->updateOrCreate(
                ['name' => $name],
                ['display_name' => $label, 'description' => 'Core E2E', 'is_active' => true],
            );
        }

        $this->super = User::query()->create([
            'role_id' => $this->roles['super_admin']->id,
            'name' => 'Super Core',
            'email' => 'super.core@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
        ]);

        $this->rh = User::query()->create([
            'role_id' => $this->roles['admin']->id,
            'name' => 'RH Core',
            'email' => 'rh.core@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
        ]);

        $this->dept = Departement::query()->create([
            'code' => 'TECH',
            'nom' => 'Technique',
            'is_active' => true,
        ]);

        $this->site = Site::query()->create([
            'code' => 'borne-mairie',
            'name' => 'Borne Mairie',
            'latitude' => 14.4167,
            'longitude' => -16.8500,
            'radius_meters' => 200,
            'qr_payload' => 'SITE-CORE-E2E',
            'services_rule' => 'ALL',
            'is_active' => true,
        ]);

        $this->agentUser = User::query()->create([
            'role_id' => $this->roles['agent']->id,
            'name' => 'Awa Core',
            'email' => 'awa.core@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
            'phone' => '+221771111111',
        ]);

        $this->agent = Agent::query()->create([
            'user_id' => $this->agentUser->id,
            'departement_id' => $this->dept->id,
            'matricule' => 'EMP-CORE-01',
            'prenom' => 'Awa',
            'nom' => 'Core',
            'sexe' => 'F',
            'date_naissance' => '1994-05-12',
            'lieu_naissance' => 'Sandiara',
            'date_entree' => '2024-03-01',
            'poste' => 'Agent terrain',
            'telephone' => '+221771111111',
            'email' => 'awa.core@sandiara.sn',
            'statut' => 'Actif',
            'is_active' => true,
        ]);
    }

    // ─── Auth ──────────────────────────────────────────────────────────────

    public function test_01_auth_super_rh_agent(): void
    {
        foreach (
            [
                [$this->super, 'super_admin'],
                [$this->rh, 'admin'],
                [$this->agentUser, 'agent'],
            ] as [$user, $role]
        ) {
            $this->postJson('/api/auth/login', [
                'login' => $user->email,
                'password' => $this->pwd,
                'device_name' => 'core-'.$role,
            ])->assertOk()
                ->assertJsonPath('user.role.name', $role)
                ->assertJsonStructure(['token']);
        }
    }

    // ─── Agents ────────────────────────────────────────────────────────────

    public function test_02_rh_creates_lists_updates_agent(): void
    {
        Sanctum::actingAs($this->rh);

        $created = $this->postJson('/api/agents', [
            'prenom' => 'Ibra',
            'nom' => 'Sarr',
            'sexe' => 'M',
            'date_naissance' => '1992-01-20',
            'lieu_naissance' => 'Fatick',
            'date_entree' => '2025-01-15',
            'poste' => 'Technicien',
            'departement_id' => $this->dept->id,
            'email' => 'ibra.core@sandiara.sn',
            'telephone' => '+221772222222',
            'statut' => 'Actif',
            'is_active' => true,
        ])->assertCreated();

        $id = $created->json('data.id') ?? $created->json('agent.id') ?? $created->json('id');
        $this->assertNotEmpty($id);
        $this->assertNotEmpty(
            $created->json('data.matricule') ?? $created->json('agent.matricule')
        );

        $this->getJson('/api/agents')->assertOk();
        $this->patchJson('/api/agents/'.$id, ['poste' => 'Technicien senior'])->assertOk();

        Sanctum::actingAs($this->agentUser);
        $this->postJson('/api/agents', [
            'prenom' => 'Hack',
            'nom' => 'X',
            'sexe' => 'M',
            'date_naissance' => '1990-01-01',
            'lieu_naissance' => 'X',
            'departement_id' => $this->dept->id,
        ])->assertForbidden();
    }

    // ─── QR ────────────────────────────────────────────────────────────────

    public function test_03_rh_generates_qr_agent_sees_mine(): void
    {
        Sanctum::actingAs($this->rh);
        $qr = $this->postJson('/api/qr-codes', [
            'agent_id' => $this->agent->id,
            'revoke_previous' => true,
            'expires_at' => now()->addMonths(6)->toIso8601String(),
        ])->assertCreated();

        $qrId = $qr->json('data.id') ?? $qr->json('qr_code.id') ?? $qr->json('id');
        $this->assertNotEmpty($qrId);
        $this->assertDatabaseHas('qr_codes', [
            'id' => $qrId,
            'agent_id' => $this->agent->id,
            'statut' => 'ACTIF',
        ]);

        Sanctum::actingAs($this->agentUser);
        $mine = $this->getJson('/api/qr-codes/mine')->assertOk();
        $this->assertNotEmpty($mine->json('data') ?? $mine->json());

        Sanctum::actingAs($this->rh);
        $this->postJson('/api/qr-codes/'.$qrId.'/revoke')->assertOk();
        $this->assertSame('REVOQUE', QrCode::query()->find($qrId)?->statut);
    }

    // ─── Pointage (scan mobile + liste RH) ─────────────────────────────────

    public function test_04_agent_scans_then_rh_lists_pointages(): void
    {
        Sanctum::actingAs($this->agentUser);

        // Scan borne site + GPS dans le rayon
        $scan = $this->postJson('/api/pointages/scan', [
            'qr_payload' => $this->site->qr_payload,
            'type' => 'ENTREE',
            'latitude' => 14.4167,
            'longitude' => -16.8500,
            'device_id' => 'phone-e2e-1',
            'scanned_at' => '2026-08-21T08:12:00+00:00', // vendredi (évite blocage samedi/dimanche)
        ]);

        $scan->assertCreated();
        $pointageId = $scan->json('pointage.id');
        $this->assertNotEmpty($pointageId);
        $this->assertSame('ENTREE', $scan->json('pointage.type'));

        $p = Pointage::query()->findOrFail($pointageId);
        $this->assertSame($this->agent->id, $p->agent_id);
        $this->assertContains($p->statut, ['A_L_HEURE', 'RETARD']);

        // Agent ne peut pas forcer le store manuel admin
        $this->postJson('/api/pointages', [
            'agent_id' => $this->agent->id,
            'type' => 'SORTIE',
            'date_pointage' => now()->toDateString(),
            'heure_pointage' => '17:00:00',
        ])->assertForbidden();

        Sanctum::actingAs($this->rh);
        $list = $this->getJson('/api/pointages')->assertOk();
        $ids = collect($list->json('data') ?? [])->pluck('id')->all();
        $this->assertContains($pointageId, $ids);

        // Intrusion : agent ne filtre pas un autre agent
        $other = Agent::query()->create([
            'departement_id' => $this->dept->id,
            'matricule' => 'EMP-CORE-99',
            'prenom' => 'Autre',
            'nom' => 'Agent',
            'sexe' => 'M',
            'date_naissance' => '1991-01-01',
            'lieu_naissance' => 'X',
            'date_entree' => '2024-01-01',
            'poste' => 'X',
            'statut' => 'Actif',
            'is_active' => true,
        ]);
        Sanctum::actingAs($this->agentUser);
        $scoped = $this->getJson('/api/pointages?agent_id='.$other->id)->assertOk();
        $agentIds = collect($scoped->json('data') ?? [])->pluck('agent_id')->unique()->values()->all();
        $this->assertSame([$this->agent->id], $agentIds);
    }

    // ─── Demandes ──────────────────────────────────────────────────────────

    public function test_05_demande_mobile_then_rh_decides_with_notif(): void
    {
        Sanctum::actingAs($this->agentUser);
        $created = $this->postJson('/api/demandes', [
            'type_demande' => 'PERMISSION',
            'date_debut' => now()->addDays(2)->toDateString(),
            'date_fin' => now()->addDays(2)->toDateString(),
            'heure_debut' => '10:00',
            'heure_fin' => '12:00',
            'motif' => 'Rendez-vous administratif',
        ])->assertCreated();

        $demandeId = $created->json('data.id')
            ?? $created->json('demande.id')
            ?? $created->json('id');
        $this->assertNotEmpty($demandeId);

        Sanctum::actingAs($this->rh);
        $this->getJson('/api/demandes')->assertOk();
        $this->postJson('/api/demandes/'.$demandeId.'/decide', [
            'decision' => 'APPROUVEE',
            'commentaire' => 'Accordé',
        ])->assertOk();

        $demande = AbsenceRequest::query()->findOrFail($demandeId);
        $this->assertSame('APPROUVEE', $demande->statut);

        // L’agent reçoit une notif liée à la décision (si le flux le prévoit)
        Sanctum::actingAs($this->agentUser);
        $inbox = $this->getJson('/api/notifications')->assertOk();
        $this->assertIsArray($inbox->json('data'));
    }

    public function test_05b_demande_date_fin_before_debut_rejected(): void
    {
        Sanctum::actingAs($this->rh);
        $this->postJson('/api/demandes', [
            'agent_id' => $this->agent->id,
            'type_demande' => 'CONGE',
            'date_debut' => '2026-09-10',
            'date_fin' => '2026-09-01',
            'motif' => 'Dates incohérentes',
        ])->assertStatus(422);
    }

    // ─── Planning ──────────────────────────────────────────────────────────

    public function test_06_planning_shift_crud_coherence(): void
    {
        Sanctum::actingAs($this->rh);

        $this->postJson('/api/planning-shifts', [
            'departement_id' => $this->dept->id,
            'audience' => 'SERVICE',
            'shift_start' => '16:00',
            'shift_end' => '08:00',
            'manager_name' => 'Chef',
            'required_count' => 2,
            'assigned_count' => 1,
            'statut' => 'CONFIRME',
            'date_effective' => now()->addDay()->toDateString(),
        ])->assertStatus(422);

        $ok = $this->postJson('/api/planning-shifts', [
            'departement_id' => $this->dept->id,
            'audience' => 'SERVICE',
            'shift_start' => '08:00',
            'shift_end' => '16:00',
            'manager_name' => 'Chef Technique',
            'required_count' => 3,
            'assigned_count' => 1,
            'statut' => 'CONFIRME',
            'date_effective' => now()->addDay()->toDateString(),
            'is_active' => true,
        ])->assertCreated();

        $id = $ok->json('data.id') ?? $ok->json('planning_shift.id') ?? $ok->json('id');
        $this->getJson('/api/planning-shifts')->assertOk();
        $this->patchJson('/api/planning-shifts/'.$id, [
            'assigned_count' => 2,
        ])->assertOk();
    }

    // ─── Notifs + confidentialité ──────────────────────────────────────────

    public function test_07_notifications_isolation_and_unread(): void
    {
        /** @var NotificationService $svc */
        $svc = app(NotificationService::class);
        $svc->notifyUser($this->agentUser, 'Notif agent', 'Corps A', 'info', 'core');
        $svc->notifyUser($this->rh, 'Notif RH', 'Corps RH', 'info', 'core');

        Sanctum::actingAs($this->agentUser);
        $titles = collect($this->getJson('/api/notifications')->assertOk()->json('data'))
            ->pluck('title')
            ->all();
        $this->assertContains('Notif agent', $titles);
        $this->assertNotContains('Notif RH', $titles);

        $countRes = $this->getJson('/api/notifications/unread-count')->assertOk();
        $this->assertGreaterThanOrEqual(1, (int) $countRes->json('unread_count'));

        $foreign = AppNotification::query()->where('user_id', $this->rh->id)->value('id');
        $this->postJson('/api/notifications/'.$foreign.'/read')->assertForbidden();
    }

    // ─── Gouvernance comptes (cœur) ────────────────────────────────────────

    public function test_08_governance_rh_vs_super(): void
    {
        Sanctum::actingAs($this->rh);
        $list = $this->getJson('/api/users')->assertOk();
        $emails = collect($list->json('data') ?? [])->pluck('email')->all();
        $this->assertNotContains($this->super->email, $emails);

        $this->postJson('/api/users', [
            'name' => 'Hack Super',
            'email' => 'hack.core@sandiara.sn',
            'password' => $this->pwd,
            'password_confirmation' => $this->pwd,
            'role_id' => $this->roles['super_admin']->id,
        ])->assertStatus(422);

        Sanctum::actingAs($this->super);
        $orphan = Agent::query()->create([
            'departement_id' => $this->dept->id,
            'matricule' => 'EMP-CORE-LOGIN',
            'prenom' => 'Login',
            'nom' => 'Agent',
            'sexe' => 'M',
            'date_naissance' => '1993-02-02',
            'lieu_naissance' => 'Sandiara',
            'date_entree' => '2025-01-01',
            'poste' => 'Agent',
            'statut' => 'Actif',
            'is_active' => true,
        ]);
        $this->postJson('/api/users', [
            'name' => 'Agent Login',
            'email' => 'agent.login.core@sandiara.sn',
            'password' => $this->pwd,
            'password_confirmation' => $this->pwd,
            'role_id' => $this->roles['agent']->id,
            'agent_id' => $orphan->id,
        ])->assertCreated();
    }

    // ─── Intrusions ciblées ────────────────────────────────────────────────

    public function test_09_intrusions_core_endpoints(): void
    {
        $this->getJson('/api/agents')->assertUnauthorized();
        $this->postJson('/api/pointages/scan', [
            'qr_payload' => $this->site->qr_payload,
            'latitude' => 14.4167,
            'longitude' => -16.8500,
        ])->assertUnauthorized();

        Sanctum::actingAs($this->agentUser);
        $this->getJson('/api/users')->assertForbidden();
        $this->getJson('/api/audit-logs')->assertForbidden();
        $this->deleteJson('/api/agents/'.$this->agent->id)->assertForbidden();
        $this->postJson('/api/qr-codes', [
            'agent_id' => $this->agent->id,
        ])->assertForbidden();
    }
}
