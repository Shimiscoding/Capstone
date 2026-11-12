<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_enforcer_cannot_sign_in_to_the_website(): void
    {
        $enforcer = User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'password' => 'password',
        ]);

        $this->from(route('login'))->post(route('login.store'), [
            'login' => $enforcer->email,
            'password' => 'password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors(['login' => 'Enforcer accounts can only sign in through the mobile app.']);

        $this->assertGuest('web');
    }

    public function test_existing_enforcer_web_session_cannot_access_website_pages(): void
    {
        $enforcer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $this->actingAs($enforcer)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_admin_and_supervisor_can_still_sign_in_to_the_website(): void
    {
        foreach ([User::ROLE_ADMIN, User::ROLE_SUPERVISOR] as $role) {
            $user = User::factory()->create(['role' => $role, 'password' => 'password']);

            $this->post(route('login.store'), [
                'login' => $user->email,
                'password' => 'password',
            ])->assertRedirect(route('dashboard'));

            $this->post(route('logout'))->assertRedirect(route('login'));
        }
    }

    public function test_suspended_admin_loses_website_access_and_can_still_log_out(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get(route('dashboard'))->assertOk();

        $admin->update(['account_status' => User::STATUS_SUSPENDED]);
        $this->get(route('dashboard'))->assertForbidden();
        $this->get(route('users.profile-photo', $admin))->assertForbidden();
        $this->actingAs($admin, 'web')->post(route('logout'))->assertRedirect(route('login'));
    }

    public function test_public_signup_requires_an_explicit_setting_after_the_first_admin_exists(): void
    {
        User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->get(route('login'))->assertOk()->assertDontSee('Sign up as admin');
        $this->get(route('register'))->assertNotFound();
        $this->post(route('register.store'), [])->assertNotFound();

        app(Settings::class)->putMany([Settings::ADMIN_SIGNUP => true]);
        $this->get(route('register'))->assertOk();
        $this->post(route('register.store'), [
            'firstName' => 'Approved',
            'lastName' => 'Admin',
            'phoneNumber' => '09170000011',
            'email' => 'approved@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', ['email' => 'approved@example.test', 'role' => User::ROLE_ADMIN]);
    }
}
