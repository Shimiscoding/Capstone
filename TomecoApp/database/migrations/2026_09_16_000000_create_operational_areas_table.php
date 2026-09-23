<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_areas', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        DB::table('users')->whereNotNull('area')->where('area', '<>', '')->pluck('area')
            ->map(fn (string $area): string => trim($area))
            ->filter()->unique(fn (string $area): string => mb_strtolower($area))
            ->each(fn (string $area) => DB::table('operational_areas')->insertOrIgnore([
                'name' => $area,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_areas');
    }
};
