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

    public function test_web_only_notifications_hidden_from_mobile_inbox(): void
    {
        $admin = $this->makeAdmin();
        [$agentUser] = $this->makeAgentUser('decided@sandiara.sn', 'EMP-DEC');

        /** @var NotificationService $notifications */
        $notifications = app(NotificationService::class);

        $notifications->notifyUser(
            $admin,
            'Nouvelle demande CONGE',
            'À traiter sur le web.',
            'confirmation',
            'conge',
            'AbsenceRequest',
            1,
            playSound: true,
            channel: AppNotification::CHANNEL_WEB,
        );

        $notifications->notifyUser(
            $admin,
            'Demande CONGE approuvée',
            'Votre demande personnelle a été approuvée.',
            'approbation',
            'conge',
            'AbsenceRequest',
            2,
            playSound: true,
            channel: AppNotification::CHANNEL_BOTH,
        );

        $mobileInbox = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/notifications?channel=mobile')
            ->assertOk();

        $mobileTitles = collect($mobileInbox->json('data'))->pluck('title')->all();
        $this->assertNotContains('Nouvelle demande CONGE', $mobileTitles);
        $this->assertContains('Demande CONGE approuvée', $mobileTitles);

        $webInbox = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/notifications?channel=web')
            ->assertOk();

        $webTitles = collect($webInbox->json('data'))->pluck('title')->all();
        $this->assertContains('Nouvelle demande CONGE', $webTitles);
        $this->assertContains('Demande CONGE approuvée', $webTitles);

        // Décision perso → visible aussi pour l’agent terrain
        $notifications->notifyUser(
            $agentUser,
            'Demande PERMISSION approuvée',
            'Votre demande a été approuvée.',
            'approbation',
            'permission',
            channel: AppNotification::CHANNEL_BOTH,
        );

        $agentInbox = $this->actingAs($agentUser, 'sanctum')
            ->getJson('/api/notifications?channel=mobile')
            ->assertOk();

        $this->assertTrue(
            collect($agentInbox->json('data'))->contains(
                fn ($n) => ($n['title'] ?? '') === 'Demande PERMISSION approuvée'
            )
        );
    }

    public function test_decide_notifies_other_admins_traite_par(): void
    {
        $superRole = Role::query()->create([
            'name' => 'super_admin',
            'display_name' => 'Super',
            'description' => 'Test',
            'is_active' => true,
        ]);

        $rh = $this->makeAdmin();
        $super = User::query()->create([
            'role_id' => $superRole->id,
            'name' => 'Super Test',
            'email' => 'super.iso@sandiara.sn',
            'password' => 'Admin@2026!',
            'is_active' => true,
        ]);
        [$agentUser, $agent] = $this->makeAgentUser('agent.decide@sandiara.sn', 'EMP-DEC2');

        $demande = \App\Models\AbsenceRequest::query()->create([
            'agent_id' => $agent->id,
            'type_demande' => 'CONGE',
            'date_debut' => now()->toDateString(),
            'date_fin' => now()->addDays(2)->toDateString(),
            'motif' => 'Repos famille',
            'statut' => 'EN_ATTENTE',
        ]);

        $this->actingAs($rh, 'sanctum')
            ->postJson("/api/demandes/{$demande->id}/decide", [
                'decision' => 'APPROUVEE',
            ])
            ->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $agentUser->id,
            'type' => 'approbation',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $super->id,
            'type' => 'traitement',
            'channel' => 'web',
        ]);

        $peer = AppNotification::query()
            ->where('user_id', $super->id)
            ->where('type', 'traitement')
            ->first();

        $this->assertNotNull($peer);
        $this->assertStringContainsString('traitée par', (string) $peer->title);
        $this->assertStringContainsString($rh->name, (string) $peer->title);

        // Le décideur ne reçoit pas « traité par soi »
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $rh->id,
            'type' => 'traitement',
        ]);
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
