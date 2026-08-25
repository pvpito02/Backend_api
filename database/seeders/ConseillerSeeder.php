<?php

namespace Database\Seeders;

use App\Models\Departement;
use App\Models\Role;
use App\Models\User;
use App\Services\StaffPointageProfileService;
use Illuminate\Database\Seeder;

class ConseillerSeeder extends Seeder
{
    /**
     * Compte conseiller de test (mobile only) — mot de passe : Admin@2026!
     * Fiche agent + QR créés automatiquement.
     */
    public function run(): void
    {
        $roleId = Role::query()->where('name', 'conseiller')->value('id');
        if (! $roleId) {
            $this->command?->warn('Rôle conseiller introuvable — lancez RoleSeeder d’abord.');

            return;
        }

        $user = User::query()->updateOrCreate(
            ['email' => 'conseiller@sandiara.sn'],
            [
                'role_id' => $roleId,
                'name' => 'Aïssatou Conseiller',
                'phone' => '+221770000020',
                'password' => 'Admin@2026!',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $deptId = Departement::query()->where('code', 'SECRETARIAT')->value('id')
            ?? Departement::query()->value('id');

        app(StaffPointageProfileService::class)->ensureFor($user, [
            'prenom' => 'Aïssatou',
            'nom' => 'Conseiller',
            'poste' => 'Conseiller municipal',
            'departement_id' => $deptId,
        ]);
    }
}
