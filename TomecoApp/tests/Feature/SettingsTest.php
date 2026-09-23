<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_update_general_settings(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get(route('dashboard.settings'))->assertOk()->assertSee('System Settings');
        $this->actingAs($admin)->put(route('dashboard.settings.update'), [
            'section' => 'general', 'organization_name' => 'TOMECO Legazpi', 'address' => 'City Hall',
            'phone' => '09123456789', 'email' => 'admin@example.com', 'timezone' => 'Asia/Manila',
            'date_format' => 'Y-m-d', 'violation_prefix' => 'VIO-',
        ])->assertRedirect(route('dashboard.settings', ['tab' => 'general']));

        $this->assertEquals('TOMECO Legazpi', Setting::where('key', 'general.organization_name')->value('value'));
    }

    public function test_non_admin_cannot_access_settings(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $this->actingAs($officer)->get(route('dashboard.settings'))->assertForbidden();
    }

    public function test_roles_and_permissions_only_show_administrator_and_supervisor(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)
            ->get(route('dashboard.settings', ['tab' => 'permissions']))
            ->assertOk()
            ->assertSeeText('Administrator')
            ->assertSeeText('Supervisor')
            ->assertDontSeeText('Officer');

        $this->actingAs($admin)->put(route('dashboard.settings.update'), [
            'section' => 'permissions',
            'admin' => ['manage_settings', 'manage_users'],
            'supervisor' => ['record_violations', 'export'],
        ])->assertRedirect(route('dashboard.settings', ['tab' => 'permissions']));

        $this->assertDatabaseHas('settings', ['key' => 'permissions.supervisor']);
        $this->assertDatabaseMissing('settings', ['key' => 'permissions.officer']);
    }

    public function test_admin_can_disable_public_admin_signup(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->put(route('dashboard.settings.update'), [
            'section' => 'security',
            'password_min_length' => 8,
            'session_timeout' => 120,
            'login_attempts' => 5,
            'api_token_days' => 30,
            'audit_retention_days' => 365,
        ])->assertRedirect(route('dashboard.settings', ['tab' => 'security']));

        $this->post(route('logout'));
        $this->get(route('login'))->assertOk()->assertDontSee('Sign up as admin');
        $this->get(route('register'))->assertNotFound();
        $this->post(route('register.store'), [])->assertNotFound();
    }
}
