<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Violation;
use App\Services\ViolationCatalog;
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
            ->assertSee('Tickets issued')
            ->assertSee('Unpaid ticket aging')
            ->assertSee('Recorded locations')
            ->assertSee('Outstanding fines')
            ->assertSee('Line chart')
            ->assertSee('Bar chart')
            ->assertDontSee('Doughnut chart')
            ->assertSeeText('Today')->assertSeeText('This week')->assertSeeText('This month')->assertSeeText('This year')->assertSeeText('All time')
            ->assertSee('id="analyticsReportPeriod" name="summary"', false)->assertSeeText('Custom dates')
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

    public function test_custom_dates_use_manila_boundaries_and_filters_across_the_report(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 04:00:00', 'UTC'));
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);
        foreach ([
            ['2026-10-19 15:59:59', 'Car', 'unpaid', 'Outside'],
            ['2026-10-19 16:00:00', 'Car', 'unpaid', 'Justice Avenue'],
            ['2026-10-20 15:59:59', 'Car', 'unpaid', 'Justice Avenue'],
            ['2026-10-20 16:00:00', 'Car', 'unpaid', 'Outside'],
            ['2026-10-20 04:00:00', 'Car', 'paid', 'Paid location'],
            ['2026-10-20 04:00:00', 'Motorcycle', 'unpaid', 'Motorcycle location'],
        ] as [$date, $vehicle, $status, $location]) {
            $this->ticketAt($date, ['enforcer_id' => $officer->id, 'vehicle_type' => $vehicle, 'status' => $status, 'location' => $location]);
        }
        $this->actingAs($admin)->get(route('dashboard.analytics', [
            'summary' => 'custom', 'date_from' => '2026-10-20', 'date_to' => '2026-10-20', 'vehicle' => 'Car', 'status' => 'unpaid',
        ]))->assertOk()->assertViewHas('periodViolationTotal', 2)
            ->assertViewHas('outstandingFines', 1000.0)->assertViewHas('assessedFines', 1000.0)
            ->assertViewHas('dailyViolations', fn ($rows) => $rows->sum('value') === 2 && $rows->sum('unpaid') === 2)
            ->assertViewHas('vehicleTrends', fn ($rows) => $rows->count() === 1 && (int) $rows->first()->total === 2)
            ->assertViewHas('locationTrends', fn ($rows) => $rows->count() === 1 && $rows->first()->label === 'Justice Avenue' && $rows->first()->total === 2)
            ->assertViewHas('enforcerTrends', fn ($rows) => (int) $rows->first()->total === 2)
            ->assertViewHas('violationTrends', fn ($rows) => (int) $rows->first()->total === 2)
            ->assertViewHas('aging', fn ($rows) => $rows->sum('total') === 2)
            ->assertSeeText('Justice Avenue')->assertDontSeeText('Paid location');
        $this->travelBack();
    }

    public function test_comparisons_use_equal_non_overlapping_elapsed_periods(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 04:00:00', 'UTC'));
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        foreach (['2026-10-23 16:00:00', '2026-10-24 03:00:00', '2026-10-23 15:59:59', '2026-10-22 00:00:00'] as $date) {
            $this->ticketAt($date);
        }
        $this->actingAs($admin)->get(route('dashboard.analytics'))->assertOk()
            ->assertViewHas('periodViolationTotal', 2)->assertViewHas('previousTotal', 1)->assertViewHas('ticketChange', 100.0);
        $this->actingAs($admin)->get(route('dashboard.analytics', ['summary' => 'all']))->assertOk()
            ->assertViewHas('previousTotal', null)->assertViewHas('ticketChange', null);
        $this->travelBack();
    }

    public function test_unpaid_aging_uses_calendar_days_and_excludes_paid_tickets(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 04:00:00', 'UTC'));
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        foreach ([0, 7, 8, 30, 31, 60, 61] as $age) {
            $date = now('Asia/Manila')->subDays($age)->startOfDay()->setTimezone('UTC')->format('Y-m-d H:i:s');
            $this->ticketAt($date, ['fine_amount' => 125.50]);
        }
        $this->ticketAt('2026-08-01 03:00:00', ['status' => 'paid', 'fine_amount' => 900]);
        $this->actingAs($admin)->get(route('dashboard.analytics', ['summary' => 'all']))->assertOk()
            ->assertViewHas('outstandingFines', 878.5)
            ->assertViewHas('aging', fn ($rows) => $rows->pluck('total')->all() === [2, 2, 2, 1]
                && $rows->sum('amount') === 878.5);
        $this->travelBack();
    }

    public function test_invalid_custom_dates_show_inline_errors_and_recover_to_today(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        foreach ([['2026-02-30', '2026-03-01'], ['2020-02-10', '2020-02-01'], ['', '']] as [$from, $to]) {
            $this->actingAs($admin)->get(route('dashboard.analytics', ['summary' => 'custom', 'date_from' => $from, 'date_to' => $to]))
                ->assertOk()->assertViewHas('summaryRange', 'today')
                ->assertViewHas('filterErrors', fn ($errors) => $errors->isNotEmpty())
                ->assertSee('aria-invalid="true"', false)->assertSeeText('Some filters could not be applied');
        }
        // Quick periods ignore unfinished or invalid date drafts, including without JavaScript.
        $this->actingAs($admin)->get(route('dashboard.analytics', ['summary' => 'month', 'date_from' => 'bad', 'date_to' => 'bad']))
            ->assertOk()->assertViewHas('summaryRange', 'month')->assertViewHas('filterErrors', fn ($errors) => $errors->isEmpty());
    }

    public function test_custom_today_is_valid_after_midnight_in_manila(): void
    {
        $this->travelTo(Carbon::parse('2026-10-23 17:00:00', 'UTC'));
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get(route('dashboard.analytics', ['summary' => 'custom', 'date_from' => '2026-10-24', 'date_to' => '2026-10-24']))
            ->assertOk()->assertViewHas('summaryRange', 'custom')->assertViewHas('filterErrors', fn ($errors) => $errors->isEmpty());
        $this->travelBack();
    }

    public function test_rankings_paginate_without_losing_report_filters(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        foreach (ViolationCatalog::COMMON_GROUPS as $label) {
            $this->ticketAt(now()->format('Y-m-d H:i:s'), ['violation_type' => $label]);
        }
        $this->actingAs($admin)->get(route('dashboard.analytics', ['summary' => 'all', 'vehicle' => 'Car', 'page' => 2]))
            ->assertOk()->assertViewHas('violationPage', fn ($page) => $page->total() === 12 && $page->count() === 2 && str_contains($page->previousPageUrl(), 'vehicle=Car'))
            ->assertSeeText('Unregistered Vehicle')->assertDontSeeText('Illegal parking');
        $this->actingAs($admin)->get(route('dashboard.analytics', ['summary' => 'all', 'page' => 999]))
            ->assertOk()->assertViewHas('violationPage', fn ($page) => $page->currentPage() === 2);
    }

    public function test_locations_group_decrypted_text_and_escape_untrusted_content(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        foreach (['Justice Avenue', ' Justice  Avenue ', '<script>bad()</script>', null] as $location) {
            $this->ticketAt(now()->format('Y-m-d H:i:s'), ['location' => $location]);
        }
        $this->actingAs($admin)->get(route('dashboard.analytics', ['summary' => 'all']))->assertOk()
            ->assertViewHas('locationTrends', fn ($rows) => $rows->first()->label === 'Justice Avenue' && $rows->first()->total === 2)
            ->assertSeeText('Location not recorded')->assertDontSee('<script>bad()</script>', false);
    }

    private function ticketAt(string $createdAt, array $attributes = []): Violation
    {
        $ticket = Violation::create(array_merge([
            'first_name' => 'Fictional', 'last_name' => 'Motorist', 'plate_number' => 'TEST',
            'vehicle_type' => 'Car', 'violation_type' => 'Illegal parking', 'fine_amount' => 500, 'status' => 'unpaid',
        ], $attributes));
        $ticket->forceFill(['created_at' => $createdAt])->save();

        return $ticket;
    }
}
