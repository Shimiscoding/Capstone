<?php

namespace App\Http\Controllers;

use App\Models\OfficerLocation;
use App\Models\OfficerLocationHistory;
use App\Models\User;
use App\Services\OfficerLocationService;
use App\Support\OfficerLocationData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LocationMonitoringController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeViewer($request->user());

        return view('location-monitoring.index', ['googleMapsKey' => config('services.google_maps.browser_key'), 'mapCenter' => config('officer-location.map_center'), 'thresholds' => ['active' => config('officer-location.active_seconds'), 'offline' => config('officer-location.offline_seconds')]]);
    }

    public function officers(Request $request, OfficerLocationService $service): JsonResponse
    {
        $viewer = $request->user();
        $this->authorizeViewer($viewer);
        $officers = $this->authorizedLocations($viewer)->get()->map(fn ($location) => OfficerLocationData::make($location, $service))->values();
        $statuses = $officers->countBy('status');

        return response()->json([
            'officers' => $officers,
            'teams' => $officers->whereNotNull('team_id')->unique('team_id')->map(fn ($item) => ['id' => $item['team_id'], 'name' => $item['team']])->values(),
            'areas' => $officers->pluck('area')->filter()->unique()->sort()->values(),
            'summary' => ['on_duty' => $officers->where('on_duty', true)->count(), 'offline' => ($statuses['offline'] ?? 0) + ($statuses['delayed'] ?? 0), 'active_teams' => $officers->where('on_duty', true)->pluck('team_id')->filter()->unique()->count(), 'alerts' => ($statuses['offline'] ?? 0) + $officers->where('low_accuracy', true)->count()],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function history(Request $request, User $officer): JsonResponse
    {
        $this->authorizeOfficer($request->user(), $officer);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $points = OfficerLocationHistory::where('user_id', $officer->id)->whereDate('recorded_at', $data['date'])->orderBy('recorded_at')->get(['latitude', 'longitude', 'accuracy', 'speed', 'heading', 'recorded_at']);

        return response()->json(['officer' => ['id' => $officer->id, 'name' => $officer->fullName], 'points' => $points]);
    }

    private function authorizedLocations(User $viewer): Builder
    {
        return OfficerLocation::query()->whereHas('officer', fn (Builder $query) => $query->where('role', User::ROLE_OFFICER)->when($viewer->isSupervisor(), fn (Builder $query) => $query->where('supervisor_id', $viewer->id)))
            ->with(['officer.supervisor', 'officer.enforcerAttendances' => fn ($query) => $query->whereNotNull('time_in')->latest('attendance_date')->limit(1)]);
    }

    private function authorizeViewer(User $viewer): void
    {
        abort_unless($viewer->isAdmin() || $viewer->isSupervisor(), 403);
    }

    private function authorizeOfficer(User $viewer, User $officer): void
    {
        $this->authorizeViewer($viewer);
        abort_unless($officer->isOfficer() && ($viewer->isAdmin() || $officer->supervisor_id === $viewer->id), 404);
    }
}
