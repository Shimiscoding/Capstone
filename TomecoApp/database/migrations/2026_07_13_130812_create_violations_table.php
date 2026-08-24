<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('violations', function (Blueprint $table) {
            $table->id();
            $table->string('motorist_name');
            $table->string('license_number')->nullable();
            $table->string('plate_number');
            $table->string('violation_type');
            $table->decimal('fine_amount', 10, 2);
            $table->string('status')->default('unpaid');
            $table->text('location')->nullable();
            $table->string('evidence_image')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('violations');
    }
};
