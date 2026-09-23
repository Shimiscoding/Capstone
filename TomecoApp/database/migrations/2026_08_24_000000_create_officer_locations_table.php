<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('officer_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Existing TOMECO teams are supervisor-led groups, so this references the supervising user.
            $table->foreignId('team_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('speed', 8, 3)->nullable();
            $table->decimal('heading', 6, 2)->nullable();
            $table->boolean('is_sharing')->default(true)->index();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->index(['team_id', 'is_sharing']);
            $table->index('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('officer_locations');
    }
};
