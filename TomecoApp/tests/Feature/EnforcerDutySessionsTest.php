<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnforcerDutySessionsTest extends TestCase
{
    use RefreshDatabase;

    private User $supervisor;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $this->officer = User::factory()->create(array_merge($this->schedule(), [
            'role' => User::ROLE_OFFICER, 'supervisor_id' => $this->supervisor->id,
        ]));
        $this->at('08:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $time): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-08 '.$time, 'Asia/Manila'));
    }

    private function schedule(): array
    {
        return [
            'attendance_restrictions_enabled' => true,
            'attendance_working_days' => ['thursday'],
            'attendance_time_in_start' => '07:00', 'attendance_time_in_end' => '09:00',
            'attendance_time_out_start' => '11:30', 'attendance_time_out_end' => '12:00',
            'attendance_afternoon_time_in_start' => '13:00', 'attendance_afternoon_time_in_end' => '14:00',
            'attendance_afternoon_time_out_start' => '16:00', 'attendance_afternoon_time_out_end' => '18:00',
        ];
    }

    public function test_api_records_two_sessions_with_coordinates_and_independent_status(): void
    {
        Sanctum::actingAs($this->officer);
        $this->postJson('/api/officer/attendance/time-in', ['latitude' => 11.2, 'longitude' => 125.0])
            ->assertCreated()->assertJsonPath('attendance.session', 'morning');
        $this->postJson('/api/officer/attendance/time-in')->assertUnprocessable();
        $this->at('11:45');
        $this->postJson('/api/officer/attendance/time-out')->assertOk();
        $this->at('13:15');
        $this->getJson('/api/officer/attendance/status')->assertOk()
            ->assertJsonPath('status', 'not_timed_in')
            ->assertJsonPath('session', 'afternoon')
            ->assertJsonPath('sessions.morning.status', 'timed_out')
            ->assertJsonPath('schedule.sessions.afternoon.in_start', '13:00');
        $this->postJson('/api/officer/attendance/time-in', ['latitude' => 11.3, 'longitude' => 125.1])
            ->assertCreated()->assertJsonPath('attendance.session', 'afternoon');
        $this->postJson('/api/officer/attendance/time-in')->assertUnprocessable();
        $this->at('16:30');
        $this->postJson('/api/officer/attendance/time-out')->assertOk();
        $this->postJson('/api/officer/attendance/time-out')->assertUnprocessable();
        $this->assertDatabaseCount('enforcer_attendances', 2);
        $this->assertDatabaseHas('enforcer_attendances', ['session' => 'morning', 'time_in_latitude' => 11.2]);
        $this->assertDatabaseHas('enforcer_attendances', ['session' => 'afternoon', 'time_in_latitude' => 11.3]);
        $this->getJson('/api/officer/attendance/status')->assertJsonPath('sessions.afternoon.status', 'timed_out');
    }

    public function test_open_morning_duty_blocks_afternoon_and_uses_morning_time_out_window(): void
    {
        Sanctum::actingAs($this->officer);
        $this->postJson('/api/officer/attendance/time-in')->assertCreated();
        $this->at('13:15');
        $this->postJson('/api/officer/attendance/time-in')->assertUnprocessable();
        $this->getJson('/api/officer/attendance/status')->assertJsonPath('session', 'morning')->assertJsonPath('status', 'timed_in');
        $this->at('16:30');
        $this->postJson('/api/officer/attendance/time-out')->assertUnprocessable();
        $this->assertDatabaseCount('enforcer_attendances', 1);
    }

    public function test_schedule_windows_and_days_are_enforced(): void
    {
        Sanctum::actingAs($this->officer);
        $this->at('10:00');
        $this->postJson('/api/officer/attendance/time-in')->assertUnprocessable();
        $this->at('12:30');
        $this->postJson('/api/officer/attendance/time-in')->assertUnprocessable();
        $this->at('15:00');
        $this->postJson('/api/officer/attendance/time-in')->assertUnprocessable();
        Carbon::setTestNow(Carbon::parse('2026-10-09 08:00', 'Asia/Manila'));
        $this->postJson('/api/officer/attendance/time-in')->assertUnprocessable();
        $this->assertDatabaseCount('enforcer_attendances', 0);
    }

    public function test_supervisor_can_record_both_sessions_and_view_duty_labels(): void
    {
        $this->actingAs($this->supervisor);
        $this->post(route('supervisor.enforcers.time-in', $this->officer))->assertSessionHas('enforcer_attendance_success');
        $this->at('11:45');
        $this->post(route('supervisor.enforcers.time-out', $this->officer))->assertSessionHas('enforcer_attendance_success');
        $this->at('13:15');
        $this->post(route('supervisor.enforcers.time-in', $this->officer))->assertSessionHas('enforcer_attendance_success');
        $this->at('16:30');
        $this->post(route('supervisor.enforcers.time-out', $this->officer))->assertSessionHas('enforcer_attendance_success');
        $this->assertDatabaseCount('enforcer_attendances', 2);
        $this->get(route('supervisor.enforcers.attendance', $this->officer))->assertOk()->assertSeeText('Morning')->assertSeeText('Afternoon');
    }

    public function test_admin_saves_both_windows_and_rejects_overlapping_sessions(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->put(route('dashboard.attendance.restrictions.update', $this->officer), $this->schedule())
            ->assertSessionHasNoErrors()->assertSessionHas('attendance_success');
        $this->assertDatabaseHas('users', ['id' => $this->officer->id, 'attendance_afternoon_time_in_start' => '13:00']);
        $this->put(route('dashboard.attendance.restrictions.update', $this->officer), array_merge($this->schedule(), [
            'attendance_time_out_end' => '13:30',
        ]))->assertSessionHasErrors('attendance_afternoon_time_in_start');
        $this->get(route('dashboard.attendance', ['type' => 'enforcer']))->assertOk()->assertSee('attendance_afternoon_time_')->assertSeeText('Afternoon duty');
    }
}
