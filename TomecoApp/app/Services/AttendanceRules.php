<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

class AttendanceRules
{
    public function __construct(private readonly Settings $settings) {}

    public function timeInError(User $user, ?Carbon $moment = null): ?string
    {
        return $this->errorFor($user, 'Time In', 'attendance_time_in_start', 'attendance_time_in_end', $moment);
    }

    public function timeOutError(User $user, ?Carbon $moment = null): ?string
    {
        return $this->errorFor($user, 'Time Out', 'attendance_time_out_start', 'attendance_time_out_end', $moment);
    }

    private function errorFor(User $user, string $action, string $startKey, string $endKey, ?Carbon $moment): ?string
    {
        if (! $user->attendance_restrictions_enabled) {
            return null;
        }

        $timezone = $this->settings->get('general.timezone', config('app.timezone'));
        $now = ($moment ?? now())->copy()->timezone($timezone);
        $workingDays = $user->attendance_working_days ?? [];

        if (! in_array(strtolower($now->englishDayOfWeek), $workingDays, true)) {
            return $action.' is not allowed on '.$now->englishDayOfWeek.'.';
        }

        $start = substr((string) $user->{$startKey}, 0, 5);
        $end = substr((string) $user->{$endKey}, 0, 5);
        $current = $now->format('H:i');

        if ($current < $start || $current > $end) {
            return $action.' is only allowed from '.Carbon::createFromFormat('H:i', $start)->format('h:i A').' to '.Carbon::createFromFormat('H:i', $end)->format('h:i A').'.';
        }

        return null;
    }
}
