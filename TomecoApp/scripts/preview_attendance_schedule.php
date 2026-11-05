<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ViewErrorBag;

// Fictional, unsaved staff only. Server permissions/mutations are tested in PHPUnit.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$staff = new User([
    'firstName' => 'Sample', 'lastName' => 'Enforcer', 'role' => User::ROLE_OFFICER,
    'username' => 'preview.enforcer', 'email' => 'preview@example.test',
    'attendance_restrictions_enabled' => true,
    'attendance_time_in_start' => '07:00', 'attendance_time_in_end' => '09:00',
    'attendance_time_out_start' => '16:00', 'attendance_time_out_end' => '18:00',
    'attendance_working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
]);
$staff->id = 2147000001;
$staff->enforcer_attendances_count = 0;
$staffMembers = new LengthAwarePaginator(collect([$staff]), 1, 10);
$source = preg_replace('/@extends\([^\r\n]+\)/', '', file_get_contents(resource_path('views/supervisor/attendance.blade.php')), 1);
$html = Blade::render(
    '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Daily attendance schedule preview</title><link rel="stylesheet" href="../css/dashboard.css"></head><body><main style="padding:24px;max-width:1400px;margin:auto">'.$source.'@yield("content")</main>@stack("scripts")</body></html>',
    ['staffMembers' => $staffMembers, 'attendanceType' => 'enforcer', 'search' => '', 'sort' => 'name_asc', 'errors' => new ViewErrorBag],
);
file_put_contents(public_path('demos/attendance-schedule-preview.html'), $html);
echo 'Fictional preview: public/demos/attendance-schedule-preview.html'.PHP_EOL;
