<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->reassign(false);
    }

    public function down(): void
    {
        $this->reassign(true);
    }

    private function reassign(bool $restore): void
    {
        DB::transaction(function () use ($restore): void {
            $options = DB::table('violation_issued')->pluck('id', 'label');
            $descriptions = require database_path('data/violation_sections.php');

            // An entire section must fit the printed checkbox. Section 5 also
            // covers non-headlight accessories; section 21 is failure to carry
            // a license, whereas section 20 is driving without securing one.
            foreach ([5 => 'Defective Head Light', 21 => 'Driving without D/L'] as $section => $previousGroup) {
                if (! isset($options['Others'], $options[$previousGroup])) {
                    throw new RuntimeException('Missing form options for section '.$section);
                }

                // Correct only the supplied entries with the previous mapping.
                // Preserve separately maintained descriptions and custom mappings.
                DB::table('violations_type')->where('section_id', $section)
                    ->where('description', $descriptions[$section])
                    ->where('violation_issued_id', $options[$restore ? 'Others' : $previousGroup])
                    ->update(['violation_issued_id' => $options[$restore ? $previousGroup : 'Others']]);
            }
            // Historical citations own their selected group and captured text.
            // Regrouping the catalog must never rewrite ticket_violation_items.
        });
    }
};
