<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin()
    {
        $role = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Administrateur',
            'description' => 'Test',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'role_id' => $role->id,
            'name' => 'Admin Stats',
            'email' => 'stats@sandiara.sn',
            'password' => 'Admin@2026!',
            'is_active' => true,
        ]);

        return $this->actingAs($user, 'sanctum');
    }

    public function test_dashboard_summary_requires_auth(): void
    {
        $this->getJson('/api/dashboard/summary')->assertUnauthorized();
    }

    public function test_dashboard_summary_ok_for_admin(): void
    {
        $this->actingAsAdmin()
            ->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonStructure([
                'date',
                'quick' => [
                    'pointages_enregistres',
                    'agents_presents',
                    'agents_absents',
                    'total_agents',
                    'retards_detectes',
                    'demandes_en_attente',
                ],
                'kpis' => ['presents', 'taux_presence', 'retards', 'absences'],
            ]);
    }

    public function test_device_token_register(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/device-tokens', [
                'token' => 'fcm-test-token-123',
                'platform' => 'android',
                'device_name' => 'Pixel Test',
            ])
            ->assertCreated()
            ->assertJsonPath('device_token.platform', 'android');
    }
}
