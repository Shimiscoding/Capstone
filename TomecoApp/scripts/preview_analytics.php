<?php

use App\Models\User;
use App\Models\Violation;
use App\Services\AnalyticsReport;
use App\Services\ViolationCatalog;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Fictional browser preview, isolated entirely in an in-memory SQLite database.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
    'database.connections.sqlite.url' => null, 'cache.default' => 'array', 'session.driver' => 'array',
    'broadcasting.default' => 'null']);
DB::purge('sqlite');
Artisan::call('migrate', ['--force' => true]);
Carbon::setTestNow(Carbon::parse('2026-10-24 12:00:00', 'Asia/Manila'));
$admin = User::factory()->create(['firstName' => 'Preview', 'lastName' => 'Administrator', 'role' => 'admin']);
Auth::setUser($admin);
$officers = collect(['Santos', 'Reyes', 'Cruz'])->map(fn ($name) => User::factory()->create(['firstName' => 'Sample', 'lastName' => $name, 'role' => 'officer']));
$locations = ['Justice Avenue', 'Real Street', 'Rizal Avenue', 'Veterans Avenue', 'P. Zamora Street'];
foreach (range(0, 159) as $index) {
    $ticket = Violation::create([
        'enforcer_id' => $officers[$index % 3]->id, 'first_name' => 'Fictional', 'last_name' => 'Motorist',
        'plate_number' => 'SAMPLE', 'vehicle_type' => ['Motorcycle', 'Car', 'Tricycle'][$index % 3],
        'violation_type' => ViolationCatalog::COMMON_GROUPS[$index % 12],
        'fine_amount' => [500, 1000, 1500][$index % 3], 'status' => $index % 4 ? 'paid' : 'unpaid',
        'location' => $locations[$index % 5],
    ]);
    $ticket->forceFill(['created_at' => now('Asia/Manila')->subDays($index % 75)->setTime(9 + $index % 3, $index % 60)->setTimezone('UTC')])->save();
}
foreach (['populated' => ['summary' => 'month'], 'chart-unavailable' => ['summary' => 'month'], 'empty' => ['summary' => 'month', 'vehicle' => 'No matching vehicle'],
    'invalid' => ['summary' => 'custom', 'date_from' => '2026-10-20', 'date_to' => '2026-10-01']] as $name => $query) {
    $request = Request::create('http://127.0.0.1:8000/dashboard/analytics', 'GET', $query);
    $app->instance('request', $request);
    $data = $app->make(AnalyticsReport::class)->build($request);
    $html = view('dashboard.analytics', $data)->render();
    if ($name === 'chart-unavailable') {
        $html = preg_replace('~<script src="https://cdn.jsdelivr.net/[^\"]+"></script>~', '', $html);
    }
    $html = preg_replace('~https?://[^/\"\s]+/(?=(?:css|js|images)/)~', '/', $html);
    $html = str_replace('<h1>Analytics Reports</h1>', '<h1>Analytics Reports</h1><p style="color:var(--accent);font-size:12px">Design preview · Fictional sample data</p>', $html);
    file_put_contents(public_path('demos/analytics-updated-'.$name.'.html'), $html);
}
echo 'Generated fictional analytics previews in public/demos; application records were not changed.'.PHP_EOL;
