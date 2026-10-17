<?php

use App\Models\EnforcerAttendance;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;

// Render fictional, unsaved models for browser QA. Never changes attendance data.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$admin = new User(['firstName' => 'Preview', 'lastName' => 'Admin', 'role' => User::ROLE_ADMIN]);
$admin->id = 2147000000;
Auth::setUser($admin);
$staff = new User(['firstName' => 'Sample', 'lastName' => 'Enforcer', 'username' => 'preview.enforcer', 'email' => 'preview@example.test', 'role' => User::ROLE_OFFICER]);
$staff->id = 2147000001;
$records = collect(range(0, 4))->map(function ($day) use ($staff) {
    $date = Carbon::parse('2026-10-15')->subDays($day);
    $record = new EnforcerAttendance([
        'user_id' => $staff->id, 'attendance_date' => $date, 'session' => $day === 1 ? 'afternoon' : 'morning',
        'time_in' => $date->copy()->setTime($day === 1 ? 13 : 8, 5),
        'time_out' => $day === 2 ? null : $date->copy()->setTime($day === 1 ? 17 : 12, 0),
        'time_in_latitude' => $day === 4 ? null : 11.24, 'time_in_longitude' => $day === 4 ? null : 125.0,
        'time_out_latitude' => $day === 0 ? 11.25 : null, 'time_out_longitude' => $day === 0 ? 125.01 : null,
    ]);
    $record->id = 2147000100 + $day;

    return $record;
});
$attendances = new LengthAwarePaginator($records, 5, 20);
$html = Blade::render(<<<'BLADE'
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Attendance history preview</title><link rel="stylesheet" href="../css/dashboard.css"></head><body><main style="padding:24px;max-width:1400px;margin:auto"><div class="page-head users-page-head"><div><p class="eyebrow">Attendance history · fictional preview</p><h1>Sample Enforcer</h1><p class="page-description">Complete Time In and Time Out timestamp history for this enforcer.</p></div><span class="user-count">5 records</span></div><x-attendance-history-table :staff="$staff" :attendances="$attendances" :enforcer="true" /></main>@stack('scripts')</body></html>
BLADE, compact('staff', 'attendances'));
// Relative assets work with either Apache's public root or Laravel serve.
$html = preg_replace('~src="[^"]*/js/attendance-history\.js([^\"]*)"~', 'src="../js/attendance-history.js$1"', $html);
file_put_contents(public_path('demos/attendance-history-preview.html'), $html);
echo 'Fictional preview: public/demos/attendance-history-preview.html'.PHP_EOL;
