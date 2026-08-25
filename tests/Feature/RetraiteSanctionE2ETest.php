<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AppNotification;
use App\Models\Departement;
use App\Models\Retraite;
use App\Models\Role;
use App\Models\Sanction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Retraites + Sanctions : CRUD, droits, dates, confidentialité.
 */
class RetraiteSanctionE2ETest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Role> */
    private array $roles = [];

    private User $rh;

    private User $rhNoPerm;

    private User $agentUser;

    private User $agentUser2;

    private Agent $agent;

    private Agent $agent2;

    private Agent $agentSenior;

    private Departement $dept;

    private string $pwd = 'Admin@2026!';

    protected function setUp(): void
    {
        parent::setUp();

        foreach (
            [
                'super_admin' => 'Super',
                'admin' => 'RH',
                'sous_admin' => 'Sous-admin',
                'agent' => 'Agent',
                'conseiller' => 'Conseiller',
            ] as $name => $label
        ) {
            $this->roles[$name] = Role::query()->updateOrCreate(
                ['name' => $name],
                ['display_name' => $label, 'description' => 'RS E2E', 'is_active' => true],
            );
        }

        $this->dept = Departement::query()->create([
            'code' => 'TECH',
            'nom' => 'Technique',
            'is_active' => true,
        ]);

        $this->rh = User::query()->create([
            'role_id' => $this->roles['admin']->id,
            'name' => 'RH RS',
            'email' => 'rh.rs@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
            'permissions' => null,
        ]);

        $this->rhNoPerm = User::query()->create([
            'role_id' => $this->roles['admin']->id,
            'name' => 'RH Limité',
            'email' => 'rh.limited.rs@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
            'permissions' => ['agents.manage'],
        ]);

        $this->agentUser = User::query()->create([
            'role_id' => $this->roles['agent']->id,
            'name' => 'Agent RS',
            'email' => 'agent.rs@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
        ]);

        $this->agentUser2 = User::query()->create([
            'role_id' => $this->roles['agent']->id,
            'name' => 'Agent RS2',
            'email' => 'agent2.rs@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
        ]);

        $this->agent = Agent::query()->create([
            'user_id' => $this->agentUser->id,
            'departement_id' => $this->dept->id,
            'matricule' => 'EMP-RS-01',
            'prenom' => 'Awa',
            'nom' => 'RS',
            'sexe' => 'F',
            'date_naissance' => '1995-01-01',
            'lieu_naissance' => 'Sandiara',
            'date_entree' => '2020-01-01',
            'poste' => 'Agent',
            'statut' => 'Actif',
            'is_active' => true,
        ]);

        $this->agent2 = Agent::query()->create([
            'user_id' => $this->agentUser2->id,
            'departement_id' => $this->dept->id,
            'matricule' => 'EMP-RS-02',
            'prenom' => 'Ibra',
            'nom' => 'RS2',
            'sexe' => 'M',
            'date_naissance' => '1992-01-01',
            'lieu_naissance' => 'Sandiara',
            'date_entree' => '2019-01-01',
            'poste' => 'Agent',
            'statut' => 'Actif',
            'is_active' => true,
        ]);

        $this->agentSenior = Agent::query()->create([
            'departement_id' => $this->dept->id,
            'matricule' => 'EMP-RS-SEN',
            'prenom' => 'Papa',
            'nom' => 'Senior',
            'sexe' => 'M',
            'date_naissance' => '1960-06-15',
            'lieu_naissance' => 'Sandiara',
            'date_entree' => '1985-01-01',
            'poste' => 'Senior',
            'statut' => 'Actif',
            'is_active' => true,
        ]);
    }

    // ─── Sanctions ─────────────────────────────────────────────────────────

    public function test_01_rh_creates_sanction_notifies_agent(): void
    {
        Sanctum::actingAs($this->rh);

        $res = $this->postJson('/api/sanctions', [
            'agent_id' => $this->agent->id,
            'type_sanction' => 'AVERTISSEMENT',
            'titre' => 'Retard répété',
            'description' => 'Plusieurs retards en août',
            'date_debut' => '2026-08-20',
            'date_fin' => '2026-08-27',
            'severite' => 'faible',
        ])->assertCreated();

        $id = $res->json('sanction.id');
        $this->assertNotEmpty($id);
        $this->assertDatabaseHas('sanctions', [
            'id' => $id,
            'agent_id' => $this->agent->id,
            'statut' => 'ACTIVE',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->agentUser->id,
            'categorie' => 'sanction',
        ]);
    }

    public function test_02_sanction_date_fin_before_debut_rejected(): void
    {
        Sanctum::actingAs($this->rh);
        $this->postJson('/api/sanctions', [
            'agent_id' => $this->agent->id,
            'type_sanction' => 'LETTRE',
            'titre' => 'Dates incohérentes',
            'description' => 'Test',
            'date_debut' => '2026-08-20',
            'date_fin' => '2026-08-10',
        ])->assertStatus(422);
    }

    public function test_03_agent_sees_only_own_sanctions(): void
    {
        Sanctum::actingAs($this->rh);
        $this->postJson('/api/sanctions', [
            'agent_id' => $this->agent->id,
            'type_sanction' => 'AVERTISSEMENT',
            'titre' => 'Sanction A',
            'description' => 'Pour A',
            'date_debut' => '2026-08-01',
        ])->assertCreated();
        $this->postJson('/api/sanctions', [
            'agent_id' => $this->agent2->id,
            'type_sanction' => 'LETTRE',
            'titre' => 'Sanction B',
            'description' => 'Pour B',
            'date_debut' => '2026-08-01',
        ])->assertCreated();

        Sanctum::actingAs($this->agentUser);
        $list = $this->getJson('/api/sanctions')->assertOk();
        $agentIds = collect($list->json('data'))->pluck('agent_id')->unique()->values()->all();
        $this->assertSame([$this->agent->id], $agentIds);

        $foreign = Sanction::query()->where('agent_id', $this->agent2->id)->firstOrFail();
        $this->getJson('/api/sanctions/'.$foreign->id)->assertForbidden();
        $this->deleteJson('/api/sanctions/'.$foreign->id)->assertForbidden();
    }

    public function test_04_rh_updates_and_deletes_sanction(): void
    {
        Sanctum::actingAs($this->rh);
        $created = $this->postJson('/api/sanctions', [
            'agent_id' => $this->agent->id,
            'type_sanction' => 'SUSPENSION',
            'titre' => 'Suspension temporaire',
            'description' => '3 jours',
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-09-03',
            'severite' => 'elevee',
        ])->assertCreated();

        $id = $created->json('sanction.id');
        $this->patchJson('/api/sanctions/'.$id, [
            'statut' => 'TERMINEE',
        ])->assertOk()
            ->assertJsonPath('sanction.statut', 'TERMINEE');

        $this->deleteJson('/api/sanctions/'.$id)->assertOk();
        $this->assertNull(Sanction::query()->find($id));
    }

    public function test_05_rh_without_sanctions_perm_cannot_create(): void
    {
        Sanctum::actingAs($this->rhNoPerm);
        $this->postJson('/api/sanctions', [
            'agent_id' => $this->agent->id,
            'type_sanction' => 'AVERTISSEMENT',
            'titre' => 'Nope',
            'description' => 'Nope',
            'date_debut' => '2026-08-01',
        ])->assertForbidden();
    }

    // ─── Retraites ─────────────────────────────────────────────────────────

    public function test_06_retraite_create_unique_and_mark_agent(): void
    {
        Sanctum::actingAs($this->rh);

        $res = $this->postJson('/api/retraites', [
            'agent_id' => $this->agentSenior->id,
            'date_depart' => '2026-12-31',
            'motif' => 'Âge légal',
            'montant_pension' => 150000,
            'mark_agent_retraite' => true,
        ])->assertCreated();

        $id = $res->json('retraite.id');
        $this->assertNotEmpty($id);

        $this->agentSenior->refresh();
        $this->assertSame('Retraité', $this->agentSenior->statut);
        $this->assertFalse((bool) $this->agentSenior->is_active);

        // Doublon interdit
        $this->postJson('/api/retraites', [
            'agent_id' => $this->agentSenior->id,
            'date_depart' => '2027-01-01',
        ])->assertStatus(422);
    }

    public function test_07_retraite_validate_marks_agent_retired(): void
    {
        Sanctum::actingAs($this->rh);
        $created = $this->postJson('/api/retraites', [
            'agent_id' => $this->agent2->id,
            'date_depart' => '2026-11-01',
            'motif' => 'Départ anticipé',
            'statut' => 'EN_COURS',
        ])->assertCreated();

        $id = $created->json('retraite.id');
        $this->assertSame('Actif', $this->agent2->fresh()->statut);

        $this->patchJson('/api/retraites/'.$id, [
            'statut' => 'VALIDE',
        ])->assertOk();

        $this->agent2->refresh();
        $this->assertSame('Retraité', $this->agent2->statut);
        $this->assertFalse((bool) $this->agent2->is_active);
    }

    public function test_08_retraite_alerts_endpoint(): void
    {
        Sanctum::actingAs($this->rh);
        $res = $this->getJson('/api/retraites/alerts')->assertOk();
        $this->assertArrayHasKey('config', $res->json());
        $this->assertArrayHasKey('alerts', $res->json());
        $this->assertArrayHasKey('age_minimum', $res->json('config'));

        // Senior ~66 ans → doit apparaître dans les alertes
        $ids = collect($res->json('alerts'))->pluck('agent_id')->all();
        $this->assertContains($this->agentSenior->id, $ids);
    }

    public function test_09_agent_cannot_manage_retraites(): void
    {
        Sanctum::actingAs($this->agentUser);
        $this->getJson('/api/retraites')->assertForbidden();
        $this->getJson('/api/retraites/alerts')->assertForbidden();
        $this->postJson('/api/retraites', [
            'agent_id' => $this->agent->id,
            'date_depart' => '2030-01-01',
        ])->assertForbidden();
    }

    public function test_10_rh_lists_filters_and_deletes_retraite(): void
    {
        Sanctum::actingAs($this->rh);
        $created = $this->postJson('/api/retraites', [
            'agent_id' => $this->agent->id,
            'date_depart' => '2028-06-01',
            'statut' => 'EN_COURS',
        ])->assertCreated();

        $id = $created->json('retraite.id');

        $this->getJson('/api/retraites?statut=EN_COURS')->assertOk();
        $this->getJson('/api/retraites?agent_id='.$this->agent->id)->assertOk();
        $this->getJson('/api/retraites/'.$id)->assertOk();

        $this->deleteJson('/api/retraites/'.$id)->assertOk();
        $this->assertNull(Retraite::query()->find($id));
    }
}
