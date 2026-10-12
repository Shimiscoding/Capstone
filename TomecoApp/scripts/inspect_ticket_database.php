<?php

use App\Services\ViolationCatalog;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Read-only inspection; --backup additionally writes an encrypted local snapshot.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$table = Schema::hasTable('Ticket_issued') ? 'Ticket_issued' : 'violations';
$db = DB::connection();
$snapshot = [
    'table' => $table,
    'columns' => Schema::getColumns($table),
    'indexes' => Schema::getIndexes($table),
    'foreign_keys' => Schema::getForeignKeys($table),
    'count' => $db->table($table)->count(),
];
if ($table === 'violations') {
    $snapshot['labels'] = $db->table($table)->select('violation_type')->selectRaw('COUNT(*) as total')->groupBy('violation_type')->get()->all();
}
echo json_encode(in_array('--summary', $argv, true)
    ? ['table' => $table, 'count' => $snapshot['count'], 'columns' => array_column($snapshot['columns'], 'name')]
    : $snapshot, JSON_PRETTY_PRINT).PHP_EOL;
if (in_array('--backup', $argv, true)) {
    $snapshot['rows'] = $db->table($table)->orderBy('id')->get()->all();
    foreach (['violation_issued', 'violations_type', 'ticket_violation_items'] as $relatedTable) {
        if (Schema::hasTable($relatedTable)) {
            $snapshot['related_tables'][$relatedTable] = ['columns' => Schema::getColumns($relatedTable),
                'indexes' => Schema::getIndexes($relatedTable), 'foreign_keys' => Schema::getForeignKeys($relatedTable),
                'rows' => $db->table($relatedTable)->orderBy($relatedTable === 'violations_type' && Schema::hasColumn($relatedTable, 'section_id') ? 'section_id' : 'id')->get()->all()];
        }
    }
    $snapshot['migrations'] = $db->table('migrations')->get()->all();
    $path = storage_path('app/private/ticket-backups');
    if (! is_dir($path)) {
        mkdir($path, 0700, true);
    }
    $file = $path.'/'.date('Ymd-His').'-'.$table.'.encrypted';
    if (file_put_contents($file, Crypt::encryptString(json_encode($snapshot, JSON_THROW_ON_ERROR))) === false) {
        throw new RuntimeException('Could not save ticket backup.');
    }
    echo 'Encrypted backup: '.$file.PHP_EOL;
}
if (in_array('--verify', $argv, true)) {
    $backups = glob(storage_path('app/private/ticket-backups/*.encrypted'));
    rsort($backups);
    if ($backups === []) {
        throw new RuntimeException('No pre-migration backup found.');
    }
    $before = json_decode(Crypt::decryptString(file_get_contents($backups[0])), true, flags: JSON_THROW_ON_ERROR);
    if ($table !== 'Ticket_issued' || $snapshot['count'] !== $before['count']) {
        throw new RuntimeException('Ticket count does not match the backup.');
    }
    foreach ($before['rows'] as $row) {
        $current = (array) $db->table($table)->find($row['id']);
        foreach ($row as $column => $value) {
            if ($column === 'violation_type') {
                continue;
            }
            if (! array_key_exists($column, $current) || $current[$column] !== $value) {
                throw new RuntimeException('A preserved ticket column differs from its backup: '.$column);
            }
        }
        if (isset($row['violation_type'])) {
            $expected = count(array_unique(array_map(fn ($label) => strtolower($label), ViolationCatalog::legacyLabels($row['violation_type']))));
            $itemsTable = Schema::hasTable('ticket_violation_items') ? 'ticket_violation_items' : 'violation_issued';
            if ($db->table($itemsTable)->where('ticket_issued_id', $row['id'])->count() !== $expected) {
                throw new RuntimeException('Issued offense count does not match its original checkbox list.');
            }
        }
    }
    foreach ($before['related_tables'] ?? [] as $name => $related) {
        $target = $name === 'violation_issued' && in_array('ticket_issued_id', array_column($related['columns'], 'name'), true)
            && Schema::hasTable('ticket_violation_items') ? 'ticket_violation_items' : $name;
        foreach ($related['rows'] as $row) {
            $minimal = Schema::hasColumn('violations_type', 'section_id');
            if ($minimal && $name === 'violations_type' && isset($row['section_number'])) {
                $current = (array) $db->table($target)->where('section_id', (int) $row['section_number'])->first();
                if (($current['description'] ?? null) !== $row['description']) {
                    throw new RuntimeException('A section description differs from its backup.');
                }

                continue;
            }
            if ($minimal && $name === 'violations_type' && ! isset($row['section_id'])) {
                continue; // Legacy placeholders are represented by null section links and preserved snapshots.
            }
            $current = (array) $db->table($target)->where($name === 'violations_type' && $minimal ? 'section_id' : 'id', $row['section_id'] ?? $row['id'])->first();
            if ($minimal && $target === 'ticket_violation_items' && isset($row['violations_type_id'])
                && ! in_array('section_id', array_column($before['related_tables']['violations_type']['columns'], 'name'), true)) {
                $oldTypes = collect($before['related_tables']['violations_type']['rows'])->keyBy('id');
                $oldSection = $oldTypes[$row['violations_type_id']]['section_number'];
                $row['violations_type_id'] = $oldSection !== null ? (int) $oldSection : null;
                if (array_key_exists('section_key', $row)) {
                    $row['section_key'] = $row['violations_type_id'] ?? 0;
                }
            }
            foreach ($row as $column => $value) {
                if ($name === 'violation_issued' && $column === 'active' && ! Schema::hasColumn($target, 'active')) {
                    continue;
                }
                if ($name === 'violations_type' && $column === 'form_group' && ! Schema::hasColumn($target, $column)) {
                    continue;
                }
                if (! array_key_exists($column, $current) || $current[$column] !== $value) {
                    throw new RuntimeException('A related citation/catalog value differs from its backup: '.$name.'.'.$column);
                }
            }
        }
    }
    $itemsTable = Schema::hasTable('ticket_violation_items') ? 'ticket_violation_items' : 'violation_issued';
    echo json_encode(['verified' => true, 'tickets' => $snapshot['count'],
        'issued_offenses' => $db->table($itemsTable)->count(),
        'form_options' => Schema::hasTable('ticket_violation_items') ? $db->table('violation_issued')->count() : null,
        'catalog_sections' => Schema::hasColumn('violations_type', 'section_id') ? $db->table('violations_type')->count() : $db->table('violations_type')->whereNotNull('section_number')->count(),
        'preserved' => 'IDs, officer links, timestamps, fines, status, and every encrypted value match the backup.'], JSON_PRETTY_PRINT).PHP_EOL;
}
