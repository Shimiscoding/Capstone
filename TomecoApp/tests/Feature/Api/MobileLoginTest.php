<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_log_in_to_the_mobile_api(): void
    {
        $driver = User::factory()->create([
            'role' => User::ROLE_DRIVER,
            'driverLicense' => 'N01-23-456789',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'login' => $driver->email,
            'password' => 'password123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.role', User::ROLE_DRIVER)
            ->assertJsonPath('user.driverLicense', 'N01-23-456789')
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
