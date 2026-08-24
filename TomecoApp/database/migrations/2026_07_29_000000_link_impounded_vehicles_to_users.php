<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('impounded_vehicles', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });

        $driversByPlate = DB::table('users')
            ->where('role', User::ROLE_DRIVER)
            ->whereNotNull('plateNumber')
            ->get(['id', 'plateNumber'])
            ->keyBy(fn (object $user): string => Str::lower(trim($user->plateNumber)));

        DB::table('impounded_vehicles')
            ->whereNull('user_id')
            ->orderBy('id')
            ->each(function (object $vehicle) use ($driversByPlate): void {
                $user = $driversByPlate->get(Str::lower(trim($vehicle->plate)));

                if ($user) {
                    DB::table('impounded_vehicles')
                        ->where('id', $vehicle->id)
                        ->update(['user_id' => $user->id]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('impounded_vehicles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
