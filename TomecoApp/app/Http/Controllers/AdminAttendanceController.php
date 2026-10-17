<?php

namespace App\Http\Controllers;

use App\Models\EnforcerAttendance;
use App\Models\SupervisorAttendance;
use App\Models\User;
use App\Notifications\AttendanceTimeOutUpdated;
use App\Services\OfficerLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        if ($user->isOfficer()) {
            $sessionRules = [
                'attendance_time_in_start' => ['required', 'date_format:H:i', 'before:12:00'],
                'attendance_time_in_end' => ['required', 'date_format:H:i', 'after:attendance_time_in_start', 'before:12:00'],
                'attendance_time_out_start' => ['required', 'date_format:H:i', 'after:attendance_time_in_end'],
                'attendance_time_out_end' => ['required', 'date_format:H:i', 'after:attendance_time_out_start'],
                'attendance_afternoon_time_in_start' => ['required', 'date_format:H:i', 'after_or_equal:12:00', 'after:attendance_time_out_end'],
                'attendance_afternoon_time_in_end' => ['required', 'date_format:H:i', 'after:attendance_afternoon_time_in_start'],
                'attendance_afternoon_time_out_start' => ['required', 'date_format:H:i', 'after:attendance_afternoon_time_in_end'],
                'attendance_afternoon_time_out_end' => ['required', 'date_format:H:i', 'after:attendance_afternoon_time_out_start'],
            ];
            $attributes = array_merge($attributes, $request->validate($sessionRules));
        }
        $user->update($attributes);

        return back()->with('attendance_success', 'Attendance restrictions updated for '.$user->fullName.'.');
    }

    public function updateSupervisorTimeOut(
        Request $request,
        SupervisorAttendance $attendance,
    ): RedirectResponse|JsonResponse {
        $this->ensureAdmin($request);
        $this->updateTimeOut($request, $attendance);

        return $this->attendanceResponse($request, 'Supervisor attendance updated successfully.');
    }

    public function updateEnforcerTimeOut(
        Request $request,
        EnforcerAttendance $attendance,
    ): RedirectResponse|JsonResponse {
        $this->ensureAdmin($request);
        $this->updateTimeOut($request, $attendance);

        return $this->attendanceResponse($request, 'Enforcer attendance updated successfully.');
    }

    public function destroySupervisorAttendance(Request $request, SupervisorAttendance $attendance): RedirectResponse|JsonResponse
    {
        $this->ensureAdmin($request);
        $staff = $attendance->user;
        $attendance->delete();

        return $this->attendanceResponse($request, 'Attendance record deleted.', $this->historyUrl($request, $staff, false));
    }

    public function destroyEnforcerAttendance(Request $request, EnforcerAttendance $attendance): RedirectResponse|JsonResponse
    {
        $this->ensureAdmin($request);
        $staff = $attendance->user;
        DB::transaction(function () use ($attendance): void {
            $record = EnforcerAttendance::query()->lockForUpdate()->findOrFail($attendance->id);
            // Model deletion events retain the record in the existing audit trail.
            $record->delete();
        });
        if (! $staff->isOnDuty()) {
            app(OfficerLocationService::class)->stopSharing($staff);
        }

        return $this->attendanceResponse($request, 'Attendance record deleted.', $this->historyUrl($request, $staff, true));
    }

    private function historyUrl(Request $request, User $staff, bool $enforcer): string
    {
        $count = $enforcer ? $staff->enforcerAttendances()->count() : $staff->supervisorAttendances()->count();
        $page = max(1, min($request->integer('page', 1), (int) ceil($count / 20)));

        return route($enforcer ? 'dashboard.attendance.enforcers.show' : 'dashboard.attendance.supervisor', ['user' => $staff, 'page' => $page]);
    }

    private function attendanceResponse(Request $request, string $message, ?string $redirect = null): RedirectResponse|JsonResponse
    {
        $request->session()->flash('attendance_success', $message);

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'redirect' => $redirect])
            : ($redirect ? redirect($redirect) : back());
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
        if ($attendance instanceof EnforcerAttendance && ! $attendance->user->isOnDuty()) {
            app(OfficerLocationService::class)->stopSharing($attendance->user);
        }
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
