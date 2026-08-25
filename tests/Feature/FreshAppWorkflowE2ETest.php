<?php

namespace Tests\Feature;

use App\Models\AbsenceRequest;
use App\Models\Agent;
use App\Models\AppNotification;
use App\Models\Departement;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Simulation « appli toute neuve » : aucune donnée seedée en amont.
 * Couvre le workflow admin (sidebar) + mobile + dates + cohérence + intrusions.
 */
class FreshAppWorkflowE2ETest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Role> */
    private array $roles = [];

    private User $super;

    private User $rh;

    private User $agentUser;

    private Agent $agent;

    private Departement $dept;

    private string $pwd = 'Admin@2026!';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootstrapFreshWorld();
    }

    private function bootstrapFreshWorld(): void
    {
        foreach (
            [
                'super_admin' => 'Super Admin',
                'admin' => 'Admin RH',
                'sous_admin' => 'Sous-admin',
                'conseiller' => 'Conseiller',
                'agent' => 'Agent',
                'rh' => 'RH',
                'direction' => 'Direction',
            ] as $name => $label
        ) {
            $this->roles[$name] = Role::query()->updateOrCreate(
                ['name' => $name],
                [
                    'display_name' => $label,
                    'description' => 'E2E',
                    'is_active' => true,
                ],
            );
        }

        $this->super = User::query()->create([
            'role_id' => $this->roles['super_admin']->id,
            'name' => 'Super E2E',
            'email' => 'super.e2e@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
        ]);

        $this->rh = User::query()->create([
            'role_id' => $this->roles['admin']->id,
            'name' => 'RH E2E',
            'email' => 'rh.e2e@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
            'permissions' => null,
        ]);

        $this->dept = Departement::query()->create([
            'code' => 'TECH',
            'nom' => 'Technique',
            'is_active' => true,
        ]);

        $this->agentUser = User::query()->create([
            'role_id' => $this->roles['agent']->id,
            'name' => 'Awa Diop',
            'email' => 'awa.e2e@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
            'phone' => '+221770000001',
        ]);

        $this->agent = Agent::query()->create([
            'user_id' => $this->agentUser->id,
            'departement_id' => $this->dept->id,
            'matricule' => 'EMP-E2E-001',
            'prenom' => 'Awa',
            'nom' => 'Diop',
            'sexe' => 'F',
            'date_naissance' => '1995-03-15',
            'lieu_naissance' => 'Sandiara',
            'date_entree' => '2024-01-10',
            'poste' => 'Agent de terrain',
            'telephone' => '+221770000001',
            'email' => 'awa.e2e@sandiara.sn',
            'statut' => 'Actif',
            'is_active' => true,
        ]);
    }

    // ─── 1. Santé & public ─────────────────────────────────────────────────

    public function test_01_health_and_public_branding(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->getJson('/api/public/branding')->assertOk();
    }

    // ─── 2. Auth Super / RH / Agent (web + mobile) ──────────────────────────

    public function test_02_login_returns_correct_persona_and_token(): void
    {
        foreach (
            [
                [$this->super, 'super_admin'],
                [$this->rh, 'admin'],
                [$this->agentUser, 'agent'],
            ] as [$user, $roleName]
        ) {
            $this->postJson('/api/auth/login', [
                'login' => $user->email,
                'password' => $this->pwd,
                'device_name' => 'e2e-'.$roleName.'-'.uniqid(),
            ])->assertOk()
                ->assertJsonPath('user.email', $user->email)
                ->assertJsonPath('user.role.name', $roleName)
                ->assertJsonStructure(['token']);
        }

        $this->postJson('/api/auth/login', [
            'login' => $this->super->email,
            'password' => 'WrongPass1!',
        ])->assertStatus(422);
    }

    public function test_02b_me_with_real_token_for_agent_mobile(): void
    {
        $login = $this->postJson('/api/auth/login', [
            'login' => $this->agentUser->email,
            'password' => $this->pwd,
            'device_name' => 'pointage_mobile_e2e',
        ])->assertOk();

        $token = $login->json('token');
        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $this->agentUser->email)
            ->assertJsonPath('user.role.name', 'agent');

        $this->withToken($token)
            ->getJson('/api/qr-codes/mine')
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertOk();
    }

    // ─── 3. Tableau de bord & listes sidebar (lecture) ──────────────────────

    public function test_03_super_can_open_sidebar_list_endpoints(): void
    {
        Sanctum::actingAs($this->super);

        $gets = [
            '/api/dashboard/summary',
            '/api/dashboard/alerts',
            '/api/agents',
            '/api/departements',
            '/api/demandes',
            '/api/retraites',
            '/api/qr-codes',
            '/api/pointages',
            '/api/pointage-anomalies',
            '/api/overtime-requests',
            '/api/missions',
            '/api/planning-shifts',
            '/api/holidays',
            '/api/stats/presence',
            '/api/stats/retards',
            '/api/stats/demandes',
            '/api/reports/summary',
            '/api/reports/weekly-pointage',
            '/api/sanctions',
            '/api/users',
            '/api/roles',
            '/api/announcements',
            '/api/sites',
            '/api/dossiers-agents',
            '/api/notifications',
            '/api/notifications/unread-count',
            '/api/audit-logs',
            '/api/remote-config',
            '/api/mobile-features',
            '/api/work-schedules',
            '/api/retraites/alerts',
        ];

        foreach ($gets as $url) {
            $this->getJson($url)->assertOk("GET {$url} devrait répondre 200 pour Super");
        }
    }

    // ─── 4. CRUD organisation : départements, agents, sites ─────────────────

    public function test_04_rh_crud_departement_agent_site_with_dates(): void
    {
        Sanctum::actingAs($this->rh);

        $deptRes = $this->postJson('/api/departements', [
            'code' => 'RH',
            'nom' => 'Ressources Humaines',
            'description' => 'Service RH',
            'is_active' => true,
        ])->assertCreated();
        $deptId = $deptRes->json('data.id') ?? $deptRes->json('departement.id') ?? $deptRes->json('id');
        $this->assertNotEmpty($deptId);

        $agentRes = $this->postJson('/api/agents', [
            'prenom' => 'Moussa',
            'nom' => 'Ndiaye',
            'sexe' => 'M',
            'date_naissance' => '1990-06-20',
            'lieu_naissance' => 'Thiès',
            'date_entree' => '2025-02-01',
            'poste' => 'Secrétaire',
            'departement_id' => $deptId,
            'email' => 'moussa.e2e@sandiara.sn',
            'telephone' => '+221770000002',
            'statut' => 'Actif',
            'is_active' => true,
        ])->assertCreated();

        $agentId = $agentRes->json('data.id')
            ?? $agentRes->json('agent.id')
            ?? $agentRes->json('id');
        $this->assertNotEmpty($agentId);
        $matricule = $agentRes->json('data.matricule')
            ?? $agentRes->json('agent.matricule');
        $this->assertNotEmpty($matricule, 'Matricule auto généré attendu');

        $this->patchJson('/api/agents/'.$agentId, [
            'poste' => 'Secrétaire principal',
            'date_fin_contrat' => '2027-12-31',
        ])->assertOk();

        $siteRes = $this->postJson('/api/sites', [
            'code' => 'mairie-principale',
            'name' => 'Mairie principale',
            'latitude' => 14.4167,
            'longitude' => -16.8500,
            'radius_meters' => 120,
            'qr_payload' => 'SITE-MAIRIE-E2E',
            'services_rule' => 'ALL',
            'is_active' => true,
        ])->assertCreated();
        $siteId = $siteRes->json('data.id') ?? $siteRes->json('site.id') ?? $siteRes->json('id');
        $this->assertNotEmpty($siteId);

        $this->getJson('/api/agents?q=Moussa')->assertOk();
        $this->deleteJson('/api/sites/'.$siteId)->assertSuccessful();
    }

    // ─── 5. Utilisateurs (comptes) Super vs RH ──────────────────────────────

    public function test_05_accounts_create_list_update_governance(): void
    {
        Sanctum::actingAs($this->super);
        $this->postJson('/api/users', [
            'name' => 'Sous Admin E2E',
            'email' => 'sous.e2e@sandiara.sn',
            'password' => $this->pwd,
            'password_confirmation' => $this->pwd,
            'role_id' => $this->roles['sous_admin']->id,
        ])->assertCreated();

        $users = $this->getJson('/api/users')->assertOk();
        $emails = collect($users->json('data') ?? [])->pluck('email')->all();
        $this->assertContains('super.e2e@sandiara.sn', $emails);

        Sanctum::actingAs($this->rh);
        $listRh = $this->getJson('/api/users')->assertOk();
        $emailsRh = collect($listRh->json('data') ?? [])->pluck('email')->all();
        $this->assertNotContains(
            'super.e2e@sandiara.sn',
            $emailsRh,
            'RH ne doit pas voir les Super dans la liste'
        );

        $this->postJson('/api/users', [
            'name' => 'Hack Super',
            'email' => 'hack.super@sandiara.sn',
            'password' => $this->pwd,
            'password_confirmation' => $this->pwd,
            'role_id' => $this->roles['super_admin']->id,
        ])->assertStatus(422);

        $this->patchJson('/api/users/'.$this->super->id, ['name' => 'Nope'])
            ->assertForbidden();
    }

    // ─── 6. Demandes + décisions + cohérence dates ──────────────────────────

    public function test_06_demandes_create_decide_and_date_coherence(): void
    {
        Sanctum::actingAs($this->agentUser);
        $bad = $this->postJson('/api/demandes', [
            'type_demande' => 'CONGE',
            'date_debut' => '2026-08-20',
            'date_fin' => '2026-08-10',
            'motif' => 'Fin avant début — doit échouer',
        ]);
        $this->assertTrue(in_array($bad->status(), [422, 403], true));

        Sanctum::actingAs($this->rh);
        $created = $this->postJson('/api/demandes', [
            'agent_id' => $this->agent->id,
            'type_demande' => 'CONGE',
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-09-05',
            'motif' => 'Congé annuel E2E',
        ])->assertCreated();

        $demandeId = $created->json('data.id')
            ?? $created->json('demande.id')
            ?? $created->json('id');
        $this->assertNotEmpty($demandeId);

        $this->postJson('/api/demandes/'.$demandeId.'/decide', [
            'decision' => 'APPROUVEE',
            'commentaire' => 'OK E2E',
        ])->assertOk();

        $demande = AbsenceRequest::query()->findOrFail($demandeId);
        $this->assertSame('APPROUVEE', $demande->statut);
        $this->assertSame('2026-09-01', $demande->date_debut?->format('Y-m-d') ?? (string) $demande->date_debut);
    }

    // ─── 7. Pointages, planning, calendrier, missions, HS, sanctions ────────

    public function test_07_presence_planning_calendar_mission_overtime_sanction(): void
    {
        Sanctum::actingAs($this->rh);

        $this->postJson('/api/pointages', [
            'agent_id' => $this->agent->id,
            'type' => 'ENTREE',
            'date_pointage' => '2026-08-22',
            'heure_pointage' => '08:05:00',
            'statut' => 'RETARD',
            'late_minutes' => 5,
            'source' => 'MANUEL',
            'note' => 'Pointage E2E',
        ])->assertCreated();

        $this->postJson('/api/planning-shifts', [
            'departement_id' => $this->dept->id,
            'audience' => 'SERVICE',
            'shift_start' => '08:00',
            'shift_end' => '16:00',
            'manager_name' => 'Chef Technique',
            'required_count' => 3,
            'assigned_count' => 1,
            'statut' => 'CONFIRME',
            'date_effective' => '2026-08-25',
            'is_active' => true,
        ])->assertCreated();

        $this->postJson('/api/holidays', [
            'libelle' => 'Fête E2E',
            'date_holiday' => '2026-12-25',
            'type_holiday' => 'FERIE',
            'is_active' => true,
        ])->assertCreated();

        $this->postJson('/api/missions', [
            'agent_id' => $this->agent->id,
            'titre' => 'Mission terrain E2E',
            'description' => 'Visite quartier',
            'lieu' => 'Sandiara centre',
            'date_debut' => '2026-08-28',
            'date_fin' => '2026-08-29',
            'statut' => 'PLANIFIEE',
        ])->assertCreated();

        Sanctum::actingAs($this->agentUser);
        $this->postJson('/api/overtime-requests', [
            'date_travail' => '2026-08-21',
            'heures_sup' => 2,
            'motif' => 'Urgence E2E',
        ])->assertCreated();

        Sanctum::actingAs($this->rh);
        $this->postJson('/api/sanctions', [
            'agent_id' => $this->agent->id,
            'type_sanction' => 'AVERTISSEMENT',
            'titre' => 'Retard répété',
            'description' => 'Avertissement E2E',
            'date_debut' => '2026-08-22',
            'severite' => 'faible',
            'statut' => 'ACTIVE',
        ])->assertCreated();
    }

    // ─── 8. QR codes + retraite + annonces ─────────────────────────────────

    public function test_08_qr_retraite_annonce_crud(): void
    {
        Sanctum::actingAs($this->rh);

        $qr = $this->postJson('/api/qr-codes', [
            'agent_id' => $this->agent->id,
            'revoke_previous' => true,
            'expires_at' => now()->addYear()->toIso8601String(),
        ])->assertCreated();
        $qrId = $qr->json('data.id') ?? $qr->json('qr_code.id') ?? $qr->json('id');
        $this->assertNotEmpty($qrId);

        Sanctum::actingAs($this->agentUser);
        $this->getJson('/api/qr-codes/mine')->assertOk();

        Sanctum::actingAs($this->rh);
        $other = Agent::query()->create([
            'departement_id' => $this->dept->id,
            'matricule' => 'EMP-E2E-RET',
            'prenom' => 'Ibra',
            'nom' => 'Fall',
            'sexe' => 'M',
            'date_naissance' => '1960-01-01',
            'lieu_naissance' => 'Sandiara',
            'date_entree' => '1990-01-01',
            'poste' => 'Agent senior',
            'statut' => 'Actif',
            'is_active' => true,
        ]);

        $this->postJson('/api/retraites', [
            'agent_id' => $other->id,
            'date_depart' => '2026-12-31',
            'motif' => 'Âge légal',
            'statut' => 'EN_COURS',
        ])->assertCreated();

        $ann = $this->postJson('/api/announcements', [
            'title' => 'Annonce E2E',
            'content' => 'Message mobile E2E',
            'starts_at' => now()->toIso8601String(),
            'expires_at' => now()->addDays(7)->toIso8601String(),
            'is_active' => true,
            'priority' => 10,
        ])->assertCreated();
        $annId = $ann->json('data.id') ?? $ann->json('announcement.id') ?? $ann->json('id');

        $this->patchJson('/api/announcements/'.$annId, [
            'title' => 'Annonce E2E maj',
        ])->assertOk();

        $this->deleteJson('/api/announcements/'.$annId)->assertSuccessful();
    }

    // ─── 9. Notifications & confidentialité ────────────────────────────────

    public function test_09_notifications_isolation_and_mark_read(): void
    {
        /** @var NotificationService $svc */
        $svc = app(NotificationService::class);
        $svc->notifyUser($this->agentUser, 'Privé agent', 'Corps A', 'info', 'e2e');
        $svc->notifyUser($this->rh, 'Privé RH', 'Corps RH', 'info', 'e2e');

        Sanctum::actingAs($this->agentUser);
        $res = $this->getJson('/api/notifications')->assertOk();
        $titles = collect($res->json('data') ?? [])->pluck('title')->all();
        $this->assertContains('Privé agent', $titles);
        $this->assertNotContains('Privé RH', $titles);

        $notifId = AppNotification::query()
            ->where('user_id', $this->agentUser->id)
            ->value('id');
        $this->postJson('/api/notifications/'.$notifId.'/read')->assertOk();

        Sanctum::actingAs($this->agentUser);
        $rhNotif = AppNotification::query()->where('user_id', $this->rh->id)->value('id');
        $this->postJson('/api/notifications/'.$rhNotif.'/read')
            ->assertForbidden();
    }

    // ─── 10. Intrusions : non auth, agent vs admin, audit Super-only ────────

    public function test_10_intrusions_and_confidentiality(): void
    {
        $this->getJson('/api/users')->assertUnauthorized();
        $this->getJson('/api/agents')->assertUnauthorized();
        $this->getJson('/api/audit-logs')->assertUnauthorized();
        $this->postJson('/api/pointages', [
            'agent_id' => $this->agent->id,
            'type' => 'ENTREE',
            'date_pointage' => '2026-08-22',
            'heure_pointage' => '08:00:00',
        ])->assertUnauthorized();

        Sanctum::actingAs($this->agentUser);
        $this->getJson('/api/users')->assertForbidden();
        $this->getJson('/api/audit-logs')->assertForbidden();
        $this->postJson('/api/departements', [
            'code' => 'HACK',
            'nom' => 'Hack',
        ])->assertForbidden();
        $this->postJson('/api/agents', [
            'prenom' => 'X',
            'nom' => 'Y',
            'sexe' => 'M',
            'date_naissance' => '1999-01-01',
            'lieu_naissance' => 'X',
            'departement_id' => $this->dept->id,
        ])->assertForbidden();
        $this->deleteJson('/api/agents/'.$this->agent->id)->assertForbidden();

        Sanctum::actingAs($this->rh);
        $this->getJson('/api/audit-logs')->assertForbidden();

        Sanctum::actingAs($this->super);
        $this->getJson('/api/audit-logs')->assertOk();
    }

    // ─── 11. Suppression / reset compte (cohérence) ─────────────────────────

    public function test_11_user_update_reset_password_and_delete_sous_admin(): void
    {
        Sanctum::actingAs($this->super);
        $created = $this->postJson('/api/users', [
            'name' => 'Temp Delete',
            'email' => 'temp.delete@sandiara.sn',
            'password' => $this->pwd,
            'password_confirmation' => $this->pwd,
            'role_id' => $this->roles['sous_admin']->id,
        ])->assertCreated();

        $uid = $created->json('user.id') ?? $created->json('data.id') ?? $created->json('id');
        $this->assertNotEmpty($uid);

        $this->patchJson('/api/users/'.$uid, [
            'name' => 'Temp Updated',
            'is_active' => true,
        ])->assertOk();

        $this->postJson('/api/users/'.$uid.'/reset-password', [
            'password' => 'NewPass@2026!',
            'password_confirmation' => 'NewPass@2026!',
        ])->assertOk();

        $this->deleteJson('/api/users/'.$uid)->assertSuccessful();
        $this->assertNull(User::query()->find($uid));
    }

    // ─── 12. Exports & stats (rapports sidebar) ─────────────────────────────

    public function test_12_exports_and_stats_endpoints(): void
    {
        Sanctum::actingAs($this->rh);

        foreach (
            [
                '/api/exports/pointages',
                '/api/exports/retards',
                '/api/exports/agents',
                '/api/exports/demandes',
                '/api/stats/presence-by-service',
            ] as $url
        ) {
            $res = $this->get($url);
            $code = $res->baseResponse->getStatusCode();
            $this->assertTrue(
                in_array($code, [200, 201], true),
                "Export/stats {$url} status={$code}"
            );
        }
    }
}
