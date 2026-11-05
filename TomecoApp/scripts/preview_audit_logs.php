<?php

use App\Http\Controllers\AuditLogController;
use App\Models\AuditLog;
use App\Models\EnforcerAttendance;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Local browser fixture only: every request uses fictional data in SQLite memory.
// Run: php -S 127.0.0.1:8766 -t public scripts/preview_audit_logs.php
if (PHP_SAPI !== 'cli-server') {
    exit('Use the local PHP development server to open this preview.');
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('~^/(css|js|images)/~', $path)) {
    return false;
}

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config([
    'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
    'database.connections.sqlite.url' => null, 'cache.default' => 'array',
    'session.driver' => 'array', 'broadcasting.default' => 'null',
]);
DB::purge('sqlite');
Artisan::call('migrate', ['--force' => true]);
$admin = User::factory()->create([
    'firstName' => 'Preview', 'lastName' => 'Administrator', 'role' => 'admin',
]);
Auth::setUser($admin);
AuditLog::query()->delete();
foreach (range(1, 24) as $index) {
    $role = ['officer', 'supervisor', 'admin'][($index - 1) % 3];
    $event = ['created', 'updated', 'deleted'][intdiv($index - 1, 3) % 3];
    AuditLog::create([
        'actor_name' => 'Sample '.($role === 'officer' ? 'Enforcer' : ucfirst($role)),
        'actor_role' => $role, 'event' => $event,
        'auditable_type' => EnforcerAttendance::class,
        'auditable_id' => (string) $index, 'subject_label' => 'Sample attendance #'.$index,
        'changes' => [
            'session' => ['old' => $event === 'created' ? null : 'morning', 'new' => $event === 'deleted' ? null : 'whole_day'],
            'time_in' => ['old' => $event === 'created' ? null : '2026-10-28 08:00:00', 'new' => $event === 'deleted' ? null : '2026-10-28 08:15:00'],
            'time_out' => ['old' => null, 'new' => null],
            'time_in_latitude' => ['old' => $event === 'created' ? null : 11.238608, 'new' => $event === 'deleted' ? null : 11.238608],
        ],
    ]);
}
$request = Request::capture();
$request->setUserResolver(fn () => $admin);
$app->instance('request', $request);
echo $app->make(AuditLogController::class)->index($request)->render();
