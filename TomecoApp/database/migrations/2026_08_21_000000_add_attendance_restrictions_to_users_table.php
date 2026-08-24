<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('attendance_restrictions_enabled')->default(false);
            $table->time('attendance_time_in_start')->nullable();
            $table->time('attendance_time_in_end')->nullable();
            $table->time('attendance_time_out_start')->nullable();
            $table->time('attendance_time_out_end')->nullable();
            $table->json('attendance_working_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'attendance_restrictions_enabled',
                'attendance_time_in_start',
                'attendance_time_in_end',
                'attendance_time_out_start',
                'attendance_time_out_end',
                'attendance_working_days',
            ]);
        });
    }
};
