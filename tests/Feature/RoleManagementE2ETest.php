<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleManagementE2ETest extends TestCase
{
    use RefreshDatabase;

    private string $pwd = 'Admin@2026!';

    private Role $superRole;

    private Role $adminRole;

    private Role $agentRole;

    private User $super;

    private User $rh;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (
            [
                'super_admin' => 'Super',
                'admin' => 'Admin',
                'agent' => 'Agent',
                'sous_admin' => 'Sous-admin',
                'conseiller' => 'Conseiller',
            ] as $name => $label
        ) {
            $role = Role::query()->updateOrCreate(
                ['name' => $name],
                ['display_name' => $label, 'description' => $label, 'is_active' => true],
            );
            if ($name === 'super_admin') {
                $this->superRole = $role;
            }
            if ($name === 'admin') {
                $this->adminRole = $role;
            }
            if ($name === 'agent') {
                $this->agentRole = $role;
            }
        }

        $this->super = User::query()->create([
            'role_id' => $this->superRole->id,
            'name' => 'Super Roles',
            'email' => 'super.roles@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
        ]);

        $this->rh = User::query()->create([
            'role_id' => $this->adminRole->id,
            'name' => 'RH Roles',
            'email' => 'rh.roles@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
        ]);
    }

    public function test_super_lists_roles_with_user_counts(): void
    {
        Sanctum::actingAs($this->super);
        $res = $this->getJson('/api/roles?manage=1')->assertOk();
        $this->assertNotEmpty($res->json('data'));
        $superRow = collect($res->json('data'))->firstWhere('name', 'super_admin');
        $this->assertNotNull($superRow);
        $this->assertTrue($superRow['is_system']);
        $this->assertSame(1, $superRow['users_count']);
    }

    public function test_rh_cannot_manage_roles(): void
    {
        Sanctum::actingAs($this->rh);
        $this->getJson('/api/roles?manage=1')->assertOk(); // index formulaire OK
        // manage=1 pour RH ne doit pas exposer le catalogue enrichi : on reste sur liste simple
        $data = $this->getJson('/api/roles?manage=1')->json('data');
        $this->assertArrayNotHasKey('users_count', $data[0] ?? []);

        $this->postJson('/api/roles', [
            'name' => 'maire',
            'display_name' => 'Maire',
        ])->assertForbidden();
    }

    public function test_super_creates_updates_and_deletes_custom_role(): void
    {
        Sanctum::actingAs($this->super);

        $created = $this->postJson('/api/roles', [
            'name' => 'maire',
            'display_name' => 'Maire',
            'description' => 'Élu municipal',
        ])->assertCreated();

        $id = $created->json('role.id');
        $this->assertFalse($created->json('role.is_system'));

        $this->patchJson('/api/roles/'.$id, [
            'display_name' => 'Monsieur le Maire',
        ])->assertOk()
            ->assertJsonPath('role.display_name', 'Monsieur le Maire');

        $this->deleteJson('/api/roles/'.$id)->assertOk();
        $this->assertNull(Role::query()->find($id));
    }

    public function test_cannot_delete_system_role_or_role_with_users(): void
    {
        Sanctum::actingAs($this->super);

        $this->deleteJson('/api/roles/'.$this->agentRole->id)
            ->assertStatus(422);

        $custom = Role::query()->create([
            'name' => 'observateur',
            'display_name' => 'Observateur',
            'is_active' => true,
        ]);
        User::query()->create([
            'role_id' => $custom->id,
            'name' => 'Obs',
            'email' => 'obs@sandiara.sn',
            'password' => $this->pwd,
            'is_active' => true,
        ]);

        $this->deleteJson('/api/roles/'.$custom->id)->assertStatus(422);
    }

    public function test_cannot_reuse_system_slug(): void
    {
        Sanctum::actingAs($this->super);
        $this->postJson('/api/roles', [
            'name' => 'admin',
            'display_name' => 'Fake Admin',
        ])->assertStatus(422);
    }
}
