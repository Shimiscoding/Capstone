<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_officer_can_log_in_to_the_mobile_api(): void
    {
        $officer = User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => $officer->email,
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', User::ROLE_OFFICER)
            ->assertJsonStructure(['token']);
    }

    public function test_admin_cannot_log_in_to_the_mobile_api(): void
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'password' => 'password123',
        ]);

        $this->postJson('/api/auth/login', [
            'login' => $admin->email,
            'password' => 'password123',
        ])->assertForbidden();
    }
}
