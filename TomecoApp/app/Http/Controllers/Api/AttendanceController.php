<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnforcerAttendance;
use App\Models\User;
use App\Services\AttendanceRules;
use App\Services\EnforcerDuty;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function timeIn(Request $request, EnforcerDuty $duty): JsonResponse
    {
        $officer = $this->officer($request);
        $coordinates = $this->coordinates($request);

        $attendance = $duty->timeIn($officer, [
            'time_in_latitude' => $coordinates['latitude'] ?? null,
            'time_in_longitude' => $coordinates['longitude'] ?? null,
        ]);

        return response()->json([
            'message' => 'Time in recorded successfully.',
            'attendance' => $this->attendanceData($attendance),
        ], 201);
    }

    public function timeOut(Request $request, EnforcerDuty $duty): JsonResponse
    {
        $officer = $this->officer($request);
        $coordinates = $this->coordinates($request);

        try {
            $attendance = $duty->timeOut($officer, $coordinates);
        } catch (ValidationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Time out recorded successfully.',
            'attendance' => $this->attendanceData($attendance),
        ]);
    }

    public function getStatus(Request $request, AttendanceRules $rules): JsonResponse
    {
        $officer = $this->officer($request);
        $date = $rules->date();
        $attendance = $officer->enforcerAttendances()->forDutyDate($date)->first();
        $sessionData = [
            'status' => match (true) {
                $attendance?->time_out !== null => 'timed_out',
                $attendance?->time_in !== null => 'timed_in',
                default => 'not_timed_in',
            },
            'attendance' => $attendance ? $this->attendanceData($attendance) : null,
        ];

        return response()->json([
            'session' => EnforcerAttendance::WHOLE_DAY,
            'date' => $date,
            'schedule' => $rules->schedule($officer),
            'sessions' => [EnforcerAttendance::WHOLE_DAY => $sessionData],
            ...$sessionData,
        ]);
    }

    private function officer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isOfficer(), 403);
        abort_if($user->supervisor_id === null, 422, 'You must be assigned to a supervisor before recording attendance.');

        return $user;
    }

    private function attendanceData(EnforcerAttendance $attendance): array
    {
        return [
            'session' => $attendance->session,
            'date' => $attendance->attendance_date->toDateString(),
            'time_in' => $attendance->time_in?->toIso8601String(),
            'time_out' => $attendance->time_out?->toIso8601String(),
            'time_in_location' => $this->locationData($attendance->time_in_latitude, $attendance->time_in_longitude),
            'time_out_location' => $this->locationData($attendance->time_out_latitude, $attendance->time_out_longitude),
        ];
    }

    private function coordinates(Request $request): array
    {
        return $request->validate([
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ]);
    }

    private function locationData(?float $latitude, ?float $longitude): ?array
    {
        return $latitude !== null && $longitude !== null
            ? ['latitude' => $latitude, 'longitude' => $longitude]
            : null;
    }
}
