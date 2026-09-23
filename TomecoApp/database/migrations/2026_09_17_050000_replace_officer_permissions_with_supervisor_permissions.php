<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'permissions.officer')->delete();
    }

    public function down(): void
    {
        DB::table('settings')->updateOrInsert(
            ['key' => 'permissions.officer'],
            ['value' => json_encode(['record_violations', 'export']), 'created_at' => now(), 'updated_at' => now()]
        );
    }
};
