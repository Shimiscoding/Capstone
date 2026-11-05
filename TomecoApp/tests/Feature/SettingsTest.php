<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Settings;
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
            'phone' => '09123456789', 'email' => 'admin@example.com', 'timezone' => 'UTC',
            'date_format' => 'Y-m-d', 'violation_prefix' => 'VIO-',
        ])->assertRedirect(route('dashboard.settings', ['tab' => 'general']));

        $this->assertEquals('TOMECO Legazpi', Setting::where('key', 'general.organization_name')->value('value'));

        $this->get(route('dashboard.settings'))
            ->assertOk()
            ->assertSee('value="UTC" selected', false)
            ->assertSee('value="Y-m-d" selected', false);
    }

    public function test_non_admin_cannot_access_settings(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $this->actingAs($officer)->get(route('dashboard.settings'))->assertForbidden();
        $this->put(route('dashboard.settings.update'), ['section' => 'general'])->assertForbidden();
    }

    public function test_removed_settings_sections_are_absent_and_old_links_show_general(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        foreach (['general', 'notifications', 'security', 'violations', 'permissions', 'data', 'unknown'] as $tab) {
            $response = $this->actingAs($admin)->get(route('dashboard.settings', ['tab' => $tab]))
                ->assertOk()
                ->assertSeeText('General')
                ->assertSeeText('Notifications')
                ->assertSeeText('Security')
                ->assertDontSeeText('Violations & Fines')
                ->assertDontSeeText('Roles & Permissions')
                ->assertDontSeeText('Import & Export')
                ->assertDontSee('name="catalog_text"', false)
                ->assertDontSee('name="supervisor[]"', false)
                ->assertDontSee('name="default_format"', false);

            if (in_array($tab, ['violations', 'permissions', 'data', 'unknown'], true)) {
                $response->assertSee('name="section" value="general"', false);
            }
        }
    }

    public function test_removed_settings_sections_cannot_be_updated(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $settings = app(Settings::class);
        $original = $settings->all();

        foreach ([
            ['section' => 'violations', 'late_fee' => 999, 'catalog_text' => 'Changed | 999'],
            ['section' => 'permissions', 'admin' => [], 'supervisor' => ['manage_settings']],
            ['section' => 'data', 'max_upload_mb' => 20, 'duplicate_behavior' => 'skip', 'default_format' => 'csv', 'history_days' => 1],
        ] as $payload) {
            $this->actingAs($admin)->putJson(route('dashboard.settings.update'), $payload)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('section');
        }

        $this->assertSame($original, $settings->all());
        $this->assertDatabaseCount('settings', 0);
    }

    public function test_admin_can_update_notification_settings(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->put(route('dashboard.settings.update'), [
            'section' => 'notifications', 'new_violation' => '1', 'recipient_email' => 'alerts@example.com',
        ])->assertRedirect(route('dashboard.settings', ['tab' => 'notifications']));

        $settings = app(Settings::class);
        $this->assertTrue($settings->get('notifications.new_violation'));
        $this->assertFalse($settings->get('notifications.overdue_reminder'));
        $this->assertSame('alerts@example.com', $settings->get('notifications.recipient_email'));
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
