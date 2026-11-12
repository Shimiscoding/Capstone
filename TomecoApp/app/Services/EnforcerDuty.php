<?php

namespace App\Services;

use App\Models\EnforcerAttendance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnforcerDuty
{
    public function __construct(
        private readonly AttendanceRules $rules,
        private readonly OfficerLocationService $locations,
    ) {}

    public function timeIn(User $user, array $values): EnforcerAttendance
    {
        return DB::transaction(function () use ($user, $values): EnforcerAttendance {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $moment = now();
            if ($error = $this->rules->timeInError($user, $moment)) {
                throw ValidationException::withMessages(['attendance' => $error]);
            }
            $date = $this->rules->date($moment);
            if ($open = $user->openAttendances()->latest('attendance_date')->first()) {
                $message = $open->attendance_date->toDateString() === $date
                    ? 'You must time out of your current duty before timing in again.'
                    : 'Ask an administrator to correct your missed Time Out before timing in.';
                throw ValidationException::withMessages(['attendance' => $message]);
            }
            if ($user->enforcerAttendances()->whereDate('attendance_date', $date)->exists()) {
                throw ValidationException::withMessages(['attendance' => 'You have already timed in today.']);
            }

            return EnforcerAttendance::create([
                ...$values,
                'user_id' => $user->id,
                'supervisor_id' => $user->supervisor_id,
                'time_in' => $moment,
                'attendance_date' => $date,
                'session' => EnforcerAttendance::WHOLE_DAY,
            ]);
        });
    }

    public function timeOut(User $user, array $coordinates = [], ?int $recordedBySupervisor = null): EnforcerAttendance
    {
        return DB::transaction(function () use ($user, $coordinates, $recordedBySupervisor): EnforcerAttendance {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $moment = now();
            if ($error = $this->rules->timeOutError($user, $moment)) {
                throw ValidationException::withMessages(['attendance' => $error]);
            }

            $attendance = $user->enforcerAttendances()->forDutyDate($this->rules->date($moment))->lockForUpdate()->first();
            if (! $attendance?->time_in || $attendance->time_out) {
                $subject = $recordedBySupervisor === null ? 'You' : $user->fullName;
                $message = ! $attendance?->time_in
                    ? "{$subject} must time in before timing out."
                    : $subject.($recordedBySupervisor === null ? ' have' : ' has').' already timed out today.';
                throw ValidationException::withMessages(['attendance' => $message]);
            }

            $values = ['time_out' => $moment];
            if ($recordedBySupervisor === null) {
                $lastLocation = $user->currentLocation;
                $values['time_out_latitude'] = $coordinates['latitude'] ?? $lastLocation?->latitude;
                $values['time_out_longitude'] = $coordinates['longitude'] ?? $lastLocation?->longitude;
            } else {
                $values['supervisor_id'] = $recordedBySupervisor;
            }
            $attendance->update($values);
            $this->locations->stopSharingIfOffDuty($user);

            return $attendance;
        });
    }
}
