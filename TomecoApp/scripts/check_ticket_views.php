<?php

// Read-only smoke check against the configured database; no login/session is saved.
use App\Http\Controllers\DashboardController;
use App\Models\User;
use App\Models\Violation;
use App\Services\Settings;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$admin = User::query()->where('role', User::ROLE_ADMIN)->firstOrFail();
Auth::setUser($admin);
$request = Request::create(config('app.url').'/dashboard/analytics', 'GET', ['period' => 'yearly']);
$request->setUserResolver(fn () => $admin);
$request->setLaravelSession($app->make('session')->driver());
$app->instance('request', $request);
$controller = $app->make(DashboardController::class);
$report = $controller->analytics($request, $app->make(Settings::class));
$result = ['analytics_html_bytes' => strlen($report->render()),
    'record_list_html_bytes' => strlen($controller->violationRecords($request)->render())];
$ticket = Violation::query()->first();
if ($ticket) {
    $result['ticket_html_bytes'] = strlen($controller->violationRecord($ticket)->render());
    $result['motorist_history_html_bytes'] = strlen($controller->motoristRecord($ticket)->render());
}
echo json_encode(['verified' => true, 'rendered' => $result], JSON_PRETTY_PRINT).PHP_EOL;
