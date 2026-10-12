<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
            ->assertSeeText('Today')->assertSeeText('Week')->assertSeeText('Month')->assertSeeText('Year')->assertSeeText('All')
            ->assertDontSee('analyticsPeriod', false);

    }

    public function test_violation_rankings_include_every_recorded_group(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $labels = ['Driving without D/L', 'Unregistered Vehicle', 'Illegal parking',
            'Disregarding Traffic Sign', 'Obstruction', 'No helmet', 'Defective Head Light'];
        foreach ($labels as $label) {
            Violation::create(['first_name' => 'Test', 'last_name' => 'Motorist', 'plate_number' => 'TEST',
                'vehicle_type' => 'Motorcycle', 'violation_type' => $label, 'fine_amount' => 500]);
        }
        $response = $this->actingAs($admin)->get(route('dashboard.analytics', ['summary' => 'all']))
            ->assertOk()->assertViewHas('violationTrends', fn ($rows) => $rows->count() === 7)
            ->assertSeeText('All recorded violation types')->assertDontSeeText('Top 5 violation types');
        foreach ($labels as $label) {
            $response->assertSeeText($label);
        }
    }

    public function test_supervisor_cannot_open_analytics(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

        $this->actingAs($supervisor)->get(route('dashboard.analytics'))->assertForbidden();
    }

    public function test_report_totals_and_rankings_use_the_same_period_and_payment_status(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);
        foreach ([['Illegal parking', 'paid'], ['Illegal parking', 'unpaid'], ['Obstruction', 'unpaid']] as [$type, $status]) {
            Violation::create([
                'enforcer_id' => $officer->id, 'first_name' => 'Test', 'last_name' => 'Motorist',
                'plate_number' => 'TEST', 'vehicle_type' => 'Motorcycle',
                'violation_type' => $type, 'fine_amount' => 500, 'status' => $status,
            ]);
        }
        $old = Violation::create([
            'enforcer_id' => $admin->id, 'first_name' => 'Old', 'last_name' => 'Record',
            'plate_number' => 'OLD', 'vehicle_type' => 'Car',
            'violation_type' => 'Outside period', 'fine_amount' => 500, 'status' => 'paid',
        ]);
        $old->forceFill(['created_at' => now()->subDays(20)])->save();

        $this->actingAs($admin)->get(route('dashboard.analytics'))
            ->assertOk()->assertViewHas('periodViolationTotal', 3)
            ->assertViewHas('activeEnforcers', 1)->assertViewHas('pendingTickets', 2)
            ->assertViewHas('resolvedTickets', 1)
            ->assertViewHas('violationTrends', fn ($rows) => $rows->first()->violation_type === 'Illegal parking'
                && (int) $rows->first()->total === 2 && (int) $rows->first()->resolved === 1)
            ->assertDontSee('Outside period');
        $this->actingAs($admin)->get(route('dashboard.analytics', ['summary' => 'all']))
            ->assertOk()->assertViewHas('periodViolationTotal', 4)
            ->assertViewHas('activeEnforcers', 1)->assertViewHas('resolvedTickets', 2);
    }

    public function test_empty_reports_have_zero_totals_and_recovery_messages(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get(route('dashboard.analytics'))
            ->assertOk()->assertViewHas('periodViolationTotal', 0)
            ->assertViewHas('activeEnforcers', 0)->assertViewHas('pendingTickets', 0)
            ->assertViewHas('resolvedTickets', 0)
            ->assertSeeText('No vehicle types recorded')->assertSeeText('No tickets recorded');
    }

    public function test_range_buttons_filter_every_analytics_section(): void
    {
        $this->travelTo(Carbon::parse('2026-10-09 12:00:00'));
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);
        foreach (['2026-10-09', '2026-10-08', '2026-10-01', '2026-08-01', '2025-01-01'] as $date) {
            $ticket = Violation::create([
                'enforcer_id' => $officer->id, 'first_name' => 'Test', 'last_name' => 'Motorist',
                'plate_number' => 'TEST', 'vehicle_type' => 'Car',
                'violation_type' => 'Illegal parking', 'fine_amount' => 500, 'status' => 'paid',
            ]);
            $ticket->forceFill(['created_at' => $date.' 08:00:00'])->save();
        }
        foreach (['today' => 1, 'week' => 2, 'month' => 3, 'year' => 4, 'all' => 5, 'invalid' => 1] as $range => $count) {
            $this->actingAs($admin)->get(route('dashboard.analytics', ['summary' => $range, 'period' => 'daily']))
                ->assertOk()->assertViewHas('periodViolationTotal', $count)
                ->assertViewHas('dailyViolations', fn ($rows) => $rows->sum('value') === $count)
                ->assertViewHas('vehicleTrends', fn ($rows) => (int) $rows->first()->total === $count)
                ->assertViewHas('violationTrends', fn ($rows) => (int) $rows->first()->total === $count)
                ->assertViewHas('summary', fn ($summary) => $summary['total'] === $count
                    && $summary['resolved'] === $count && $summary['pending'] === 0 && $summary['enforcers'] === 1);
        }
        $this->travelBack();
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
