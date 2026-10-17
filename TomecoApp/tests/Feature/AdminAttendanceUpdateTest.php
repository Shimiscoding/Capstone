<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EnforcerAttendance;
use App\Models\OfficerLocation;
use App\Models\SupervisorAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_supervisor_and_enforcer_time_out(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $enforcer = User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'supervisor_id' => $supervisor->id,
        ]);
        $supervisorAttendance = SupervisorAttendance::create([
            'user_id' => $supervisor->id,
            'attendance_date' => today(),
            'time_in' => today()->setTime(8, 0),
        ]);
        $enforcerAttendance = EnforcerAttendance::create([
            'user_id' => $enforcer->id,
            'supervisor_id' => $supervisor->id,
            'attendance_date' => today(),
            'time_in' => today()->setTime(8, 30),
        ]);

        $this->actingAs($admin)->put(
            route('dashboard.attendance.supervisors.time-out', $supervisorAttendance),
            ['time_in' => '07:45', 'time_out' => '17:00'],
        )->assertRedirect();

        $this->actingAs($admin)->put(
            route('dashboard.attendance.enforcers.time-out', $enforcerAttendance),
            ['time_in' => '08:15', 'time_out' => '17:30'],
        )->assertRedirect();

        $this->assertSame('07:45', $supervisorAttendance->fresh()->time_in->format('H:i'));
        $this->assertSame('17:00', $supervisorAttendance->fresh()->time_out->format('H:i'));
        $this->assertSame('08:15', $enforcerAttendance->fresh()->time_in->format('H:i'));
        $this->assertSame('17:30', $enforcerAttendance->fresh()->time_out->format('H:i'));
        $this->assertSame('Attendance updated', $supervisor->fresh()->notifications()->first()->data['title']);
        $this->assertStringContainsString('07:45 AM', $supervisor->fresh()->notifications()->first()->data['message']);
        $this->assertStringContainsString('05:00 PM', $supervisor->fresh()->notifications()->first()->data['message']);
        $this->assertSame('Attendance updated', $enforcer->fresh()->notifications()->first()->data['title']);
        $this->assertStringContainsString('05:30 PM', $enforcer->fresh()->notifications()->first()->data['message']);
        $this->assertSame(2, $admin->fresh()->notifications()->count());
        $this->assertSame('Attendance updated', $admin->fresh()->notifications()->first()->data['title']);
        $this->assertTrue($admin->fresh()->notifications->contains(
            fn ($notification) => str_contains($notification->data['message'], $enforcer->fullName),
        ));

        $this->actingAs($supervisor)->getJson(route('dashboard.notifications.feed'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.title', 'Attendance updated')
            ->assertJsonPath('notifications.0.message', $supervisor->fresh()->notifications()->first()->data['message']);
    }

    public function test_non_admin_cannot_update_time_out(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $enforcer = User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'supervisor_id' => $supervisor->id,
        ]);
        $attendance = EnforcerAttendance::create([
            'user_id' => $enforcer->id,
            'supervisor_id' => $supervisor->id,
            'attendance_date' => today(),
            'time_in' => today()->setTime(8, 0),
        ]);

        $this->actingAs($supervisor)->put(
            route('dashboard.attendance.enforcers.time-out', $attendance),
            ['time_in' => '08:00', 'time_out' => '17:00'],
        )->assertForbidden();

        $this->assertNull($attendance->fresh()->time_out);
    }

    public function test_time_out_must_be_after_time_in(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $attendance = SupervisorAttendance::create([
            'user_id' => $supervisor->id,
            'attendance_date' => today(),
            'time_in' => today()->setTime(9, 0),
        ]);

        $this->actingAs($admin)->put(
            route('dashboard.attendance.supervisors.time-out', $attendance),
            ['time_in' => '09:00', 'time_out' => '09:00'],
        )->assertUnprocessable();

        $this->assertNull($attendance->fresh()->time_out);
    }

    public function test_admin_deletes_only_the_selected_record_and_preserves_audit_history(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $enforcer = User::factory()->create(['role' => User::ROLE_OFFICER, 'supervisor_id' => $supervisor->id]);
        $morning = EnforcerAttendance::create(['user_id' => $enforcer->id, 'supervisor_id' => $supervisor->id, 'session' => 'morning', 'attendance_date' => today(), 'time_in' => today()->setTime(8, 0), 'time_out' => today()->setTime(12, 0)]);
        $afternoon = EnforcerAttendance::create(['user_id' => $enforcer->id, 'supervisor_id' => $supervisor->id, 'session' => 'afternoon', 'attendance_date' => today(), 'time_in' => today()->setTime(13, 0)]);
        $supervisorRecord = SupervisorAttendance::create(['user_id' => $supervisor->id, 'attendance_date' => today(), 'time_in' => today()->setTime(8, 0)]);
        $location = OfficerLocation::create(['user_id' => $enforcer->id, 'team_id' => $supervisor->id, 'latitude' => 11.2, 'longitude' => 125.0, 'is_sharing' => true, 'recorded_at' => now()]);

        $this->actingAs($admin)->delete(route('dashboard.attendance.enforcers.destroy', $morning))
            ->assertRedirect(route('dashboard.attendance.enforcers.show', ['user' => $enforcer, 'page' => 1]))
            ->assertSessionHas('attendance_success', 'Attendance record deleted.');
        $this->assertDatabaseMissing('enforcer_attendances', ['id' => $morning->id]);
        $this->assertDatabaseHas('enforcer_attendances', ['id' => $afternoon->id]);
        $this->assertTrue($location->fresh()->is_sharing);
        $this->assertDatabaseHas('users', ['id' => $enforcer->id]);
        $audit = AuditLog::where('auditable_type', EnforcerAttendance::class)->where('auditable_id', $morning->id)->where('event', 'deleted')->firstOrFail();
        $this->assertSame($admin->id, $audit->user_id);

        $this->actingAs($admin)->deleteJson(route('dashboard.attendance.enforcers.destroy', $afternoon))
            ->assertOk()->assertJsonPath('message', 'Attendance record deleted.');
        $this->assertFalse($location->fresh()->is_sharing);
        $this->actingAs($admin)->delete(route('dashboard.attendance.supervisors.destroy', $supervisorRecord))
            ->assertRedirect(route('dashboard.attendance.supervisor', ['user' => $supervisor, 'page' => 1]));
        $this->assertDatabaseMissing('supervisor_attendances', ['id' => $supervisorRecord->id]);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => SupervisorAttendance::class, 'auditable_id' => $supervisorRecord->id, 'event' => 'deleted', 'user_id' => $admin->id]);
    }

    public function test_supervisor_cannot_delete_attendance_even_for_their_own_team(): void
    {
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $enforcer = User::factory()->create(['role' => User::ROLE_OFFICER, 'supervisor_id' => $supervisor->id]);
        $attendance = EnforcerAttendance::create(['user_id' => $enforcer->id, 'supervisor_id' => $supervisor->id, 'attendance_date' => today(), 'time_in' => now()]);
        $supervisorRecord = SupervisorAttendance::create(['user_id' => $supervisor->id, 'attendance_date' => today(), 'time_in' => now()]);
        $this->actingAs($supervisor)->delete(route('dashboard.attendance.enforcers.destroy', $attendance))->assertForbidden();
        $this->actingAs($supervisor)->delete(route('dashboard.attendance.supervisors.destroy', $supervisorRecord))->assertForbidden();
        $this->assertDatabaseHas('enforcer_attendances', ['id' => $attendance->id]);
        $this->assertDatabaseHas('supervisor_attendances', ['id' => $supervisorRecord->id]);
        $this->actingAs($supervisor)->get(route('supervisor.enforcers.attendance', $enforcer))
            ->assertOk()->assertDontSee('data-attendance-delete', false)->assertDontSee('data-attendance-edit', false);
    }

    public function test_deleting_last_record_on_a_page_returns_to_a_valid_page(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $enforcer = User::factory()->create(['role' => User::ROLE_OFFICER, 'supervisor_id' => $supervisor->id]);
        for ($day = 0; $day < 21; $day++) {
            $attendance = EnforcerAttendance::create(['user_id' => $enforcer->id, 'supervisor_id' => $supervisor->id, 'attendance_date' => today()->subDays($day), 'time_in' => today()->subDays($day)->setTime(8, 0), 'time_out' => today()->subDays($day)->setTime(12, 0)]);
        }
        $this->actingAs($admin)->deleteJson(route('dashboard.attendance.enforcers.destroy', $attendance), ['page' => 2])
            ->assertOk()->assertJsonPath('redirect', route('dashboard.attendance.enforcers.show', ['user' => $enforcer, 'page' => 1]));
        $this->assertSame(20, $enforcer->enforcerAttendances()->count());
        $this->actingAs($admin)->deleteJson(route('dashboard.attendance.enforcers.destroy', $attendance))->assertNotFound();
    }

    public function test_history_renders_shared_actions_and_edit_requests_return_json_feedback(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $attendance = SupervisorAttendance::create(['user_id' => $supervisor->id, 'attendance_date' => today(), 'time_in' => today()->setTime(8, 0)]);
        $this->actingAs($admin)->get(route('dashboard.attendance.supervisor', $supervisor))
            ->assertOk()->assertSee('attendance-records-scroll', false)->assertSee('Delete attendance record?')
            ->assertSee('data-attendance-edit', false)->assertSee('data-attendance-delete', false)
            ->assertSee('attendance-history.js')->assertDontSee('attendance-edit-dropdown', false);
        $this->actingAs($admin)->putJson(route('dashboard.attendance.supervisors.time-out', $attendance), ['time_in' => '08:00', 'time_out' => '17:00'])
            ->assertOk()->assertJsonPath('message', 'Supervisor attendance updated successfully.')
            ->assertSessionHas('attendance_success', 'Supervisor attendance updated successfully.');
        $this->actingAs($admin)->putJson(route('dashboard.attendance.supervisors.time-out', $attendance), ['time_in' => '', 'time_out' => '17:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('time_in');
    }
}
