<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep historical attendance and legacy schedule columns intact. Carry the
        // final clock-out window into the single daily schedule for split shifts.
        DB::table('users')->where('role', 'officer')
            ->whereNotNull('attendance_afternoon_time_out_start')
            ->whereNotNull('attendance_afternoon_time_out_end')
            ->update([
                'attendance_time_out_start' => DB::raw('attendance_afternoon_time_out_start'),
                'attendance_time_out_end' => DB::raw('attendance_afternoon_time_out_end'),
            ]);

        Schema::table('enforcer_attendances', function (Blueprint $table): void {
            $table->string('session', 16)->default('whole_day')->change();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Whole-day schedules cannot be rolled back automatically. Preserve daily records and restore schedules explicitly if required.');
    }
};
