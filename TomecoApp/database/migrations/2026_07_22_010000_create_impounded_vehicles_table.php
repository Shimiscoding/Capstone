<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impounded_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('owner');
            $table->string('vehicle');
            $table->string('type', 50);
            $table->string('plate', 50)->index();
            $table->string('violation');
            $table->date('impounded_at')->index();
            $table->string('location');
            $table->string('status', 50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impounded_vehicles');
    }
};
