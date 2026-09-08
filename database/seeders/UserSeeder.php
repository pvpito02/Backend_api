<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Comptes de démarrage production — mot de passe : Admin@2026!
     * (à changer immédiatement après déploiement).
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
                'name' => 'Administrateur',
                'phone' => '+221770000002',
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'rh@sandiara.sn'],
            [
                'role_id' => $roles['rh'] ?? null,
                'name' => 'Ressources Humaines',
                'phone' => '+221770000003',
                'password' => $password,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
