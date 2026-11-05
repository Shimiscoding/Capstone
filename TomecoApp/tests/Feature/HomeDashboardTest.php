<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HomeDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_totals_and_activity_use_manila_calendar_days(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 23:59:59', 'Asia/Manila'));
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->ticket('2026-10-23 15:59:59', 500, 'unpaid');
        $this->ticket('2026-10-23 16:00:00', 500, 'paid');
        $this->ticket('2026-10-24 15:59:59', 750, 'unpaid');
        $this->ticket('2026-10-24 16:00:00', 100, 'unpaid');
        $this->ticket('2026-10-17 15:59:59', 500, 'paid');
        User::factory()->create(['created_at' => '2026-10-23 15:59:59']);
        User::factory()->create(['created_at' => '2026-10-23 16:00:00']);

        $response = $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()->assertViewHas('todayTickets', 2)
            ->assertViewHas('todayAssessedFines', 1250)
            ->assertViewHas('outstandingTickets', 3)
            ->assertViewHas('totalUsers', 3)
            ->assertSee('Saturday, October 24, 2026')
            ->assertSee('Issued today')->assertSee('Assessed fines today')
            ->assertSee(route('dashboard.violation-records', ['period' => 'today']))
            ->assertSee(route('dashboard.violation-records', ['period' => 'all', 'status' => 'unpaid']));
        $days = $response->viewData('activityDays');
        $this->assertCount(7, $days);
        $this->assertSame(3, $days->sum('violations'));
        $this->assertSame(2, $days->last()['violations']);
        $this->assertSame(2, $days->last()['users']);
        $this->assertSame(1, $days->get(5)['violations']);
        $this->assertSame('Sat 24', $days->last()['label']);

        $this->get(route('dashboard.violation-records', ['period' => 'today']))
            ->assertOk()->assertViewHas('recordSummary', fn ($summary) => $summary['total'] === 2);
    }

    public function test_recent_tickets_are_limited_stably_sorted_and_link_to_existing_ticket_pages(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $tickets = collect(range(1, 6))->map(fn () => $this->ticket('2026-10-20 10:00:00', 500, 'paid'));
        $latest = $tickets->last();
        $latest->update(['first_name' => '<script>unsafe()</script>', 'plate_number' => 'DEMO 123', 'location' => 'Real Street']);

        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertSee('DEMO 123')->assertSee('Real Street')->assertSee('Illegal parking')
            ->assertSee('Oct 20, 2026')->assertSee('06:00 PM')
            ->assertSee(route('dashboard.violation-records.show', $latest))
            ->assertSee('View Ticket')->assertSee('Paid')
            ->assertDontSee('<script>unsafe()</script>', false)
            ->assertDontSee('chart.umd.min.js', false);
        $this->assertSame($tickets->reverse()->take(5)->pluck('id')->values()->all(), $response->viewData('recentViolations')->pluck('id')->all());
    }

    public function test_empty_home_has_zero_totals_and_a_chart_table_without_javascript(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk()
            ->assertViewHas('todayTickets', 0)->assertViewHas('outstandingTickets', 0)
            ->assertSee('No tickets have been issued yet.')
            ->assertSee('class="chart-data" open', false)
            ->assertSee('User registrations')->assertSee('home-dashboard.js');
        $this->assertSame(0, $response->viewData('activityDays')->sum('violations'));
    }

    public function test_supervisors_and_cashiers_keep_their_existing_home_workflows(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $this->actingAs($supervisor)->get(route('dashboard'))->assertOk()->assertViewIs('supervisor.index');
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $this->actingAs($cashier)->get(route('dashboard'))->assertRedirect(route('cashier.dashboard'));
    }

    public function test_supervisor_overview_only_counts_their_team_and_paginates_the_directory(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        User::factory()->count(9)->create([
            'role' => User::ROLE_OFFICER, 'supervisor_id' => $supervisor->id,
            'firstName' => 'Assigned', 'area' => 'Downtown',
        ]);
        User::factory()->create([
            'role' => User::ROLE_OFFICER, 'supervisor_id' => $supervisor->id,
            'firstName' => 'Waiting', 'area' => '   ', 'account_status' => User::STATUS_SUSPENDED,
        ]);
        User::factory()->create(['role' => User::ROLE_OFFICER, 'firstName' => 'OtherTeam', 'area' => 'Outside area']);

        $response = $this->actingAs($supervisor)->get(route('dashboard'))
            ->assertOk()->assertViewHas('enforcerCount', 10)->assertViewHas('areaCount', 1)
            ->assertViewHas('unassignedAreaCount', 1)
            ->assertSee('Team coverage')->assertSee('Downtown')
            ->assertDontSee('OtherTeam')->assertDontSee('Outside area')
            ->assertDontSeeText('Time In')->assertDontSeeText('Time Out');
        $this->assertCount(8, $response->viewData('enforcers'));
        $this->assertSame(9, $response->viewData('coverageAreas')->get('Downtown'));

        $lastPage = $this->get(route('dashboard', ['page' => 999]))->assertOk()
            ->assertSee('Waiting')->assertSee('Suspended')->assertSee('Not assigned');
        $this->assertSame(2, $lastPage->viewData('enforcers')->currentPage());
        $this->assertCount(2, $lastPage->viewData('enforcers'));
        $this->assertSame(10, $lastPage->viewData('enforcerCount'));
    }

    public function test_empty_supervisor_overview_has_clear_next_steps(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $this->actingAs($supervisor)->get(route('dashboard'))
            ->assertOk()->assertViewHas('enforcerCount', 0)->assertViewHas('areaCount', 0)
            ->assertViewHas('unassignedAreaCount', 0)
            ->assertSee('No areas assigned yet')->assertSee('No enforcers assigned yet')
            ->assertSee('Manage my team')->assertSee('Page 1 of 1')
            ->assertSee(route('location-monitoring.index'));
    }

    private function ticket(string $createdAt, int $fine, string $status): Violation
    {
        $ticket = Violation::create([
            'first_name' => 'Sample', 'last_name' => 'Driver', 'plate_number' => 'DEMO',
            'vehicle_type' => 'Motorcycle', 'violation_type' => 'Illegal parking',
            'fine_amount' => $fine, 'status' => $status,
        ]);
        $ticket->forceFill(['created_at' => $createdAt])->save();

        return $ticket;
    }
}
