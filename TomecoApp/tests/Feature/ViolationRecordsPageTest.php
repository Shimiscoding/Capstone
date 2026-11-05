<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ViolationRecordsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_can_view_violation_records_page(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $firstViolation = Violation::create([
            'first_name' => 'Juan', 'middle_name' => 'Dela', 'last_name' => 'Cruz',
            'license_number' => 'N01-23-456789',
            'license_type' => 'professional', 'plate_number' => 'ABC 1234',
            'or_number' => 'OR-1234567', 'cr_number' => 'CR-7654321',
            'violation_type' => 'Illegal parking', 'fine_amount' => 500,
            'status' => 'unpaid',
        ]);
        $secondViolation = Violation::create([
            'first_name' => 'Juan', 'middle_name' => 'Dela', 'last_name' => 'Cruz',
            'license_number' => 'N0123456789',
            'license_type' => 'professional', 'plate_number' => 'XYZ 9876',
            'or_number' => 'OR-7654321', 'cr_number' => 'CR-1234567',
            'violation_type' => 'Obstruction', 'fine_amount' => 750,
            'status' => 'paid',
        ]);

        $this->actingAs($admin)->get(route('dashboard.violation-records'))
            ->assertOk()
            ->assertSee('Violation Records')
            ->assertSee('ABC 1234')
            ->assertSee('Issued tickets')
            ->assertSee('View complete ticket')
            ->assertSee(route('dashboard.violation-records.motorist', $firstViolation))
            ->assertSee(route('dashboard.violation-records.show', 1))
            ->assertSee('Illegal parking');

        $this->actingAs($admin)->get(route('dashboard.violation-records.motorist', $firstViolation))
            ->assertOk()
            ->assertSee('Driver violation history')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('2 violations')
            ->assertSee('Illegal parking')
            ->assertSee('Obstruction')
            ->assertSee('XYZ 9876')
            ->assertSee(route('dashboard.violation-records.show', $secondViolation));

        $this->actingAs($admin)->get(route('dashboard.violation-records.show', 1))
            ->assertOk()
            ->assertSee('Violation ticket #0000001')
            ->assertSee('Driver and permit')
            ->assertSee('Vehicle documents')
            ->assertSee('OR-1234567')
            ->assertSee('CR-7654321');
    }

    public function test_search_finds_encrypted_driver_and_plate_and_padded_ticket_numbers(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $ticket = $this->ticket(['first_name' => 'Juan', 'middle_name' => 'Dela', 'last_name' => 'Cruz', 'plate_number' => 'ABC 1234']);
        $this->ticket(['first_name' => 'Other', 'last_name' => 'Driver', 'plate_number' => 'XYZ 9876', 'violation_type' => 'Obstruction']);
        foreach (['juan dela', 'abc1234', 'ABC 1234', '#'.str_pad((string) $ticket->id, 7, '0', STR_PAD_LEFT), 'Illegal parking'] as $search) {
            $this->actingAs($admin)->get(route('dashboard.violation-records', ['search' => $search, 'period' => 'all']))
                ->assertOk()->assertViewHas('violations', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $ticket->id)
                ->assertViewHas('recordSummary', fn ($summary) => $summary['total'] === 1 && $summary['fines'] === 500.0);
        }
        $this->actingAs($admin)->get(route('dashboard.violation-records', ['search' => 'missing ticket']))
            ->assertOk()->assertViewHas('recordSummary', fn ($summary) => $summary['total'] === 0)
            ->assertSeeText('No violation records found');
    }

    public function test_filters_and_summary_use_the_same_manila_issue_period(): void
    {
        $this->travelTo(Carbon::parse('2026-10-24 04:00:00', 'UTC'));
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        foreach ([['2026-10-23 15:59:59', 'unpaid', 900], ['2026-10-23 16:00:00', 'unpaid', 500],
            ['2026-10-24 03:00:00', 'paid', 1000], ['2026-10-24 05:00:00', 'unpaid', 1200]] as [$date, $status, $fine]) {
            $this->ticket(['status' => $status, 'fine_amount' => $fine])->forceFill(['created_at' => $date])->save();
        }
        $this->actingAs($admin)->get(route('dashboard.violation-records', ['period' => 'today']))
            ->assertOk()->assertViewHas('recordSummary', ['total' => 2, 'paid' => 1, 'unpaid' => 1, 'fines' => 1500.0]);
        $this->actingAs($admin)->get(route('dashboard.violation-records', ['period' => 'today', 'status' => 'unpaid']))
            ->assertOk()->assertViewHas('recordSummary', ['total' => 1, 'paid' => 0, 'unpaid' => 1, 'fines' => 500.0]);
        $this->travelBack();
    }

    public function test_sort_and_pagination_preserve_filters_and_clamp_invalid_pages(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        foreach (range(1, 17) as $index) {
            $this->ticket(['fine_amount' => $index * 100]);
        }
        $this->actingAs($admin)->get(route('dashboard.violation-records', ['period' => 'all', 'status' => 'unpaid', 'sort' => 'fine']))
            ->assertOk()->assertViewHas('violations', fn ($rows) => $rows->count() === 15 && (float) $rows->first()->fine_amount === 1700.0
                && str_contains($rows->nextPageUrl(), 'status=unpaid') && str_contains($rows->nextPageUrl(), 'sort=fine'));
        $this->actingAs($admin)->get(route('dashboard.violation-records', ['period' => 'all', 'page' => 999]))
            ->assertOk()->assertViewHas('violations', fn ($rows) => $rows->currentPage() === 2 && $rows->count() === 2);
    }

    public function test_original_columns_and_last_view_ticket_link_are_preserved(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $ticket = $this->ticket();
        $response = $this->actingAs($admin)->get(route('dashboard.violation-records'))->assertOk()
            ->assertSee(route('dashboard.violation-records.show', $ticket))
            ->assertSee(route('dashboard.violation-records.motorist', $ticket))
            ->assertDontSee('ticketDialog')->assertDontSee('View ticket <svg', false);
        preg_match_all('/<th\b[^>]*>(.*?)<\/th>/s', $response->getContent(), $matches);
        $this->assertSame(['Ticket', 'Driver', 'Vehicle', 'Violation', 'Enforcer', 'Issued', 'Fine / status', 'View Ticket'], array_map('strip_tags', $matches[1]));
        $response->assertSee('aria-label="View Ticket '.$ticket->id.'"', false);
    }

    public function test_custom_dates_validate_and_preserve_one_sided_existing_filters(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->ticket()->forceFill(['created_at' => '2026-10-01 16:00:00'])->save();
        foreach ([['date_from' => '2026-10-03'], ['date_to' => '2026-10-01']] as $filters) {
            $this->actingAs($admin)->get(route('dashboard.violation-records', $filters))
                ->assertOk()->assertViewHas('violations', fn ($rows) => $rows->total() === 0);
        }
        $this->actingAs($admin)->get(route('dashboard.violation-records', ['period' => 'custom', 'date_from' => '2026-10-02', 'date_to' => '2026-10-02']))
            ->assertOk()->assertViewHas('violations', fn ($rows) => $rows->total() === 1);
        $this->actingAs($admin)->get(route('dashboard.violation-records', ['period' => 'custom', 'date_from' => '2026-10-03', 'date_to' => '2026-10-01']))
            ->assertOk()->assertViewHas('filterErrors', fn ($errors) => $errors->has('date_to'))
            ->assertSee('aria-invalid="true"', false)->assertSeeText('Some filters could not be applied');
    }

    private function ticket(array $attributes = []): Violation
    {
        return Violation::create(array_merge(['first_name' => 'Sample', 'last_name' => 'Driver',
            'plate_number' => 'DEMO 001', 'violation_type' => 'Illegal parking', 'fine_amount' => 500,
            'vehicle_type' => 'Motorcycle', 'status' => 'unpaid'], $attributes));
    }
}
