<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogAccessTest extends TestCase
{
    use RefreshDatabase;

    private Role $superRole;

    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superRole = Role::query()->create([
            'name' => 'super_admin',
            'display_name' => 'Super Admin',
            'description' => 'Test',
            'is_active' => true,
        ]);

        $this->adminRole = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'RH',
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

    public function test_rh_cannot_list_audit_logs(): void
    {
        $rh = $this->makeUser($this->adminRole, 'rh.audit@sandiara.sn');

        $this->actingAs($rh, 'sanctum')
            ->getJson('/api/audit-logs')
            ->assertForbidden();
    }

    public function test_super_can_list_audit_logs_and_sees_rh_actions(): void
    {
        $super = $this->makeUser($this->superRole, 'super.audit@sandiara.sn');
        $rh = $this->makeUser($this->adminRole, 'rh.actor@sandiara.sn');

        AuditLog::query()->create([
            'user_id' => $rh->id,
            'action' => 'agents.create',
            'permission' => 'create',
            'summary' => 'Création · agents',
            'details' => ['path' => '/api/agents'],
            'created_at' => now(),
        ]);

        AuditLog::query()->create([
            'user_id' => $super->id,
            'action' => 'agents.create',
            'permission' => 'create',
            'summary' => 'Création · agents (super)',
            'created_at' => now(),
        ]);

        $res = $this->actingAs($super, 'sanctum')
            ->getJson('/api/audit-logs?others_only=1')
            ->assertOk();

        $actions = collect($res->json('data'))->pluck('summary')->all();
        $this->assertContains('Création · agents', $actions);
        $this->assertNotContains('Création · agents (super)', $actions);
    }

    public function test_creating_departement_is_audited_for_admin(): void
    {
        $rh = $this->makeUser($this->adminRole, 'rh.create@sandiara.sn');

        $this->actingAs($rh, 'sanctum')
            ->postJson('/api/departements', [
                'nom' => 'Audit Service',
                'code' => 'AUD',
                'is_active' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $rh->id,
            'permission' => 'create',
        ]);
    }
}
