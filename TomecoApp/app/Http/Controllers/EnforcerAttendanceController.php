<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\EnforcerDuty;
use App\Support\StaffDirectory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnforcerAttendanceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->isSupervisor(), 403);

        $directory = new StaffDirectory($request, defaultSort: 'name_asc');
        $search = $directory->search;

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

        return view('supervisor.enforcer-attendance', [
            'enforcers' => $directory->order($enforcers)->paginate(20)->withQueryString(),
            'search' => $search,
            'sort' => $directory->sort,
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

    public function timeIn(Request $request, User $user, EnforcerDuty $duty): RedirectResponse
    {
        $this->ensureAssignedEnforcer($request, $user);
        try {
            $duty->timeIn($user, []);
        } catch (ValidationException $exception) {
            return back()->with('enforcer_attendance_error', $exception->validator->errors()->first());
        }

        return back()->with('enforcer_attendance_success', 'Time in recorded for '.$user->fullName.'.');
    }

    public function timeOut(Request $request, User $user, EnforcerDuty $duty): RedirectResponse
    {
        $this->ensureAssignedEnforcer($request, $user);

        try {
            $duty->timeOut($user, recordedBySupervisor: $request->user()->id);
        } catch (ValidationException $exception) {
            return back()->with('enforcer_attendance_error', $exception->getMessage());
        }

        return back()->with('enforcer_attendance_success', 'Time out recorded for '.$user->fullName.'.');
    }

    private function ensureAssignedEnforcer(Request $request, User $user): void
    {
        abort_unless($request->user()->isSupervisor(), 403);
        abort_unless($user->role === User::ROLE_OFFICER && $user->supervisor_id === $request->user()->id, 404);
    }
}
