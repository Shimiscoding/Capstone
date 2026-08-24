<?php

namespace Tests\Feature;

use App\Models\EnforcerAttendance;
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
}
