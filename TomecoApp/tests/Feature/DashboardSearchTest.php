<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_dashboard_pages(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($admin)->getJson(route('dashboard.search', ['q' => 'area assignments']))
            ->assertOk()->assertJsonFragment([
                'kind' => 'Pages', 'label' => 'Area Assignments',
                'url' => route('area-assignments.index'),
            ])->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_admin_can_find_staff_by_full_name_username_and_email(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $enforcer = User::factory()->create([
            'role' => User::ROLE_OFFICER, 'firstName' => 'Andrea', 'middleName' => null,
            'lastName' => 'Reyes', 'nameExtension' => null, 'username' => 'andrea.enforcer',
            'email' => 'andrea@example.test',
        ]);

        foreach (['Andrea Reyes', 'andrea.enforcer', 'andrea@example.test'] as $query) {
            $this->actingAs($admin)->getJson(route('dashboard.search', ['q' => $query]))
                ->assertOk()->assertJsonFragment([
                    'kind' => 'Staff', 'label' => 'Andrea Reyes',
                    'url' => route('dashboard.users.enforcers.show', $enforcer),
                ]);
        }
    }

    public function test_admin_search_finds_encrypted_motorist_names_plates_and_old_tickets(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $ticket = Violation::create([
            'first_name' => 'Mara', 'last_name' => 'Santos', 'plate_number' => 'ABC-2468',
            'vehicle_type' => 'Motorcycle', 'violation_type' => 'Illegal parking', 'fine_amount' => 500,
        ]);
        $ticket->forceFill(['created_at' => now()->subYear()])->save();

        foreach (['Mara Santos', 'ABC2468', '#'.$ticket->id] as $query) {
            $this->actingAs($admin)->getJson(route('dashboard.search', ['q' => $query]))
                ->assertOk()->assertJsonFragment([
                    'kind' => 'Violation records', 'label' => 'Ticket #'.$ticket->id,
                    'description' => 'Mara Santos · ABC-2468',
                    'url' => route('dashboard.violation-records.show', $ticket),
                ]);
        }
    }

    public function test_supervisor_search_only_returns_their_team_and_permitted_pages(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $own = User::factory()->create(['role' => User::ROLE_OFFICER, 'supervisor_id' => $supervisor->id, 'firstName' => 'Findme', 'lastName' => 'Own']);
        $other = User::factory()->create(['role' => User::ROLE_OFFICER, 'firstName' => 'Findme', 'lastName' => 'Other']);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'firstName' => 'Findme', 'lastName' => 'Admin']);
        Violation::create(['first_name' => 'Findme', 'last_name' => 'Motorist', 'plate_number' => 'FIND123', 'vehicle_type' => 'Motorcycle', 'violation_type' => 'Illegal parking', 'fine_amount' => 500]);

        $this->actingAs($supervisor)->getJson(route('dashboard.search', ['q' => 'Findme']))
            ->assertOk()->assertJsonFragment(['label' => $own->fullName, 'url' => route('supervisor.enforcers.attendance', $own)])
            ->assertJsonMissing(['label' => $other->fullName])
            ->assertJsonMissing(['label' => $admin->fullName])
            ->assertJsonMissing(['kind' => 'Violation records']);
        $this->getJson(route('dashboard.search', ['q' => 'settings']))->assertOk()->assertJsonMissing(['label' => 'Settings']);
        $this->getJson(route('dashboard.search', ['q' => 'team']))->assertOk()
            ->assertJsonFragment(['label' => 'My Team', 'url' => route('supervisor.team')]);
    }

    public function test_search_requires_an_authorized_web_account(): void
    {
        $this->getJson(route('dashboard.search', ['q' => 'staff']))->assertUnauthorized();
        foreach ([User::ROLE_OFFICER, User::ROLE_CASHIER] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson(route('dashboard.search', ['q' => 'staff']))->assertForbidden();
        }
    }

    public function test_search_handles_blank_no_results_and_invalid_input(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        foreach (['', '   ', 'zzznomatch'] as $query) {
            $this->actingAs($admin)->getJson(route('dashboard.search', ['q' => $query]))
                ->assertOk()->assertExactJson(['results' => []]);
        }
        foreach ([str_repeat('x', 161), ['unexpected']] as $query) {
            $this->getJson(route('dashboard.search', ['q' => $query]))
                ->assertUnprocessable()->assertJsonValidationErrors('q');
        }
    }

    public function test_staff_results_are_bounded(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        User::factory()->count(12)->create(['role' => User::ROLE_OFFICER, 'firstName' => 'Boundedmatch']);

        $this->actingAs($admin)->getJson(route('dashboard.search', ['q' => 'Boundedmatch']))
            ->assertOk()->assertJsonCount(5, 'results');
    }
}
