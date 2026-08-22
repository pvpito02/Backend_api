<?php

namespace Tests\Feature;

use App\Models\AbsenceRequest;
use App\Models\Agent;
use App\Models\Departement;
use App\Models\Pointage;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Couche 2 — approfondissement cœur :
 * entrée/sortie, cooldown, GPS, sync offline, décisions, missions/HS, intrusions fines.
 * (toujours hors sanctions / retraites / exports)
 */
class CoreWorkflowDeepE2ETest extends TestCase
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
                'sous_admin' => 'Sous-admin',
                'conseiller' => 'Conseiller',
                'agent' => 'Agent',
            ] as $name => $label
        ) {
            $this->roles[$name] = Role::query()->updateOrCreate(
                ['name' => $name],
                ['display_name' => $label, 'description' => 'Deep E2E', 'is_active' => true],
            );
        }

        $this->rh = User::query()->create([
            'role_id' => $this->roles['admin']->id,
            'name' => 'RH Deep',
            'email' => 'rh.deep@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
        ]);

        $this->dept = Departement::query()->create([
            'code' => 'TECH',
            'nom' => 'Technique',
            'is_active' => true,
        ]);

        $this->site = Site::query()->create([
            'code' => 'borne-deep',
            'name' => 'Borne Deep',
            'latitude' => 14.4167,
            'longitude' => -16.8500,
            'radius_meters' => 100,
            'qr_payload' => 'SITE-DEEP-E2E',
            'services_rule' => 'ALL',
            'is_active' => true,
        ]);

        $this->agentUser = User::query()->create([
            'role_id' => $this->roles['agent']->id,
            'name' => 'Agent Deep',
            'email' => 'agent.deep@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
        ]);

        $this->agent = Agent::query()->create([
            'user_id' => $this->agentUser->id,
            'departement_id' => $this->dept->id,
            'matricule' => 'EMP-DEEP-01',
            'prenom' => 'Agent',
            'nom' => 'Deep',
            'sexe' => 'M',
            'date_naissance' => '1990-04-04',
            'lieu_naissance' => 'Sandiara',
            'date_entree' => '2024-01-01',
            'poste' => 'Agent',
            'statut' => 'Actif',
            'is_active' => true,
        ]);
    }

    private function scanAt(string $iso, string $type = 'ENTREE', ?array $extra = []): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($this->agentUser);

        return $this->postJson('/api/pointages/scan', array_merge([
            'qr_payload' => $this->site->qr_payload,
            'type' => $type,
            'latitude' => 14.4167,
            'longitude' => -16.8500,
            'device_id' => 'deep-phone',
            'scanned_at' => $iso,
        ], $extra ?? []));
    }

    // ─── Pointage approfondi ───────────────────────────────────────────────

    public function test_01_entree_then_sortie_after_cooldown(): void
    {
        // Vendredi 21 août 2026
        $this->scanAt('2026-08-21T08:00:00+00:00', 'ENTREE')->assertCreated();

        $sortie = $this->scanAt('2026-08-21T08:35:00+00:00', 'SORTIE')->assertCreated();
        $this->assertSame('SORTIE', $sortie->json('pointage.type'));

        $types = Pointage::query()
            ->where('agent_id', $this->agent->id)
            ->orderBy('id')
            ->pluck('type')
            ->all();
        $this->assertSame(['ENTREE', 'SORTIE'], $types);
    }

    public function test_02_cooldown_blocks_rapid_rescan(): void
    {
        $this->scanAt('2026-08-21T09:00:00+00:00', 'ENTREE')->assertCreated();

        $this->scanAt('2026-08-21T09:10:00+00:00', 'SORTIE')
            ->assertStatus(422);
    }

    public function test_03_gps_out_of_zone_rejected(): void
    {
        Sanctum::actingAs($this->agentUser);
        $this->postJson('/api/pointages/scan', [
            'qr_payload' => $this->site->qr_payload,
            'type' => 'ENTREE',
            // ~2 km plus loin → hors rayon 100 m
            'latitude' => 14.4300,
            'longitude' => -16.8500,
            'scanned_at' => '2026-08-21T10:00:00+00:00',
        ])->assertStatus(422);
    }

    public function test_04_unknown_qr_without_gps_rejected(): void
    {
        Sanctum::actingAs($this->agentUser);
        $this->postJson('/api/pointages/scan', [
            'qr_payload' => 'QR-INCONNU-XYZ',
            'type' => 'ENTREE',
            'scanned_at' => '2026-08-21T11:00:00+00:00',
        ])->assertStatus(422);
    }

    public function test_05_offline_sync_batch(): void
    {
        Sanctum::actingAs($this->agentUser);
        $res = $this->postJson('/api/pointages/sync', [
            'items' => [
                [
                    'client_id' => 'c1',
                    'qr_payload' => $this->site->qr_payload,
                    'type' => 'ENTREE',
                    'latitude' => 14.4167,
                    'longitude' => -16.8500,
                    'scanned_at' => '2026-08-20T08:05:00+00:00',
                ],
                [
                    'client_id' => 'c2',
                    'qr_payload' => $this->site->qr_payload,
                    'type' => 'SORTIE',
                    'latitude' => 14.4167,
                    'longitude' => -16.8500,
                    'scanned_at' => '2026-08-20T08:40:00+00:00',
                ],
            ],
        ])->assertOk();

        $results = $res->json('results');
        $this->assertCount(2, $results);
        $this->assertTrue($results[0]['ok'] ?? false);
        $this->assertTrue($results[1]['ok'] ?? false);
    }

    // ─── Demandes : rejet + agent ne décide pas ────────────────────────────

    public function test_06_rh_rejects_demande_agent_cannot_decide(): void
    {
        Sanctum::actingAs($this->agentUser);
        $created = $this->postJson('/api/demandes', [
            'type_demande' => 'CONGE',
            'date_debut' => '2026-09-15',
            'date_fin' => '2026-09-18',
            'motif' => 'Congé deep E2E',
        ])->assertCreated();

        $id = $created->json('data.id')
            ?? $created->json('demande.id')
            ?? $created->json('id');

        // Agent tente de s’auto-approuver
        $this->postJson('/api/demandes/'.$id.'/decide', [
            'decision' => 'APPROUVEE',
        ])->assertForbidden();

        Sanctum::actingAs($this->rh);
        $this->postJson('/api/demandes/'.$id.'/decide', [
            'decision' => 'REJETEE',
            'motif_rejet' => 'Effectif insuffisant',
        ])->assertOk();

        $this->assertSame('REJETEE', AbsenceRequest::query()->findOrFail($id)->statut);
    }

    // ─── Missions & heures sup ─────────────────────────────────────────────

    public function test_07_mission_notifies_agent(): void
    {
        Sanctum::actingAs($this->rh);
        $this->postJson('/api/missions', [
            'agent_id' => $this->agent->id,
            'titre' => 'Visite marché',
            'lieu' => 'Sandiara',
            'date_debut' => '2026-08-26',
            'date_fin' => '2026-08-26',
            'statut' => 'PLANIFIEE',
        ])->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->agentUser->id,
            'categorie' => 'mission',
        ]);

        Sanctum::actingAs($this->agentUser);
        $inbox = $this->getJson('/api/notifications')->assertOk();
        $this->assertTrue(
            collect($inbox->json('data'))->contains(
                fn ($n) => str_contains((string) ($n['title'] ?? ''), 'Mission')
                    || ($n['categorie'] ?? null) === 'mission'
            )
        );
    }

    public function test_08_overtime_agent_creates_rh_lists(): void
    {
        Sanctum::actingAs($this->agentUser);
        $this->postJson('/api/overtime-requests', [
            'date_travail' => '2026-08-20',
            'heures_sup' => 2,
            'motif' => 'Conseil municipal',
        ])->assertCreated();

        Sanctum::actingAs($this->rh);
        $list = $this->getJson('/api/overtime-requests')->assertOk();
        $this->assertNotEmpty($list->json('data'));
    }

    // ─── Conseiller = terrain ──────────────────────────────────────────────

    public function test_09_conseiller_login_and_cannot_manage_users(): void
    {
        $conseiller = User::query()->create([
            'role_id' => $this->roles['conseiller']->id,
            'name' => 'Conseiller Deep',
            'email' => 'conseiller.deep@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
        ]);

        Agent::query()->create([
            'user_id' => $conseiller->id,
            'departement_id' => $this->dept->id,
            'matricule' => 'EMP-CON-01',
            'prenom' => 'Conseil',
            'nom' => 'Deep',
            'sexe' => 'M',
            'date_naissance' => '1985-01-01',
            'lieu_naissance' => 'Sandiara',
            'date_entree' => '2020-01-01',
            'poste' => 'Conseiller',
            'statut' => 'Actif',
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'login' => $conseiller->email,
            'password' => $this->pwd,
            'device_name' => 'pointage_mobile',
        ])->assertOk()
            ->assertJsonPath('user.role.name', 'conseiller');

        Sanctum::actingAs($conseiller);
        $this->getJson('/api/users')->assertForbidden();
        $this->postJson('/api/agents', [
            'prenom' => 'X',
            'nom' => 'Y',
            'sexe' => 'M',
            'date_naissance' => '1990-01-01',
            'lieu_naissance' => 'X',
            'departement_id' => $this->dept->id,
        ])->assertForbidden();
    }

    // ─── Annulation demande ────────────────────────────────────────────────

    public function test_10_agent_cancels_own_pending_demande(): void
    {
        Sanctum::actingAs($this->agentUser);
        $created = $this->postJson('/api/demandes', [
            'type_demande' => 'ABSENCE',
            'date_debut' => '2026-10-01',
            'date_fin' => '2026-10-01',
            'motif' => 'À annuler',
        ])->assertCreated();

        $id = $created->json('data.id')
            ?? $created->json('demande.id')
            ?? $created->json('id');

        $this->postJson('/api/demandes/'.$id.'/cancel')->assertOk();
        $statut = AbsenceRequest::query()->findOrFail($id)->statut;
        $this->assertContains($statut, ['ANNULEE', 'CANCELLED', 'annulee']);
    }
}
