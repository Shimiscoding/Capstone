<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AreaAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('operational_areas')->insertOrIgnore(['name' => 'Abucay', 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_user_forms_cannot_assign_or_change_areas(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->get(route('dashboard.users.enforcers.create'))->assertOk()->assertDontSee('name="area"', false);
        $attributes = ['firstName' => 'New', 'lastName' => 'Enforcer', 'username' => 'new.enforcer',
            'address' => 'Test address', 'phoneNumber' => '09123456789', 'email' => 'new.enforcer@example.com',
            'role' => User::ROLE_OFFICER, 'password' => 'password123', 'password_confirmation' => 'password123', 'area' => 'Abucay'];
        $this->post(route('dashboard.users.store'), $attributes)->assertSessionHasNoErrors()->assertRedirect();
        $officer = User::where('email', $attributes['email'])->firstOrFail();
        $this->assertNull($officer->area);
        $this->put(route('area-assignments.update', $officer), ['area' => 'Abucay'])->assertSessionHasNoErrors();
        unset($attributes['password'], $attributes['password_confirmation']);
        $attributes['area'] = 'Unauthorized change';
        $this->put(route('dashboard.users.update', $officer), $attributes)->assertSessionHasNoErrors();
        $this->assertSame('Abucay', $officer->fresh()->area);
    }

    public function test_profile_updates_cannot_change_operational_area(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR, 'username' => 'profile.supervisor']);
        $this->actingAs($supervisor)->get(route('profile.edit'))->assertOk()->assertDontSee('name="area"', false);
        $this->put(route('profile.update'), ['firstName' => 'Updated', 'lastName' => 'Supervisor',
            'username' => $supervisor->username, 'address' => 'Test address', 'phoneNumber' => $supervisor->phoneNumber,
            'email' => $supervisor->email, 'area' => 'Abucay'])->assertSessionHasNoErrors();
        $this->assertNull($supervisor->fresh()->area);
    }

    public function test_admin_can_view_and_assign_areas_to_all_officers(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);

        $this->actingAs($admin)->get(route('area-assignments.index'))
            ->assertOk()->assertSee($officer->fullName)->assertSee('Area Assignments');

        $this->actingAs($admin)->put(route('area-assignments.update', $officer), ['area' => 'Abucay'])
            ->assertRedirect()->assertSessionHas('area_assignment_success');

        $this->assertSame('Abucay', $officer->fresh()->area);
    }

    public function test_area_filter_only_returns_matching_assignments(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $paloOfficer = User::factory()->create(['role' => User::ROLE_OFFICER, 'area' => 'Palo', 'firstName' => 'Palo', 'lastName' => 'Officer']);
        $borgosOfficer = User::factory()->create(['role' => User::ROLE_OFFICER, 'area' => 'Borgos', 'firstName' => 'Borgos', 'lastName' => 'Officer']);

        $this->actingAs($admin)->get(route('area-assignments.index', ['type' => 'officer', 'area' => 'Palo']))
            ->assertOk()->assertSee($paloOfficer->fullName)->assertDontSee($borgosOfficer->fullName);
    }

    public function test_supervisors_cannot_receive_areas_even_through_a_direct_request(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR, 'firstName' => 'Hidden', 'lastName' => 'Supervisor']);
        $officer = User::factory()->create(['role' => User::ROLE_OFFICER]);
        $this->actingAs($admin)->get(route('area-assignments.index', ['type' => 'supervisor']))
            ->assertOk()->assertSeeText('Enforcer Area Assignments')->assertSeeText($officer->fullName)
            ->assertViewHas('officers', fn ($rows) => $rows->every(fn ($user) => $user->isOfficer()));
        $this->actingAs($admin)->put(route('area-assignments.update', $supervisor), ['area' => 'Abucay'])->assertForbidden();
        $this->assertNull($supervisor->fresh()->area);
    }

    public function test_supervisor_can_only_view_and_assign_their_own_officers(): void
    {
        $supervisorA = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $supervisorB = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $officerA = User::factory()->create(['role' => User::ROLE_OFFICER, 'supervisor_id' => $supervisorA->id]);
        $officerB = User::factory()->create(['role' => User::ROLE_OFFICER, 'supervisor_id' => $supervisorB->id]);

        $this->actingAs($supervisorA)->get(route('area-assignments.index'))
            ->assertOk()->assertSee($officerA->fullName)->assertDontSee($officerB->fullName);

        $this->actingAs($supervisorA)->put(route('area-assignments.update', $officerA), ['area' => 'Abucay'])
            ->assertRedirect();
        $this->actingAs($supervisorA)->put(route('area-assignments.update', $officerB), ['area' => 'Abucay'])
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
