<?php

namespace App\Http\Controllers;

use App\Models\EnforcerAttendance;
use App\Models\SupervisorAttendance;
use App\Models\User;
use App\Notifications\AttendanceTimeOutUpdated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AdminAttendanceController extends Controller
{
    public function updateRestrictions(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin($request);
        $request->merge(['attendance_restrictions_enabled' => $request->boolean('attendance_restrictions_enabled')]);
        $attributes = $request->validate([
            'attendance_restrictions_enabled' => ['boolean'],
            'attendance_time_in_start' => ['required', 'date_format:H:i'],
            'attendance_time_in_end' => ['required', 'date_format:H:i', 'after:attendance_time_in_start'],
            'attendance_time_out_start' => ['required', 'date_format:H:i'],
            'attendance_time_out_end' => ['required', 'date_format:H:i', 'after:attendance_time_out_start'],
            'attendance_working_days' => ['required', 'array', 'min:1'],
            'attendance_working_days.*' => ['required', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
        ]);
        $user->update($attributes);

        return back()->with('attendance_success', 'Attendance restrictions updated for '.$user->fullName.'.');
    }

    public function updateSupervisorTimeOut(
        Request $request,
        SupervisorAttendance $attendance,
    ): RedirectResponse {
        $this->ensureAdmin($request);
        $this->updateTimeOut($request, $attendance);

        return back()->with('attendance_success', 'Supervisor attendance updated successfully.');
    }

    public function updateEnforcerTimeOut(
        Request $request,
        EnforcerAttendance $attendance,
    ): RedirectResponse {
        $this->ensureAdmin($request);
        $this->updateTimeOut($request, $attendance);

        return back()->with('attendance_success', 'Enforcer attendance updated successfully.');
    }

    private function updateTimeOut(
        Request $request,
        SupervisorAttendance|EnforcerAttendance $attendance,
    ): void {
        $attributes = $request->validate([
            'time_in' => ['required', 'date_format:H:i'],
            'time_out' => ['required', 'date_format:H:i'],
        ]);

        $timeIn = Carbon::parse(
            $attendance->attendance_date->format('Y-m-d').' '.$attributes['time_in'],
            config('app.timezone'),
        );
        $timeOut = Carbon::parse(
            $attendance->attendance_date->format('Y-m-d').' '.$attributes['time_out'],
            config('app.timezone'),
        );

        abort_if($timeOut->lte($timeIn), 422, 'Time Out must be after Time In and cannot be the same time.');

        $attendance->update(['time_in' => $timeIn, 'time_out' => $timeOut]);
        $staff = $attendance->user;
        $notification = new AttendanceTimeOutUpdated(
            $staff->fullName,
            $attendance->attendance_date,
            $timeIn,
            $timeOut,
            $request->user()->fullName,
        );

        $staff->notify($notification);
        $request->user()->notify(new AttendanceTimeOutUpdated(
            $staff->fullName,
            $attendance->attendance_date,
            $timeIn,
            $timeOut,
            $request->user()->fullName,
        ));
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403);
    }
}
