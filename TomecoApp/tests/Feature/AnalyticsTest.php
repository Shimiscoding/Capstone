<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_activity_is_available_on_analytics(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->get(route('dashboard.analytics'))
            ->assertOk()
            ->assertSee('Violators')
            ->assertSee('Users')
            ->assertSee('Line chart')
            ->assertSee('Bar chart')
            ->assertSee('Doughnut chart')
            ->assertSee('Daily')
            ->assertSee('Monthly')
            ->assertSee('Yearly');

        $this->actingAs($admin)->get(route('dashboard.analytics', ['period' => 'monthly']))
            ->assertOk()
            ->assertSee('last 12 months');

        $this->actingAs($admin)->get(route('dashboard.analytics', ['period' => 'yearly']))
            ->assertOk()
            ->assertSee('last 5 years');

    }

    public function test_supervisor_cannot_open_analytics(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

        $this->actingAs($supervisor)->get(route('dashboard.analytics'))->assertForbidden();
    }

    public function test_analytics_ranks_vehicle_types_by_number_of_violations(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        foreach (['Motorcycle', 'Motorcycle', 'Sedan'] as $index => $vehicleType) {
            Violation::create([
                'first_name' => 'Motorist',
                'last_name' => (string) $index,
                'plate_number' => 'TEST-'.$index,
                'vehicle_type' => $vehicleType,
                'violation_type' => 'Traffic violation',
                'fine_amount' => 500,
            ]);
        }

        $this->actingAs($admin)->get(route('dashboard.analytics'))
            ->assertOk()
            ->assertSeeText('Most Common Vehicle Types')
            ->assertSee('Motorcycle')
            ->assertSee('Sedan');
    }
}
