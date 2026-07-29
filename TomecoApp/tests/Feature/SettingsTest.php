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
            'date_format' => 'Y-m-d', 'violation_prefix' => 'VIO-', 'payment_prefix' => 'PAY-', 'impound_prefix' => 'IMP-',
        ])->assertRedirect(route('dashboard.settings', ['tab' => 'general']));

        $this->assertEquals('TOMECO Legazpi', Setting::where('key', 'general.organization_name')->value('value'));
    }

    public function test_non_admin_cannot_access_settings(): void
    {
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $this->actingAs($officer)->get(route('dashboard.settings'))->assertForbidden();
    }

    public function test_admin_can_download_a_json_backup(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get(route('dashboard.settings.backup'))
            ->assertOk()->assertHeader('content-type', 'application/json')->assertSee('impounded_vehicles');
    }
}
