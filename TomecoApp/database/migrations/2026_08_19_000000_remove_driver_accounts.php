<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('violations', 'user_id')) {
            Schema::table('violations', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('user_id');
            });
        }

        if (Schema::hasColumn('violations', 'driver_name')) {
            Schema::table('violations', function (Blueprint $table): void {
                $table->renameColumn('driver_name', 'motorist_name');
            });
        }

        DB::table('users')->where('role', 'driver')->delete();

        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('driver_profiles');

        Schema::table('users', function (Blueprint $table): void {
            $columns = collect(['driverLicense', 'plateNumber'])
                ->filter(fn (string $column): bool => Schema::hasColumn('users', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }

    public function down(): void
    {
        // Driver accounts and their associated data are intentionally not recoverable.
    }
};
