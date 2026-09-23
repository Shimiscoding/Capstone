<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('violations', function (Blueprint $table): void {
            $table->string('license_type', 30)->nullable()->after('license_number');
            $table->text('or_number')->nullable()->after('plate_number');
            $table->text('cr_number')->nullable()->after('or_number');
        });
    }

    public function down(): void
    {
        Schema::table('violations', function (Blueprint $table): void {
            $table->dropColumn(['license_type', 'or_number', 'cr_number']);
        });
    }
};
