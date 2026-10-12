<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->assign([
            'Driving without D/L' => [20, 21],
            'Unregistered Vehicle' => [19],
            'Illegal parking' => [7, 50],
            'Disregarding Traffic Sign' => [38, 42, 43, 44],
            'Obstruction' => [16, 48, 49, 71],
            'Defective Head Light' => [5],
        ]);
    }

    public function down(): void
    {
        $this->assign([
            'Driving without D/L' => [20],
            'Unregistered Vehicle' => [19],
            'Illegal parking' => [7],
            'Disregarding Traffic Sign' => [38, 42, 43, 44],
            'Obstruction' => [16],
        ]);
    }

    private function assign(array $groups): void
    {
        DB::transaction(function () use ($groups): void {
            $options = DB::table('violation_issued')->pluck('id', 'label');
            foreach ([...array_keys($groups), 'Others'] as $label) {
                if (! isset($options[$label])) {
                    throw new RuntimeException('Missing form option: '.$label);
                }
            }
            // Update the catalog only. Historical ticket selections and snapshots remain intact.
            DB::table('violations_type')->whereBetween('section_id', [1, 73])
                ->update(['violation_issued_id' => $options['Others']]);
            foreach ($groups as $label => $sections) {
                DB::table('violations_type')->whereIn('section_id', $sections)
                    ->update(['violation_issued_id' => $options[$label]]);
            }
        });
    }
};
