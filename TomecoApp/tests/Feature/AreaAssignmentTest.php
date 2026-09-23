<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AreaAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_and_assign_areas_to_all_officers(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $this->actingAs($admin)->get(route('area-assignments.index'))
            ->assertOk()->assertSee($officer->fullName)->assertSee('Area Assignments');

        $this->actingAs($admin)->put(route('area-assignments.update', $officer), ['area' => 'Downtown Tacloban'])
            ->assertRedirect()->assertSessionHas('area_assignment_success');

        $this->assertSame('Downtown Tacloban', $officer->fresh()->area);
    }

    public function test_area_filter_only_returns_matching_assignments(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $paloOfficer = User::factory()->create(['role' => User::ROLE_OFFICER, 'area' => 'Palo', 'firstName' => 'Palo', 'lastName' => 'Officer']);
        $borgosOfficer = User::factory()->create(['role' => User::ROLE_OFFICER, 'area' => 'Borgos', 'firstName' => 'Borgos', 'lastName' => 'Officer']);

        $this->actingAs($admin)->get(route('area-assignments.index', ['type' => 'officer', 'area' => 'Palo']))
            ->assertOk()->assertSee($paloOfficer->fullName)->assertDontSee($borgosOfficer->fullName);
    }

    public function test_admin_can_view_and_assign_areas_to_supervisors(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);

        $this->actingAs($admin)->get(route('area-assignments.index', ['type' => 'supervisor']))
            ->assertOk()->assertSee('Supervisor Area Assignments')->assertSee($supervisor->fullName);
        $this->actingAs($admin)->put(route('area-assignments.update', $supervisor), ['area' => 'Abucay'])
            ->assertRedirect();

        $this->assertSame('Abucay', $supervisor->fresh()->area);
    }

    public function test_supervisor_can_only_view_and_assign_their_own_officers(): void
    {
        $supervisorA = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $supervisorB = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $officerA = User::factory()->create(['role' => User::ROLE_OFFICER, 'supervisor_id' => $supervisorA->id]);
        $officerB = User::factory()->create(['role' => User::ROLE_OFFICER, 'supervisor_id' => $supervisorB->id]);

        $this->actingAs($supervisorA)->get(route('area-assignments.index'))
            ->assertOk()->assertSee($officerA->fullName)->assertDontSee($officerB->fullName);

        $this->actingAs($supervisorA)->put(route('area-assignments.update', $officerA), ['area' => 'Downtown'])
            ->assertRedirect();
        $this->actingAs($supervisorA)->put(route('area-assignments.update', $officerB), ['area' => 'Downtown'])
            ->assertForbidden();
    }

    public function test_officer_cannot_access_area_assignments_on_the_website(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER, 'supervisor_id' => $supervisor->id, 'area' => 'Downtown']);
        $this->actingAs($officer)->get(route('area-assignments.index'))
            ->assertForbidden();
        $this->actingAs($officer)->put(route('area-assignments.update', $officer), ['area' => 'Abucay'])->assertForbidden();
        $this->assertSame('Downtown', $officer->fresh()->area);
    }
}
