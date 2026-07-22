<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedAdmin(): User
    {
        $role = Role::query()->create([
            'name' => 'admin',
            'display_name' => 'Administrateur',
            'description' => 'Test',
            'is_active' => true,
        ]);

        return User::query()->create([
            'role_id' => $role->id,
            'name' => 'Admin Test',
            'email' => 'admin.test@sandiara.sn',
            'password' => 'Admin@2026!',
            'is_active' => true,
        ]);
    }

    public function test_login_rejects_bad_password(): void
    {
        $this->seedAdmin();

        $this->postJson('/api/auth/login', [
            'login' => 'admin.test@sandiara.sn',
            'password' => 'WrongPass1!',
        ])->assertStatus(422);
    }

    public function test_login_and_me(): void
    {
        $this->seedAdmin();

        $login = $this->postJson('/api/auth/login', [
            'login' => 'admin.test@sandiara.sn',
            'password' => 'Admin@2026!',
        ])->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'email']]);

        $token = $login->json('token');

        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'admin.test@sandiara.sn');
    }
}
