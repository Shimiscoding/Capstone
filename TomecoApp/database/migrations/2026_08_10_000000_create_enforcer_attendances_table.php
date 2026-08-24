<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enforcer_attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supervisor_id')->constrained('users')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->timestamp('time_in')->nullable();
            $table->timestamp('time_out')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'attendance_date']);
            $table->index(['supervisor_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enforcer_attendances');
    }
};
