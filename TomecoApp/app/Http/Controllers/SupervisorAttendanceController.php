<?php

namespace App\Http\Controllers;

use App\Models\SupervisorAttendance;
use App\Services\AttendanceRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupervisorAttendanceController extends Controller
{
    public function timeIn(Request $request, AttendanceRules $rules): RedirectResponse
    {
        abort_unless($request->user()->isSupervisor(), 403);

        if ($error = $rules->timeInError($request->user())) {
            return back()->with('attendance_error', $error);
        }

        $attendance = SupervisorAttendance::firstOrCreate(
            ['user_id' => $request->user()->id, 'attendance_date' => today()->toDateString()],
            ['time_in' => now()],
        );

        if ($attendance->time_in !== null && ! $attendance->wasRecentlyCreated) {
            return back()->with('attendance_error', 'You have already timed in today.');
        }

        return back()->with('attendance_success', 'Time in recorded successfully.');
    }

    public function timeOut(Request $request, AttendanceRules $rules): RedirectResponse
    {
        abort_unless($request->user()->isSupervisor(), 403);

        if ($error = $rules->timeOutError($request->user())) {
            return back()->with('attendance_error', $error);
        }

        $attendance = SupervisorAttendance::where('user_id', $request->user()->id)
            ->whereDate('attendance_date', today())
            ->first();

        if (! $attendance?->time_in) {
            return back()->with('attendance_error', 'You must time in before timing out.');
        }
        if ($attendance->time_out) {
            return back()->with('attendance_error', 'You have already timed out today.');
        }
        $attendance->update(['time_out' => now()]);

        return back()->with('attendance_success', 'Time out recorded successfully.');
    }
}
