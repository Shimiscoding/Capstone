<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (in_array('--raise-packet-limit', $argv, true)) {
    DB::statement('SET GLOBAL max_allowed_packet = 134217728');
    DB::purge();
}
echo json_encode([
    'database' => DB::selectOne('SELECT DATABASE() AS db, @@port AS port, @@version AS version, @@max_allowed_packet AS packet_bytes'),
    'php' => ['sapi' => PHP_SAPI, 'ini' => php_ini_loaded_file(), 'upload_max_filesize' => ini_get('upload_max_filesize'), 'post_max_size' => ini_get('post_max_size'), 'max_file_uploads' => ini_get('max_file_uploads'), 'memory_limit' => ini_get('memory_limit')],
], JSON_PRETTY_PRINT).PHP_EOL;
