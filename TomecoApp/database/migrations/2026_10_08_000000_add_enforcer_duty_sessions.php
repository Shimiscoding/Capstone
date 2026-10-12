<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['in_start', 'in_end', 'out_start', 'out_end'] as $window) {
                if (! Schema::hasColumn('users', 'attendance_afternoon_time_'.$window)) {
                    $table->time('attendance_afternoon_time_'.$window)->nullable();
                }
            }
        });
        Schema::table('enforcer_attendances', function (Blueprint $table): void {
            if (! Schema::hasColumn('enforcer_attendances', 'session')) {
                $table->string('session', 16)->default('morning');
            }
        });
        // Install the replacement first: MySQL uses the user-leading index for the foreign key.
        if (! Schema::hasIndex('enforcer_attendances', ['user_id', 'attendance_date', 'session'])) {
            Schema::table('enforcer_attendances', function (Blueprint $table): void {
                $table->unique(['user_id', 'attendance_date', 'session']);
            });
        }
        if (Schema::hasIndex('enforcer_attendances', ['user_id', 'attendance_date'])) {
            Schema::table('enforcer_attendances', function (Blueprint $table): void {
                $table->dropUnique(['user_id', 'attendance_date']);
            });
        }
    }

    public function down(): void
    {
        // Preserve both sessions: rollback is only safe before afternoon records exist.
        if (DB::table('enforcer_attendances')->where('session', 'afternoon')->exists()) {
            throw new RuntimeException('Cannot roll back while afternoon attendance records exist.');
        }
        Schema::table('enforcer_attendances', function (Blueprint $table): void {
            $table->unique(['user_id', 'attendance_date']);
        });
        Schema::table('enforcer_attendances', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'attendance_date', 'session']);
            $table->dropColumn('session');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(array_map(fn ($window) => 'attendance_afternoon_time_'.$window, ['in_start', 'in_end', 'out_start', 'out_end']));
        });
    }
};
