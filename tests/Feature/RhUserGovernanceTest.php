<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RhUserGovernanceTest extends TestCase
{
    use RefreshDatabase;

    private Role $superRole;

    private Role $adminRole;

    private Role $agentRole;

    private Role $sousAdminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superRole = Role::query()->create([
            'name' => 'super_admin',
            'display_name' => 'Super',
            'description' => 'Test',
            'is_active' => true,
        ]);
        $this->adminRole = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'RH',
            'description' => 'Test',
            'is_active' => true,
        ]);
        $this->sousAdminRole = Role::query()->create([
            'name' => 'sous_admin',
            'display_name' => 'Sous-admin',
            'description' => 'Test',
            'is_active' => true,
        ]);
        $this->agentRole = Role::query()->create([
            'name' => 'agent',
            'display_name' => 'Agent',
            'description' => 'Test',
            'is_active' => true,
        ]);
    }

    private function makeUser(Role $role, string $email): User
    {
        return User::query()->create([
            'role_id' => $role->id,
            'name' => $email,
            'email' => $email,
            'password' => 'Admin@2026!',
            'is_active' => true,
        ]);
    }

    public function test_rh_cannot_create_super_admin(): void
    {
        $rh = $this->makeUser($this->adminRole, 'rh.gov@sandiara.sn');

        $this->actingAs($rh, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'Hack Super',
                'email' => 'hack.super@sandiara.sn',
                'password' => 'Admin@2026!',
                'password_confirmation' => 'Admin@2026!',
                'role_id' => $this->superRole->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }

    public function test_rh_cannot_create_another_rh(): void
    {
        $rh = $this->makeUser($this->adminRole, 'rh.gov2@sandiara.sn');

        $this->actingAs($rh, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'Autre RH',
                'email' => 'autre.rh@sandiara.sn',
                'password' => 'Admin@2026!',
                'password_confirmation' => 'Admin@2026!',
                'role_id' => $this->adminRole->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role_id']);
    }

    public function test_rh_can_create_sous_admin(): void
    {
        $rh = $this->makeUser($this->adminRole, 'rh.gov3@sandiara.sn');

        $this->actingAs($rh, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'Sous Admin',
                'email' => 'sous.gov@sandiara.sn',
                'password' => 'Admin@2026!',
                'password_confirmation' => 'Admin@2026!',
                'role_id' => $this->sousAdminRole->id,
            ])
            ->assertCreated();
    }

    public function test_rh_cannot_update_super_or_other_rh(): void
    {
        $rh = $this->makeUser($this->adminRole, 'rh.gov4@sandiara.sn');
        $otherRh = $this->makeUser($this->adminRole, 'rh.peer@sandiara.sn');
        $super = $this->makeUser($this->superRole, 'super.gov@sandiara.sn');

        $this->actingAs($rh, 'sanctum')
            ->patchJson('/api/users/'.$super->id, ['name' => 'Nope'])
            ->assertForbidden();

        $this->actingAs($rh, 'sanctum')
            ->patchJson('/api/users/'.$otherRh->id, ['name' => 'Nope'])
            ->assertForbidden();
    }

    public function test_rh_roles_list_excludes_super_and_admin(): void
    {
        $rh = $this->makeUser($this->adminRole, 'rh.gov5@sandiara.sn');

        $names = collect(
            $this->actingAs($rh, 'sanctum')
                ->getJson('/api/roles')
                ->assertOk()
                ->json('data')
        )->pluck('name')->all();

        $this->assertNotContains('super_admin', $names);
        $this->assertNotContains('admin', $names);
        $this->assertContains('sous_admin', $names);
        $this->assertContains('agent', $names);
    }

    public function test_custom_rh_role_sees_same_assignable_roles(): void
    {
        $customRh = Role::query()->create([
            'name' => 'administrateur_rh',
            'display_name' => 'Administrateur RH',
            'description' => 'Perso',
            'is_active' => true,
        ]);
        Role::query()->updateOrCreate(
            ['name' => 'conseiller'],
            ['display_name' => 'Conseiller', 'is_active' => true],
        );

        $rh = $this->makeUser($customRh, 'rh.custom.tabs@sandiara.sn');
        $rh->forceFill([
            'permissions' => ['utilisateurs.manage', 'agents.manage'],
        ])->save();

        $names = collect(
            $this->actingAs($rh, 'sanctum')
                ->getJson('/api/roles')
                ->assertOk()
                ->json('data')
        )->pluck('name')->all();

        $this->assertSame(['sous_admin', 'conseiller', 'agent'], array_values(array_intersect(
            ['sous_admin', 'conseiller', 'agent'],
            $names,
        )));
        $this->assertContains('sous_admin', $names);
        $this->assertContains('conseiller', $names);
        $this->assertContains('agent', $names);
        $this->assertNotContains('super_admin', $names);
        $this->assertNotContains('admin', $names);
        $this->assertCount(3, $names);

        $this->actingAs($rh, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'Sous-admin via RH perso',
                'email' => 'sous.via.custom.rh@sandiara.sn',
                'password' => 'Admin@2026!',
                'password_confirmation' => 'Admin@2026!',
                'role_id' => $this->sousAdminRole->id,
            ])
            ->assertCreated();

        $this->actingAs($rh, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'Super interdit',
                'email' => 'super.interdit@sandiara.sn',
                'password' => 'Admin@2026!',
                'password_confirmation' => 'Admin@2026!',
                'role_id' => $this->superRole->id,
            ])
            ->assertStatus(422);
    }

    public function test_super_can_create_rh(): void
    {
        $super = $this->makeUser($this->superRole, 'super.gov2@sandiara.sn');

        $this->actingAs($super, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'Nouveau RH',
                'email' => 'nouveau.rh@sandiara.sn',
                'password' => 'Admin@2026!',
                'password_confirmation' => 'Admin@2026!',
                'role_id' => $this->adminRole->id,
            ])
            ->assertCreated();
    }

    public function test_rh_list_hides_super_admin_accounts(): void
    {
        $super = $this->makeUser($this->superRole, 'super.hide@sandiara.sn');
        $rh = $this->makeUser($this->adminRole, 'rh.list@sandiara.sn');
        $agent = $this->makeUser($this->agentRole, 'agent.list@sandiara.sn');

        $res = $this->actingAs($rh, 'sanctum')
            ->getJson('/api/users?per_page=100')
            ->assertOk();

        $ids = collect($res->json('data'))->pluck('id')->all();

        $this->assertNotContains($super->id, $ids);
        $this->assertContains($rh->id, $ids);
        $this->assertContains($agent->id, $ids);

        $this->actingAs($rh, 'sanctum')
            ->getJson('/api/users/'.$super->id)
            ->assertForbidden();
    }
}
