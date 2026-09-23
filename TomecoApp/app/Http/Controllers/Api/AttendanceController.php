<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnforcerAttendance;
use App\Models\User;
use App\Services\AttendanceRules;
use App\Services\OfficerLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function timeIn(Request $request, AttendanceRules $rules): JsonResponse
    {
        $officer = $this->officer($request);
        $coordinates = $this->coordinates($request);

        if ($error = $rules->timeInError($officer)) {
            return response()->json(['message' => $error], 422);
        }

        $attendance = EnforcerAttendance::firstOrCreate(
            ['user_id' => $officer->id, 'attendance_date' => today()->toDateString()],
            [
                'supervisor_id' => $officer->supervisor_id,
                'time_in' => now(),
                'time_in_latitude' => $coordinates['latitude'] ?? null,
                'time_in_longitude' => $coordinates['longitude'] ?? null,
            ],
        );

        if (! $attendance->wasRecentlyCreated) {
            return response()->json(['message' => 'You have already timed in today.'], 422);
        }

        return response()->json([
            'message' => 'Time in recorded successfully.',
            'attendance' => $this->attendanceData($attendance),
        ], 201);
    }

    public function timeOut(Request $request, AttendanceRules $rules, OfficerLocationService $locations): JsonResponse
    {
        $officer = $this->officer($request);
        $coordinates = $this->coordinates($request);

        if ($error = $rules->timeOutError($officer)) {
            return response()->json(['message' => $error], 422);
        }

        $attendance = $this->todayAttendance($officer);

        if (! $attendance?->time_in) {
            return response()->json(['message' => 'You must time in before timing out.'], 422);
        }

        if ($attendance->time_out) {
            return response()->json(['message' => 'You have already timed out today.'], 422);
        }

        $lastLocation = $officer->currentLocation;
        $attendance->update([
            'time_out' => now(),
            'time_out_latitude' => $coordinates['latitude'] ?? $lastLocation?->latitude,
            'time_out_longitude' => $coordinates['longitude'] ?? $lastLocation?->longitude,
        ]);
        $locations->stopSharing($officer);

        return response()->json([
            'message' => 'Time out recorded successfully.',
            'attendance' => $this->attendanceData($attendance->fresh()),
        ]);
    }

    public function getStatus(Request $request): JsonResponse
    {
        $officer = $this->officer($request);
        $attendance = $this->todayAttendance($officer);

        return response()->json([
            'status' => match (true) {
                $attendance?->time_out !== null => 'timed_out',
                $attendance?->time_in !== null => 'timed_in',
                default => 'not_timed_in',
            },
            'attendance' => $attendance ? $this->attendanceData($attendance) : null,
        ]);
    }

    private function officer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isOfficer(), 403);
        abort_if($user->supervisor_id === null, 422, 'You must be assigned to a supervisor before recording attendance.');

        return $user;
    }

    private function todayAttendance(User $officer): ?EnforcerAttendance
    {
        return EnforcerAttendance::where('user_id', $officer->id)
            ->whereDate('attendance_date', today())
            ->first();
    }

    private function attendanceData(EnforcerAttendance $attendance): array
    {
        return [
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
