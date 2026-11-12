<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Whole-day attendance uses attendance_time_*. Only remove the retired
        // split-shift settings; historical attendance sessions remain intact.
        $columns = array_values(array_filter([
            'attendance_afternoon_time_in_start',
            'attendance_afternoon_time_in_end',
            'attendance_afternoon_time_out_start',
            'attendance_afternoon_time_out_end',
        ], fn (string $column): bool => Schema::hasColumn('users', $column)));

        if ($columns !== []) {
            Schema::table('users', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Removed legacy afternoon schedule values cannot be recovered by rolling back.');
    }
};
