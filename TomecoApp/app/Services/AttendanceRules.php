<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

class AttendanceRules
{
    public function __construct(private readonly Settings $settings) {}

    public function date(?Carbon $moment = null): string
    {
        return ($moment ?? now())->copy()->timezone($this->settings->get('general.timezone', config('app.timezone')))->toDateString();
    }

    public function schedule(User $user): array
    {
        $windows = [];
        foreach (['in_start', 'in_end', 'out_start', 'out_end'] as $window) {
            $windows[$window] = $user->{'attendance_time_'.$window} ? substr($user->{'attendance_time_'.$window}, 0, 5) : null;
        }

        return [
            'enabled' => (bool) $user->attendance_restrictions_enabled,
            'timezone' => $this->settings->get('general.timezone', config('app.timezone')),
            'working_days' => $user->attendance_working_days ?? [],
            ...$windows,
            'sessions' => ['whole_day' => $windows],
        ];
    }

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
            return 'Your attendance schedule has not been set. Please contact an administrator.';
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

        if ($start === '' || $end === '') {
            return 'Your attendance schedule has not been set. Please contact an administrator.';
        }

        if ($current < $start || $current > $end) {
            return $action.' is only allowed from '.Carbon::createFromFormat('H:i', $start)->format('h:i A').' to '.Carbon::createFromFormat('H:i', $end)->format('h:i A').'.';
        }

        return null;
    }
}
