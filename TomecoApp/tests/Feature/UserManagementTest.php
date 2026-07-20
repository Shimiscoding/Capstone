<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_user_with_a_role(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)->post(route('dashboard.users.store'), [
            'fullName' => 'Driver One',
            'badgeNumber' => 'DRV-001',
            'phoneNumber' => '09171234567',
            'email' => 'driver@example.com',
            'role' => User::ROLE_DRIVER,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('dashboard.users'));
        $this->assertDatabaseHas('users', [
            'email' => 'driver@example.com',
            'role' => User::ROLE_DRIVER,
        ]);
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $this->actingAs($officer)->get(route('dashboard.users'))->assertForbidden();
        $this->actingAs($officer)->get(route('dashboard.users.create'))->assertForbidden();
    }

    public function test_an_unknown_role_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->post(route('dashboard.users.store'), [
            'fullName' => 'Unknown Role',
            'badgeNumber' => 'UNK-001',
            'phoneNumber' => '09170000000',
            'email' => 'unknown@example.com',
            'role' => 'superuser',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'unknown@example.com']);
    }
}
