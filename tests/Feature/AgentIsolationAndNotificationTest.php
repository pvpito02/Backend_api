<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AppNotification;
use App\Models\Departement;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentIsolationAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Role $adminRole;

    private Role $agentRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Administrateur',
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

    private function makeAgentUser(string $email, string $matricule): array
    {
        $dept = Departement::query()->first() ?? Departement::query()->create([
            'nom' => 'Secrétariat',
            'code' => 'SEC',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'role_id' => $this->agentRole->id,
            'name' => "Agent {$matricule}",
            'email' => $email,
            'password' => 'Admin@2026!',
            'is_active' => true,
        ]);

        $agent = Agent::query()->create([
            'user_id' => $user->id,
            'departement_id' => $dept->id,
            'matricule' => $matricule,
            'prenom' => 'Test',
            'nom' => $matricule,
            'poste' => 'Agent',
            'telephone' => '+221770000099',
            'email' => $email,
            'statut' => 'Actif',
            'is_active' => true,
        ]);

        return [$user->fresh('agent'), $agent];
    }

    private function makeAdmin(): User
    {
        return User::query()->create([
            'role_id' => $this->adminRole->id,
            'name' => 'Admin Test',
            'email' => 'admin.iso@sandiara.sn',
            'password' => 'Admin@2026!',
            'is_active' => true,
        ]);
    }

    public function test_notifications_require_auth(): void
    {
        $this->getJson('/api/notifications')->assertUnauthorized();
        $this->getJson('/api/realtime/poll')->assertUnauthorized();
        $this->getJson('/api/pointages')->assertUnauthorized();
    }

    public function test_agent_only_sees_own_notifications(): void
    {
        [$userA] = $this->makeAgentUser('a@sandiara.sn', 'EMP-A');
        [$userB] = $this->makeAgentUser('b@sandiara.sn', 'EMP-B');

        /** @var NotificationService $notifications */
        $notifications = app(NotificationService::class);
        $notifications->notifyUser($userA, 'Privé A', 'Message A', 'info', 'test');
        $notifications->notifyUser($userB, 'Privé B', 'Message B', 'info', 'test');

        $res = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk();

        $titles = collect($res->json('data'))->pluck('title')->all();
        $this->assertContains('Privé A', $titles);
        $this->assertNotContains('Privé B', $titles);

        // Marquer une notif d’un autre agent → interdit
        $foreign = AppNotification::query()->where('user_id', $userB->id)->firstOrFail();
        $this->actingAs($userA, 'sanctum')
            ->postJson("/api/notifications/{$foreign->id}/read")
            ->assertForbidden();
    }

    public function test_user_resource_never_exposes_password(): void
    {
        $admin = $this->makeAdmin();

        $login = $this->postJson('/api/auth/login', [
            'login' => $admin->email,
            'password' => 'Admin@2026!',
        ])->assertOk();

        $payload = json_encode($login->json());
        $this->assertStringNotContainsString('Admin@2026!', (string) $payload);
        $this->assertArrayNotHasKey('password', $login->json('user') ?? []);
    }

    public function test_admin_mission_notifies_assigned_agent(): void
    {
        $admin = $this->makeAdmin();
        [$agentUser, $agent] = $this->makeAgentUser('mission@sandiara.sn', 'EMP-M');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/missions', [
                'agent_id' => $agent->id,
                'titre' => 'Visite terrain',
                'lieu' => 'Fatick',
                'date_debut' => now()->toDateString(),
                'date_fin' => now()->addDay()->toDateString(),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $agentUser->id,
            'categorie' => 'mission',
        ]);

        $inbox = $this->actingAs($agentUser, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk();

        $this->assertTrue(
            collect($inbox->json('data'))->contains(
                fn ($n) => str_contains((string) ($n['title'] ?? ''), 'Mission')
            )
        );
    }

    public function test_admin_overtime_notifies_assigned_agent(): void
    {
        $admin = $this->makeAdmin();
        [$agentUser, $agent] = $this->makeAgentUser('hs@sandiara.sn', 'EMP-HS');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/overtime-requests', [
                'agent_id' => $agent->id,
                'date_travail' => now()->toDateString(),
                'heures_sup' => 2.5,
                'motif' => 'Conseil municipal',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $agentUser->id,
            'categorie' => 'heures_sup',
        ]);

        // L’agent ne voit que sa demande HS
        $list = $this->actingAs($agentUser, 'sanctum')
            ->getJson('/api/overtime-requests')
            ->assertOk();

        $ids = collect($list->json('data'))->pluck('agent_id')->unique()->all();
        $this->assertSame([$agent->id], $ids);
    }

    public function test_agent_cannot_list_other_agent_pointages_via_filter(): void
    {
        [$userA, $agentA] = $this->makeAgentUser('pa@sandiara.sn', 'EMP-PA');
        [, $agentB] = $this->makeAgentUser('pb@sandiara.sn', 'EMP-PB');

        // Même si agent_id d’un autre est passé, le scope agent doit rester sur soi
        $res = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/pointages?agent_id='.$agentB->id)
            ->assertOk();

        $agentIds = collect($res->json('data'))->pluck('agent_id')->unique()->filter()->all();
        foreach ($agentIds as $id) {
            $this->assertSame($agentA->id, (int) $id);
        }
    }
}
