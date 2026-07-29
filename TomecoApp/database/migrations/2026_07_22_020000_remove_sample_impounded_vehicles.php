<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('impounded_vehicles')->whereIn('reference', [
            'IMP-2026-0042',
            'IMP-2026-0041',
            'IMP-2026-0040',
            'IMP-2025-0039',
            'IMP-2025-0038',
        ])->delete();
    }

    public function down(): void
    {
        // Sample records are intentionally not restored.
    }
};
