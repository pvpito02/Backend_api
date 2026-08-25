<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private function seedRoles(): void
    {
        foreach (
            [
                ['name' => 'super_admin', 'display_name' => 'Super Admin'],
                ['name' => 'admin', 'display_name' => 'Admin'],
                ['name' => 'sous_admin', 'display_name' => 'Sous-admin'],
                ['name' => 'agent', 'display_name' => 'Agent'],
            ] as $role
        ) {
            Role::query()->create($role);
        }
    }

    public function test_super_can_create_admin_with_subset_of_permissions(): void
    {
        $this->seedRoles();
        $super = User::factory()->create([
            'role_id' => Role::query()->where('name', 'super_admin')->value('id'),
        ]);
        Sanctum::actingAs($super);

        $adminRoleId = Role::query()->where('name', 'admin')->value('id');

        $res = $this->postJson('/api/users', [
            'name' => 'RH Limité',
            'email' => 'rh.limite@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'role_id' => $adminRoleId,
            'permissions' => ['agents.manage', 'demandes.decide'],
        ]);

        $res->assertCreated();
        $user = User::query()->where('email', 'rh.limite@example.com')->first();
        $this->assertNotNull($user);
        $this->assertEqualsCanonicalizing(
            ['agents.manage', 'demandes.decide'],
            $user->permissions,
        );
        $this->assertTrue($user->staffCan('agents.manage'));
        $this->assertFalse($user->staffCan('qr.manage'));
    }

    public function test_rh_without_utilisateurs_permission_cannot_create_users(): void
    {
        $this->seedRoles();
        $rh = User::factory()->create([
            'role_id' => Role::query()->where('name', 'admin')->value('id'),
            'permissions' => ['agents.manage'],
        ]);
        Sanctum::actingAs($rh);

        $this->postJson('/api/users', [
            'name' => 'Agent X',
            'email' => 'agent.x@example.com',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
            'role_id' => Role::query()->where('name', 'agent')->value('id'),
        ])->assertForbidden();
    }

    public function test_staff_permissions_catalog_is_super_only(): void
    {
        $this->seedRoles();
        $rh = User::factory()->create([
            'role_id' => Role::query()->where('name', 'admin')->value('id'),
        ]);
        Sanctum::actingAs($rh);
        $this->getJson('/api/staff-permissions')->assertForbidden();

        $super = User::factory()->create([
            'role_id' => Role::query()->where('name', 'super_admin')->value('id'),
        ]);
        Sanctum::actingAs($super);
        $this->getJson('/api/staff-permissions')
            ->assertOk()
            ->assertJsonStructure(['data', 'defaults']);
    }
}
