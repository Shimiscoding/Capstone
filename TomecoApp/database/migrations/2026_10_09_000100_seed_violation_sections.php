<?php

use App\Services\ViolationCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $groups = [7 => 'Illegal parking', 16 => 'Obstruction', 19 => 'Unregistered Vehicle', 20 => 'Driving without D/L',
            38 => 'Disregarding Traffic Sign', 42 => 'Disregarding Traffic Sign', 43 => 'Disregarding Traffic Sign', 44 => 'Disregarding Traffic Sign'];
        foreach (require database_path('data/violation_sections.php') as $section => $description) {
            DB::table('violations_type')->insertOrIgnore([
                'catalog_key' => 'CO-2000-01:'.$section, 'ordinance_code' => 'CO 2000-01',
                'section_number' => (string) $section, 'description' => $description,
                'form_group' => $groups[$section] ?? 'Others', 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // Form labels whose exact sections are not supplied remain explicitly unclassified.
        foreach (ViolationCatalog::COMMON_GROUPS as $label) {
            $key = 'legacy:'.hash('sha256', strtolower(trim($label)));
            DB::table('violations_type')->insertOrIgnore([
                'catalog_key' => $key, 'description' => $label, 'form_group' => $label,
                'active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep catalog entries referenced by issued tickets; never erase cited history.
        DB::table('violations_type')->where('catalog_key', 'like', 'CO-2000-01:%')
            ->whereNotIn('id', DB::table('violation_issued')->select('violations_type_id'))->delete();
    }
};
