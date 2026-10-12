<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$files = glob(storage_path('app/private/ticket-backups/*.encrypted'));
rsort($files);
$backup = json_decode(Crypt::decryptString(file_get_contents($files[0])), true, flags: JSON_THROW_ON_ERROR);
foreach (['Ticket_issued' => $backup['rows'], 'ticket_violation_items' => $backup['related_tables']['ticket_violation_items']['rows'],
    'violation_issued' => $backup['related_tables']['violation_issued']['rows']] as $table => $rows) {
    $current = json_decode(DB::table($table)->orderBy('id')->get()->toJson(), true);
    if ($current !== $rows) {
        throw new RuntimeException('Preserved rows differ: '.$table);
    }
}
$groups = ['Driving without D/L' => [20, 21], 'Unregistered Vehicle' => [19], 'Illegal parking' => [7, 50],
    'Disregarding Traffic Sign' => [38, 42, 43, 44], 'Obstruction' => [16, 48, 49, 71], 'Defective Head Light' => [5]];
$options = DB::table('violation_issued')->pluck('id', 'label');
foreach ($backup['related_tables']['violations_type']['rows'] as $row) {
    $expected = 'Others';
    foreach ($groups as $label => $sections) {
        if (in_array((int) $row['section_id'], $sections, true)) {
            $expected = $label;
        }
    }
    $current = DB::table('violations_type')->where('section_id', $row['section_id'])->first();
    if ($current->description !== $row['description'] || (int) $current->violation_issued_id !== (int) $options[$expected]) {
        throw new RuntimeException('Incorrect section grouping or changed description.');
    }
}
echo json_encode(['verified' => true, 'sections' => DB::table('violations_type')->count(),
    'preserved_tickets' => count($backup['rows']), 'preserved_items' => count($backup['related_tables']['ticket_violation_items']['rows'])], JSON_PRETTY_PRINT).PHP_EOL;
