<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->where('key', 'permissions.driver')->delete();
        DB::table('notifications')->where('data', 'like', '%driver%')->delete();
    }

    public function down(): void
    {
        // Removed role settings and notifications are intentionally not restored.
    }
};
