<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Violation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()->isCashier()) {
            return redirect()->route('cashier.dashboard');
        }

        if ($request->user()->isSupervisor()) {
            $supervisor = $request->user();
            $teamQuery = $supervisor->enforcers()->where('role', User::ROLE_OFFICER);
            $teamAreas = (clone $teamQuery)->get(['area', 'additional_areas'])->map->assignedAreas();
            $coverageAreas = $teamAreas->flatten(1)->countBy()->sortDesc();
            $enforcerCount = $teamAreas->count();
            $page = min(max(1, $request->integer('page', 1)), max(1, (int) ceil($enforcerCount / 8)));

            return view('supervisor.index', [
                'supervisor' => $supervisor,
                'enforcers' => (clone $teamQuery)->orderBy('firstName')->orderBy('lastName')->orderBy('id')
                    ->paginate(8, ['*'], 'page', $page)->withQueryString(),
                'enforcerCount' => $enforcerCount,
                'areaCount' => $coverageAreas->count(),
                'unassignedAreaCount' => $teamAreas->filter(fn ($areas) => $areas === [])->count(),
                'coverageAreas' => $coverageAreas->take(4),
                'dashboardNow' => now('Asia/Manila'),
            ]);
        }

        $dashboardNow = now('Asia/Manila');
        $storageTimezone = config('app.timezone');
        $todayStart = $dashboardNow->copy()->startOfDay()->setTimezone($storageTimezone);
        $tomorrowStart = $dashboardNow->copy()->addDay()->startOfDay()->setTimezone($storageTimezone);
        $violationQuery = Violation::query();
        $todayTickets = (clone $violationQuery)->where('created_at', '>=', $todayStart)->where('created_at', '<', $tomorrowStart);
        $activityDays = collect(range(6, 0))->map(function (int $daysAgo) use ($dashboardNow, $storageTimezone): array {
            $date = $dashboardNow->copy()->subDays($daysAgo);
            $start = $date->copy()->startOfDay()->setTimezone($storageTimezone);
            $end = $date->copy()->addDay()->startOfDay()->setTimezone($storageTimezone);

            return [
                'label' => $date->format('D j'),
                'date' => $date->format('M j, Y'),
                'violations' => Violation::query()->where('created_at', '>=', $start)->where('created_at', '<', $end)->count(),
                'users' => User::query()->where('created_at', '>=', $start)->where('created_at', '<', $end)->count(),
            ];
        });

        return view('dashboard.index', [
            'dashboardNow' => $dashboardNow,
            'totalUsers' => User::count(),
            'todayTickets' => (clone $todayTickets)->count(),
            'todayAssessedFines' => (clone $todayTickets)->sum('fine_amount'),
            'outstandingTickets' => (clone $violationQuery)->where(fn ($query) => $query->where('status', '!=', 'paid')->orWhereNull('status'))->count(),
            'recentViolations' => (clone $violationQuery)->latest()->orderByDesc('id')->take(5)->get(),
            'activityDays' => $activityDays,
        ]);
    }
}
