<?php

namespace App\Services;

use App\Models\EnforcerAttendance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnforcerDuty
{
    public function timeIn(User $user, AttendanceRules $rules, array $values): EnforcerAttendance
    {
        return DB::transaction(function () use ($user, $rules, $values) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($user->enforcerAttendances()->whereNotNull('time_in')->whereNull('time_out')->exists()) {
                throw ValidationException::withMessages(['attendance' => 'You must time out of your current duty before timing in again.']);
            }
            if ($user->enforcerAttendances()->whereDate('attendance_date', $rules->date())->exists()) {
                throw ValidationException::withMessages(['attendance' => 'You have already timed in today.']);
            }

            return EnforcerAttendance::create([
                ...$values,
                'user_id' => $user->id,
                'attendance_date' => $rules->date(),
                'session' => EnforcerAttendance::WHOLE_DAY,
            ]);
        });
    }
}
