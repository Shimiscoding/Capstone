<?php

namespace Tests\Feature;

use App\Models\EnforcerAttendance;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UnusedAfternoonAttendanceColumnsTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS = [
        'attendance_afternoon_time_in_start',
        'attendance_afternoon_time_in_end',
        'attendance_afternoon_time_out_start',
        'attendance_afternoon_time_out_end',
    ];

    public function test_cleanup_removes_retired_values_and_preserves_users_daily_schedules_and_attendance_history(): void
    {
        foreach (self::COLUMNS as $column) {
            $this->assertFalse(Schema::hasColumn('users', $column));
        }
        $remainingColumns = Schema::getColumnListing('users');
        Schema::table('users', function (Blueprint $table): void {
            foreach (self::COLUMNS as $column) {
                $table->time($column)->nullable();
            }
        });
        $supervisor = User::factory()->create(['role' => User::ROLE_SUPERVISOR]);
        $officer = User::factory()->create([
            'role' => User::ROLE_OFFICER,
            'supervisor_id' => $supervisor->id,
            'attendance_restrictions_enabled' => true,
            'attendance_working_days' => ['monday', 'tuesday'],
            'attendance_time_in_start' => '07:00',
            'attendance_time_in_end' => '09:00',
            'attendance_time_out_start' => '16:00',
            'attendance_time_out_end' => '18:00',
        ]);
        DB::table('users')->where('id', $officer->id)->update(array_combine(
            self::COLUMNS, ['13:00', '14:00', '16:00', '18:00'],
        ));
        foreach (['morning', 'afternoon', 'whole_day'] as $index => $session) {
            EnforcerAttendance::create([
                'user_id' => $officer->id,
                'supervisor_id' => $supervisor->id,
                'session' => $session,
                'attendance_date' => '2026-11-'.(9 + $index),
                'time_in' => '2026-11-'.(9 + $index).' 08:00:00',
                'time_out' => '2026-11-'.(9 + $index).' 17:00:00',
                'time_in_latitude' => 11.24,
                'time_in_longitude' => 125.0,
            ]);
        }
        $usersBefore = DB::table('users')->select($remainingColumns)->orderBy('id')->get()->all();
        $attendanceBefore = DB::table('enforcer_attendances')->orderBy('id')->get()->all();

        $migration = require database_path('migrations/2026_11_12_000100_remove_unused_afternoon_attendance_columns.php');
        $migration->up();
        $migration->up();

        $this->assertSame($remainingColumns, Schema::getColumnListing('users'));
        $this->assertEquals($usersBefore, DB::table('users')->orderBy('id')->get()->all());
        $this->assertEquals($attendanceBefore, DB::table('enforcer_attendances')->orderBy('id')->get()->all());
    }

    public function test_cleanup_handles_a_schema_with_only_some_retired_columns_remaining(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->time('attendance_afternoon_time_in_start')->nullable();
        });
        $migration = require database_path('migrations/2026_11_12_000100_remove_unused_afternoon_attendance_columns.php');

        $migration->up();

        foreach (self::COLUMNS as $column) {
            $this->assertFalse(Schema::hasColumn('users', $column));
        }
    }
}
