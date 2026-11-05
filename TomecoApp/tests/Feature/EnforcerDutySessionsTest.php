<?php

namespace Tests\Feature;

use App\Models\EnforcerAttendance;
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
            'attendance_time_out_start' => '16:00', 'attendance_time_out_end' => '18:00',
        ];
    }

    public function test_api_records_one_whole_day_with_coordinates_and_continuous_status(): void
    {
        Sanctum::actingAs($this->officer);
        $this->postJson('/api/officer/attendance/time-in', ['latitude' => 11.2, 'longitude' => 125.0])
            ->assertCreated()->assertJsonPath('attendance.session', 'whole_day');
        $this->postJson('/api/officer/attendance/time-in')->assertUnprocessable();
        $this->at('11:45');
        $this->postJson('/api/officer/attendance/time-out')->assertUnprocessable();
        $this->at('13:15');
        $this->getJson('/api/officer/attendance/status')->assertOk()
            ->assertJsonPath('status', 'timed_in')
            ->assertJsonPath('session', 'whole_day')
            ->assertJsonPath('sessions.whole_day.status', 'timed_in')
            ->assertJsonPath('schedule.sessions.whole_day.out_start', '16:00')
            ->assertJsonMissingPath('sessions.afternoon');
        $this->postJson('/api/officer/attendance/time-in')->assertUnprocessable();
        $this->at('16:30');
        $this->postJson('/api/officer/attendance/time-out', ['latitude' => 11.3, 'longitude' => 125.1])
            ->assertOk()->assertJsonPath('attendance.time_out_location.latitude', 11.3);
        $this->postJson('/api/officer/attendance/time-out')->assertUnprocessable();
        $this->assertDatabaseCount('enforcer_attendances', 1);
        $this->assertDatabaseHas('enforcer_attendances', ['session' => 'whole_day', 'time_in_latitude' => 11.2, 'time_out_latitude' => 11.3]);
        $this->getJson('/api/officer/attendance/status')->assertJsonPath('sessions.whole_day.status', 'timed_out');
    }

    public function test_completed_daily_attendance_cannot_be_reopened_even_when_clock_in_window_is_open(): void
    {
        $this->officer->update(['attendance_time_in_end' => '23:59']);
        Sanctum::actingAs($this->officer);
        $this->postJson('/api/officer/attendance/time-in')->assertCreated();
        $this->at('13:15');
        $this->postJson('/api/officer/attendance/time-in')->assertUnprocessable();
        $this->getJson('/api/officer/attendance/status')->assertJsonPath('session', 'whole_day')->assertJsonPath('status', 'timed_in');
        $this->at('16:30');
        $this->postJson('/api/officer/attendance/time-out')->assertOk();
        $this->postJson('/api/officer/attendance/time-in')->assertUnprocessable()
            ->assertJsonValidationErrors('attendance');
        $this->getJson('/api/officer/attendance/status')->assertJsonPath('status', 'timed_out');
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

    public function test_supervisor_records_one_whole_day_and_views_its_history_label(): void
    {
        $this->actingAs($this->supervisor);
        $this->post(route('supervisor.enforcers.time-in', $this->officer))->assertSessionHas('enforcer_attendance_success');
        $this->at('11:45');
        $this->post(route('supervisor.enforcers.time-out', $this->officer))->assertSessionHas('enforcer_attendance_error');
        $this->at('13:15');
        $this->post(route('supervisor.enforcers.time-in', $this->officer))->assertSessionHas('enforcer_attendance_error');
        $this->at('16:30');
        $this->post(route('supervisor.enforcers.time-out', $this->officer))->assertSessionHas('enforcer_attendance_success');
        $this->assertDatabaseCount('enforcer_attendances', 1);
        $this->get(route('supervisor.enforcers.attendance', $this->officer))->assertOk()
            ->assertSeeText('Whole day')->assertDontSeeText('Morning')->assertDontSeeText('Afternoon');
    }

    public function test_admin_saves_one_daily_schedule_with_no_afternoon_fields(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));
        $this->put(route('dashboard.attendance.restrictions.update', $this->officer), $this->schedule())
            ->assertSessionHasNoErrors()->assertSessionHas('attendance_success');
        $this->assertDatabaseHas('users', ['id' => $this->officer->id, 'attendance_time_out_start' => '16:00']);
        $this->put(route('dashboard.attendance.restrictions.update', $this->officer), array_merge($this->schedule(), [
            'attendance_time_out_end' => '15:30',
        ]))->assertSessionHasErrors('attendance_time_out_end');
        $this->get(route('dashboard.attendance', ['type' => 'enforcer']))->assertOk()
            ->assertDontSee('attendance_afternoon_time_')->assertDontSeeText('Morning duty')->assertDontSeeText('Afternoon duty');
    }

    public function test_a_daily_clock_in_window_can_start_after_noon(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->put(route('dashboard.attendance.restrictions.update', $this->officer), array_merge($this->schedule(), [
                'attendance_time_in_start' => '13:00', 'attendance_time_in_end' => '14:00',
            ]))->assertSessionHasNoErrors();

        $this->officer->refresh();
        Sanctum::actingAs($this->officer);
        $this->at('13:15');
        $this->postJson('/api/officer/attendance/time-in')->assertCreated()->assertJsonPath('attendance.session', 'whole_day');
    }

    public function test_legacy_records_are_preserved_and_do_not_allow_a_second_daily_clock_in(): void
    {
        $legacy = EnforcerAttendance::create([
            'user_id' => $this->officer->id, 'supervisor_id' => $this->supervisor->id,
            'attendance_date' => '2026-10-08', 'session' => 'morning',
            'time_in' => now(), 'time_out' => now()->addHours(4),
        ]);
        $this->officer->update(['attendance_time_in_end' => '23:59']);
        Sanctum::actingAs($this->officer);
        $this->at('13:15');
        $this->getJson('/api/officer/attendance/status')->assertJsonPath('status', 'timed_out');
        $this->postJson('/api/officer/attendance/time-in')->assertUnprocessable()->assertJsonValidationErrors('attendance');
        $this->assertDatabaseCount('enforcer_attendances', 1);
        $this->assertSame('morning', $legacy->fresh()->session);
    }

    public function test_new_working_day_allows_another_daily_record(): void
    {
        $this->officer->update(['attendance_working_days' => ['thursday', 'friday']]);
        Sanctum::actingAs($this->officer);
        $this->postJson('/api/officer/attendance/time-in')->assertCreated();
        $this->at('16:30');
        $this->postJson('/api/officer/attendance/time-out')->assertOk();
        Carbon::setTestNow(Carbon::parse('2026-10-09 08:00', 'Asia/Manila'));
        $this->getJson('/api/officer/attendance/status')->assertJsonPath('status', 'not_timed_in');
        $this->postJson('/api/officer/attendance/time-in')->assertCreated();
        $this->assertDatabaseCount('enforcer_attendances', 2);
    }

    public function test_migration_keeps_history_and_carries_final_time_out_into_the_daily_schedule(): void
    {
        $this->officer->update([
            'attendance_time_out_start' => '11:30', 'attendance_time_out_end' => '12:00',
            'attendance_afternoon_time_out_start' => '16:00', 'attendance_afternoon_time_out_end' => '18:00',
        ]);
        foreach (['morning', 'afternoon'] as $session) {
            EnforcerAttendance::create([
                'user_id' => $this->officer->id, 'supervisor_id' => $this->supervisor->id,
                'attendance_date' => '2026-10-07', 'session' => $session,
                'time_in' => now(), 'time_in_latitude' => 11.24, 'time_in_longitude' => 125.0,
            ]);
        }
        $before = EnforcerAttendance::orderBy('id')->get()->map->getRawOriginal()->all();
        $migration = require database_path('migrations/2026_10_21_000000_use_whole_day_enforcer_attendance.php');
        $migration->up();

        $this->assertSame($before, EnforcerAttendance::orderBy('id')->get()->map->getRawOriginal()->all());
        $this->assertSame('16:00', substr($this->officer->fresh()->attendance_time_out_start, 0, 5));
        $this->assertSame('18:00', substr($this->officer->fresh()->attendance_time_out_end, 0, 5));
        $this->assertSame('07:00', substr($this->officer->fresh()->attendance_time_in_start, 0, 5));
    }
}
