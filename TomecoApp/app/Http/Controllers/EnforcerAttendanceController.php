<?php

namespace App\Http\Controllers;

use App\Models\EnforcerAttendance;
use App\Models\User;
use App\Services\AttendanceRules;
use App\Services\EnforcerDuty;
use App\Services\OfficerLocationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnforcerAttendanceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isSupervisor(), 403);

        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'name_asc');
        $sort = in_array($sort, ['latest', 'oldest', 'name_asc', 'name_desc'], true) ? $sort : 'name_asc';

        $enforcers = User::query()
            ->where('role', User::ROLE_OFFICER)
            ->where('supervisor_id', $request->user()->id)
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('firstName', 'like', '%'.$search.'%')
                        ->orWhere('lastName', 'like', '%'.$search.'%')
                        ->orWhere('fullName', 'like', '%'.$search.'%')
                        ->orWhere('username', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->withCount('enforcerAttendances')
            ->withMax('enforcerAttendances', 'time_in');

        match ($sort) {
            'latest' => $enforcers->latest(),
            'oldest' => $enforcers->oldest(),
            'name_desc' => $enforcers->orderByDesc('firstName')->orderByDesc('lastName'),
            default => $enforcers->orderBy('firstName')->orderBy('lastName'),
        };

        return view('supervisor.enforcer-attendance', [
            'enforcers' => $enforcers->paginate(20)->withQueryString(),
            'search' => $search,
            'sort' => $sort,
        ]);
    }

    public function show(Request $request, User $user): View
    {
        abort_unless(
            $user->role === User::ROLE_OFFICER
            && ($request->user()->isAdmin() || ($request->user()->isSupervisor() && $user->supervisor_id === $request->user()->id)),
            404,
        );

        return view('supervisor.enforcer-attendance-show', [
            'enforcer' => $user,
            'attendances' => $user->enforcerAttendances()
                ->with('supervisor')
                ->latest('attendance_date')->latest('time_in')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function timeIn(Request $request, User $user, AttendanceRules $rules): RedirectResponse
    {
        $this->ensureAssignedEnforcer($request, $user);
        if ($error = $rules->timeInError($user)) {
            return back()->with('enforcer_attendance_error', $error);
        }
        try {
            app(EnforcerDuty::class)->timeIn($user, $rules, ['supervisor_id' => $request->user()->id, 'time_in' => now()]);
        } catch (ValidationException $exception) {
            return back()->with('enforcer_attendance_error', $exception->validator->errors()->first());
        }

        return back()->with('enforcer_attendance_success', 'Time in recorded for '.$user->fullName.'.');
    }

    public function timeOut(Request $request, User $user, AttendanceRules $rules, OfficerLocationService $locations): RedirectResponse
    {
        $this->ensureAssignedEnforcer($request, $user);

        $attendance = $user->enforcerAttendances()->whereDate('attendance_date', $rules->date())->whereNotNull('time_in')->whereNull('time_out')->latest('time_in')->first()
            ?? EnforcerAttendance::where('user_id', $user->id)->whereDate('attendance_date', $rules->date())->where('session', $rules->session($user))->first();
        if ($error = $rules->timeOutError($user, session: $attendance?->session)) {
            return back()->with('enforcer_attendance_error', $error);
        }

        if (! $attendance?->time_in) {
            return back()->with('enforcer_attendance_error', $user->fullName.' must time in before timing out.');
        }
        if ($attendance->time_out) {
            return back()->with('enforcer_attendance_error', $user->fullName.' has already timed out today.');
        }
        $attendance->update(['supervisor_id' => $request->user()->id, 'time_out' => now()]);
        $locations->stopSharing($user);

        return back()->with('enforcer_attendance_success', 'Time out recorded for '.$user->fullName.'.');
    }

    private function ensureAssignedEnforcer(Request $request, User $user): void
    {
        abort_unless($request->user()->isSupervisor(), 403);
        abort_unless($user->role === User::ROLE_OFFICER && $user->supervisor_id === $request->user()->id, 404);
    }
}
