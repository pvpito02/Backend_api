<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Departement;
use App\Models\User;
use Illuminate\Database\Seeder;

class AgentSeeder extends Seeder
{
    /**
     * Agents de démo alignés sur les mocks admin / mobile.
     * EMP001 est déjà créé dans UserSeeder — on le rattache ici.
     */
    public function run(): void
    {
        $depts = Departement::query()->pluck('id', 'code');

        $agentUser = User::query()->where('email', 'agent.ndiaye@sandiara.sn')->first();

        Agent::query()->updateOrCreate(
            ['matricule' => 'EMP001'],
            [
                'user_id' => $agentUser?->id,
                'prenom' => 'Mamadou',
                'nom' => 'Ndiaye',
                'sexe' => 'M',
                'date_naissance' => '1988-03-12',
                'date_entree' => '2015-01-15',
                'poste' => 'Secrétaire municipal',
                'departement_id' => $depts['SECRETARIAT'] ?? null,
                'email' => 'agent.ndiaye@sandiara.sn',
                'telephone' => '+221770000010',
                'photo_url' => 'https://i.pravatar.cc/160?img=12',
                'statut' => 'Actif',
                'is_active' => true,
                'solde_conges' => 22.00,
            ]
        );

        $rows = [
            ['EMP002', 'Awa', 'Diop', 'F', 'Agent d\'accueil', 'ACCUEIL', 'awa.diop@sandiara.sn', '+221770000011', 47],
            ['EMP003', 'Cheikh', 'Fall', 'M', 'Comptable', 'FINANCES', 'cheikh.fall@sandiara.sn', '+221770000012', 15],
            ['EMP004', 'Fatou', 'Sarr', 'F', 'Chargée RH', 'RH', 'fatou.sarr@sandiara.sn', '+221770000013', 49],
            ['EMP005', 'Ousmane', 'Ba', 'M', 'Agent technique', 'TECH', 'ousmane.ba@sandiara.sn', '+221770000014', 33],
            ['EMP006', 'Aminata', 'Kane', 'F', 'Archiviste', 'ARCHIVES', 'aminata.kane@sandiara.sn', '+221770000015', 45],
            ['EMP007', 'Ibrahima', 'Sow', 'M', 'Agent urbanisme', 'URBANISME', 'ibrahima.sow@sandiara.sn', '+221770000016', 8],
            ['EMP008', 'Modou', 'Fall', 'M', 'Technicien informatique', 'INFORMATIQUE', 'modou.fall@sandiara.sn', '+221770000017', 11],
            ['EMP009', 'Mariama', 'Gueye', 'F', 'Agent état civil', 'ETAT_CIVIL', 'mariama.gueye@sandiara.sn', '+221770000018', 32],
            ['EMP010', 'Khady', 'Niang', 'F', 'Agent courrier', 'COURRIER', 'khady.niang@sandiara.sn', '+221770000019', 44],
            ['EMP011', 'Papa', 'Diallo', 'M', 'Chef service technique', 'TECH', 'papa.diallo@sandiara.sn', '+221770000020', 5],
        ];

        foreach ($rows as [$matricule, $prenom, $nom, $sexe, $poste, $deptCode, $email, $phone, $img]) {
            Agent::query()->updateOrCreate(
                ['matricule' => $matricule],
                [
                    'prenom' => $prenom,
                    'nom' => $nom,
                    'sexe' => $sexe,
                    'date_entree' => '2018-01-01',
                    'poste' => $poste,
                    'departement_id' => $depts[$deptCode] ?? null,
                    'email' => $email,
                    'telephone' => $phone,
                    'photo_url' => "https://i.pravatar.cc/160?img={$img}",
                    'statut' => 'Actif',
                    'is_active' => true,
                    'solde_conges' => 20.00,
                ]
            );
        }

        // Chef technique supervise EMP005
        $chef = Agent::query()->where('matricule', 'EMP011')->first();
        $tech = Agent::query()->where('matricule', 'EMP005')->first();
        if ($chef && $tech) {
            $tech->update(['supervisor_id' => $chef->id]);
        }
    }
}
