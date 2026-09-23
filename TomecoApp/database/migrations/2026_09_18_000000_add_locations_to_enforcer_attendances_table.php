<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enforcer_attendances', function (Blueprint $table): void {
            $table->decimal('time_in_latitude', 10, 7)->nullable()->after('time_in');
            $table->decimal('time_in_longitude', 10, 7)->nullable()->after('time_in_latitude');
            $table->decimal('time_out_latitude', 10, 7)->nullable()->after('time_out');
            $table->decimal('time_out_longitude', 10, 7)->nullable()->after('time_out_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('enforcer_attendances', function (Blueprint $table): void {
            $table->dropColumn(['time_in_latitude', 'time_in_longitude', 'time_out_latitude', 'time_out_longitude']);
        });
    }
};
