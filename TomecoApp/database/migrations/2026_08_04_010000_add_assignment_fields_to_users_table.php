<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->nullable()->unique()->after('nameExtension');
            $table->string('address')->nullable()->after('username');
            $table->string('area')->nullable()->after('address');
            $table->string('barangay')->nullable()->after('area');
            $table->foreignId('supervisor_id')->nullable()->after('role')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supervisor_id');
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'address', 'area', 'barangay']);
        });
    }
};
