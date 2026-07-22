<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Comptes de test — mot de passe : Admin@2026! (hashé via cast User).
     */
    public function run(): void
    {
        $password = 'Admin@2026!';
        $roles = Role::query()->pluck('id', 'name');

        User::query()->updateOrCreate(
            ['email' => 'superadmin@sandiara.sn'],
            [
                'role_id' => $roles['super_admin'] ?? null,
                'name' => 'Super Administrateur',
                'phone' => '+221770000001',
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'admin@sandiara.sn'],
            [
                'role_id' => $roles['admin'] ?? null,
                'name' => 'Administrateur RH',
                'phone' => '+221770000002',
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'sousadmin@sandiara.sn'],
            [
                'role_id' => $roles['sous_admin'] ?? null,
                'name' => 'Sous Administrateur',
                'phone' => '+221770000003',
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $agentUser = User::query()->updateOrCreate(
            ['email' => 'agent.ndiaye@sandiara.sn'],
            [
                'role_id' => $roles['agent'] ?? null,
                'name' => 'Mamadou Ndiaye',
                'phone' => '+221770000010',
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        Agent::query()->updateOrCreate(
            ['matricule' => 'EMP001'],
            [
                'user_id' => $agentUser->id,
                'prenom' => 'Mamadou',
                'nom' => 'Ndiaye',
                'sexe' => 'M',
                'date_naissance' => '1988-03-12',
                'date_entree' => '2015-01-15',
                'poste' => 'Secrétaire municipal',
                'email' => $agentUser->email,
                'telephone' => $agentUser->phone,
                'photo_url' => 'https://i.pravatar.cc/160?img=12',
                'statut' => 'Actif',
                'is_active' => true,
                'solde_conges' => 22.00,
            ]
        );
    }
}
