<?php

namespace Tests\Feature;

use App\Models\User;
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
}
