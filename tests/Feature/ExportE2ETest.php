<?php

namespace Tests\Feature;

use App\Models\AbsenceRequest;
use App\Models\Agent;
use App\Models\Departement;
use App\Models\Pointage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Exports CSV : droits, filtres, contenu cohérent avec le métier.
 */
class ExportE2ETest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, Role> */
    private array $roles = [];

    private User $super;

    private User $rh;

    private User $rhNoExport;

    private User $sousAdmin;

    private User $agentUser;

    private Agent $agent;

    private Agent $agent2;

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
                ['display_name' => $label, 'description' => 'Export E2E', 'is_active' => true],
            );
        }

        $this->dept = Departement::query()->create([
            'code' => 'TECH',
            'nom' => 'Technique',
            'is_active' => true,
        ]);

        $this->super = User::query()->create([
            'role_id' => $this->roles['super_admin']->id,
            'name' => 'Super Export',
            'email' => 'super.export@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
        ]);

        $this->rh = User::query()->create([
            'role_id' => $this->roles['admin']->id,
            'name' => 'RH Export',
            'email' => 'rh.export@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
            'permissions' => null, // défauts complets incl. rapports.export
        ]);

        $this->rhNoExport = User::query()->create([
            'role_id' => $this->roles['admin']->id,
            'name' => 'RH Sans Export',
            'email' => 'rh.noexport@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
            'permissions' => ['agents.manage', 'demandes.decide'],
        ]);

        $this->sousAdmin = User::query()->create([
            'role_id' => $this->roles['sous_admin']->id,
            'name' => 'Sous Export',
            'email' => 'sous.export@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
        ]);

        $this->agentUser = User::query()->create([
            'role_id' => $this->roles['agent']->id,
            'name' => 'Agent Export',
            'email' => 'agent.export@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
        ]);

        $this->agent = Agent::query()->create([
            'user_id' => $this->agentUser->id,
            'departement_id' => $this->dept->id,
            'matricule' => 'EMP-EXP-01',
            'prenom' => 'Awa',
            'nom' => 'Export',
            'sexe' => 'F',
            'date_naissance' => '1992-01-01',
            'lieu_naissance' => 'Sandiara',
            'date_entree' => '2024-01-01',
            'poste' => 'Agent',
            'email' => 'awa.export@sandiara.sn',
            'telephone' => '+221770000010',
            'statut' => 'Actif',
            'is_active' => true,
        ]);

        $this->agent2 = Agent::query()->create([
            'departement_id' => $this->dept->id,
            'matricule' => 'EMP-EXP-02',
            'prenom' => 'Ibra',
            'nom' => 'ExportB',
            'sexe' => 'M',
            'date_naissance' => '1990-01-01',
            'lieu_naissance' => 'Sandiara',
            'date_entree' => '2024-01-01',
            'poste' => 'Agent',
            'statut' => 'Actif',
            'is_active' => true,
        ]);

        Pointage::query()->create([
            'agent_id' => $this->agent->id,
            'type' => 'ENTREE',
            'date_pointage' => '2026-08-10',
            'heure_pointage' => '08:05:00',
            'statut' => 'RETARD',
            'late_minutes' => 5,
            'source' => 'QR',
        ]);
        Pointage::query()->create([
            'agent_id' => $this->agent->id,
            'type' => 'SORTIE',
            'date_pointage' => '2026-08-10',
            'heure_pointage' => '16:00:00',
            'statut' => 'A_L_HEURE',
            'late_minutes' => 0,
            'source' => 'QR',
        ]);
        Pointage::query()->create([
            'agent_id' => $this->agent2->id,
            'type' => 'ENTREE',
            'date_pointage' => '2026-08-15',
            'heure_pointage' => '08:00:00',
            'statut' => 'A_L_HEURE',
            'late_minutes' => 0,
            'source' => 'MANUEL',
        ]);

        AbsenceRequest::query()->create([
            'agent_id' => $this->agent->id,
            'type_demande' => 'CONGE',
            'date_debut' => '2026-09-01',
            'date_fin' => '2026-09-05',
            'motif' => 'Congé export',
            'statut' => 'EN_ATTENTE',
        ]);
        AbsenceRequest::query()->create([
            'agent_id' => $this->agent2->id,
            'type_demande' => 'PERMISSION',
            'date_debut' => '2026-07-01',
            'date_fin' => '2026-07-01',
            'motif' => 'Ancienne',
            'statut' => 'APPROUVEE',
        ]);
    }

    private function csvBody(string $url): string
    {
        $res = $this->get($url);
        $res->assertOk();
        $this->assertStringContainsString('text/csv', (string) $res->headers->get('content-type'));

        return $res->streamedContent();
    }

    // ─── Droits ────────────────────────────────────────────────────────────

    public function test_01_agent_cannot_export(): void
    {
        Sanctum::actingAs($this->agentUser);
        foreach (['pointages', 'retards', 'agents', 'demandes'] as $kind) {
            $this->get('/api/exports/'.$kind)->assertForbidden();
        }
    }

    public function test_02_rh_without_export_permission_forbidden(): void
    {
        Sanctum::actingAs($this->rhNoExport);
        $this->get('/api/exports/pointages')->assertForbidden();
        $this->get('/api/exports/agents')->assertForbidden();
    }

    public function test_03_rh_super_sous_admin_can_export(): void
    {
        foreach ([$this->rh, $this->super, $this->sousAdmin] as $user) {
            Sanctum::actingAs($user);
            $this->get('/api/exports/pointages')->assertOk();
            $this->get('/api/exports/retards')->assertOk();
            $this->get('/api/exports/agents')->assertOk();
            $this->get('/api/exports/demandes')->assertOk();
        }
    }

    // ─── Contenu / cohérence ───────────────────────────────────────────────

    public function test_04_pointages_csv_headers_and_filters(): void
    {
        Sanctum::actingAs($this->rh);

        $all = $this->csvBody('/api/exports/pointages');
        $this->assertStringContainsString('Date;Heure;Matricule;Agent;Service;Type;Statut', $all);
        $this->assertStringContainsString('EMP-EXP-01', $all);
        $this->assertStringContainsString('EMP-EXP-02', $all);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $all); // BOM UTF-8

        $filtered = $this->csvBody(
            '/api/exports/pointages?from=2026-08-14&to=2026-08-16&agent_id='.$this->agent2->id
        );
        $this->assertStringContainsString('EMP-EXP-02', $filtered);
        $this->assertStringNotContainsString('EMP-EXP-01', $filtered);

        $byStatut = $this->csvBody('/api/exports/pointages?statut=RETARD');
        $this->assertStringContainsString('RETARD', $byStatut);
        $this->assertStringNotContainsString('SORTIE', $byStatut);
    }

    public function test_05_retards_csv_only_late_entries(): void
    {
        Sanctum::actingAs($this->rh);

        $csv = $this->csvBody('/api/exports/retards?from=2026-08-01&to=2026-08-31');
        $this->assertStringContainsString('Date;Agent;Matricule;Heure;Statut', $csv);
        $this->assertStringContainsString('Retard (min)', $csv);
        $this->assertStringContainsString('EMP-EXP-01', $csv);
        $this->assertStringContainsString('RETARD', $csv);
        // Pas de sortie / pas d’entrée à l’heure
        $this->assertStringNotContainsString('SORTIE', $csv);
        $this->assertStringNotContainsString('EMP-EXP-02', $csv);

        $byAgent = $this->csvBody('/api/exports/retards?agent_id='.$this->agent->id);
        $this->assertStringContainsString('EMP-EXP-01', $byAgent);
    }

    public function test_06_agents_csv_includes_active_row(): void
    {
        Sanctum::actingAs($this->rh);

        $csv = $this->csvBody('/api/exports/agents');
        $this->assertStringContainsString('Matricule;Prénom;Nom;Poste;Service', $csv);
        $this->assertStringContainsString('EMP-EXP-01', $csv);
        $this->assertStringContainsString('Awa', $csv);
        $this->assertStringContainsString('Technique', $csv);

        $activeOnly = $this->csvBody('/api/exports/agents?is_active=1');
        $this->assertStringContainsString('EMP-EXP-01', $activeOnly);
    }

    public function test_07_demandes_csv_filters_by_periode_metier(): void
    {
        Sanctum::actingAs($this->rh);

        $all = $this->csvBody('/api/exports/demandes');
        $this->assertStringContainsString('ID;Matricule;Agent;Type;Début;Fin;Statut;Motif', $all);
        $this->assertStringContainsString('CONGE', $all);
        $this->assertStringContainsString('PERMISSION', $all);

        // Filtre sur dates de congé (date_debut), pas created_at
        $sept = $this->csvBody('/api/exports/demandes?from=2026-09-01&to=2026-09-30');
        $this->assertStringContainsString('CONGE', $sept);
        $this->assertStringNotContainsString('PERMISSION', $sept);

        $pending = $this->csvBody('/api/exports/demandes?statut=EN_ATTENTE');
        $this->assertStringContainsString('EN_ATTENTE', $pending);
        $this->assertStringNotContainsString('APPROUVEE', $pending);
    }
}
