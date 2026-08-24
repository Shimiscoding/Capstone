<?php

namespace Tests\Feature;

use App\Models\EnforcerAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnforcerAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_can_manage_attendance_for_an_assigned_enforcer(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $enforcer = User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($supervisor)->post(route('supervisor.enforcers.time-in', $enforcer))->assertRedirect();
        $attendance = EnforcerAttendance::where('user_id', $enforcer->id)->firstOrFail();
        $this->assertNotNull($attendance->time_in);
        $this->assertSame($supervisor->id, $attendance->supervisor_id);

        $this->actingAs($supervisor)->post(route('supervisor.enforcers.time-out', $enforcer))->assertRedirect();
        $this->assertNotNull($attendance->fresh()->time_out);
        $this->assertSame(1, EnforcerAttendance::where('user_id', $enforcer->id)->count());
    }

    public function test_supervisor_cannot_manage_another_supervisors_enforcer(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $otherSupervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $enforcer = User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'supervisor_id' => $otherSupervisor->id,
        ]);

        $this->actingAs($supervisor)->post(route('supervisor.enforcers.time-in', $enforcer))->assertNotFound();
        $this->assertDatabaseCount('enforcer_attendances', 0);
    }

    public function test_enforcer_name_opens_attendance_details_only_for_their_supervisor(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $otherSupervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $enforcer = User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'supervisor_id' => $supervisor->id,
            'fullName' => 'Clickable Enforcer',
        ]);

        $this->actingAs($supervisor)
            ->get(route('supervisor.enforcers.attendance', $enforcer))
            ->assertOk()
            ->assertSee('Clickable Enforcer')
            ->assertSee('Complete Time In and Time Out timestamp history');

        $this->actingAs($otherSupervisor)
            ->get(route('supervisor.enforcers.attendance', $enforcer))
            ->assertNotFound();
    }

    public function test_attendance_sidebar_page_is_separate_from_supervisor_home(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $enforcer = User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'supervisor_id' => $supervisor->id,
            'fullName' => 'Attendance List Enforcer',
        ]);

        $this->actingAs($supervisor)
            ->get(route('supervisor.enforcers.attendance.index'))
            ->assertOk()
            ->assertSee('Enforcer Attendance')
            ->assertSee('Attendance List Enforcer');

        $this->actingAs($supervisor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Assigned enforcers')
            ->assertDontSee('Attendance Records');
    }

    public function test_admin_can_select_enforcers_and_view_their_attendance_details(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $enforcer = User::factory()->create(['role' => User::ROLE_OFFICER, 'supervisor_id' => $supervisor->id, 'fullName' => 'Admin Visible Enforcer']);
        EnforcerAttendance::create(['user_id' => $enforcer->id, 'supervisor_id' => $supervisor->id, 'attendance_date' => today(), 'time_in' => now()]);

        $this->actingAs($admin)->get(route('dashboard.attendance', ['type' => 'enforcer']))
            ->assertOk()
            ->assertSee('Enforcer Attendance')
            ->assertSee('Admin Visible Enforcer');

        $this->actingAs($admin)->get(route('supervisor.enforcers.attendance', $enforcer))
            ->assertOk()
            ->assertSee('Attendance history');
    }
}
