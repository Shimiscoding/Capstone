<?php

namespace App\Services;

use App\Events\OfficerLocationUpdated;
use App\Models\OfficerLocation;
use App\Models\OfficerLocationHistory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class OfficerLocationService
{
    public const OFF_DUTY = 'off_duty';

    public function __construct(private readonly AttendanceRules $rules) {}

    public function update(User $officer, array $data): OfficerLocation
    {
        [$location, $changed] = DB::transaction(function () use ($officer, $data): array {
            // Use the same officer lock as attendance writes so a late upload cannot
            // restore sharing after a concurrent Time Out.
            $officer = User::query()->lockForUpdate()->findOrFail($officer->id);
            if (! $officer->isOfficer() || $officer->effectiveAccountStatus() !== User::STATUS_ACTIVE) {
                throw new AuthorizationException('Only active officer accounts may share a location.');
            }
            $now = now();
            if (! $officer->isOnDuty($this->rules->date($now))) {
                throw new AuthorizationException('Location sharing is only allowed while you are timed in.', self::OFF_DUTY);
            }

            $previous = $officer->currentLocation()->first();
            $recordedAt = isset($data['recorded_at'])
                ? Carbon::parse($data['recorded_at'])->setTimezone(config('app.timezone'))
                : $now;
            if ($previous && $recordedAt->lt($previous->recorded_at)) {
                return [$previous, false];
            }
            $values = [...$data, 'team_id' => $officer->supervisor_id, 'is_sharing' => true, 'recorded_at' => $recordedAt];
            $location = $officer->currentLocation()->updateOrCreate([], $values);

            if ($this->shouldSaveHistory($previous, $location)) {
                OfficerLocationHistory::create($location->only(['user_id', 'team_id', 'latitude', 'longitude', 'accuracy', 'speed', 'heading', 'recorded_at']));
            }

            return [$location, true];
        });

        if ($changed) {
            $this->publishLocation($location, toOthers: true);
        }

        return $location;
    }

    public function stopSharingIfOffDuty(User $officer): void
    {
        $location = DB::transaction(function () use ($officer): ?OfficerLocation {
            $officer = User::query()->lockForUpdate()->findOrFail($officer->id);
            if ($officer->isOnDuty($this->rules->date())) {
                return null;
            }
            $location = $officer->currentLocation()->where('is_sharing', true)->first();
            $location?->update(['is_sharing' => false]);

            return $location;
        });
        if ($location) {
            DB::afterCommit(fn () => $this->publishLocation($location));
        }
    }

    private function publishLocation(OfficerLocation $location, bool $toOthers = false): void
    {
        // The write has committed. Polling can recover if live broadcasting is unavailable.
        rescue(function () use ($location, $toOthers): void {
            $date = $this->rules->date();
            $location->load(['officer.supervisor', 'officer.enforcerAttendances' => fn ($query) => $query->forDutyDate($date)->limit(1)]);
            $event = new OfficerLocationUpdated($location);
            if ($toOthers) {
                $event->dontBroadcastToCurrentUser();
            }
            event($event);
        });
    }

    public function status(OfficerLocation $location, ?bool $onDuty = null): string
    {
        if (! $location->is_sharing || $onDuty === false) {
            return 'off_duty';
        }
        $age = $location->updated_at->diffInSeconds(now());
        if ($age <= config('officer-location.active_seconds')) {
            return 'active';
        }
        if ($age <= config('officer-location.offline_seconds')) {
            return 'delayed';
        }

        return 'offline';
    }

    private function shouldSaveHistory(?OfficerLocation $previous, OfficerLocation $current): bool
    {
        if (! $previous) {
            return true;
        }
        $latest = OfficerLocationHistory::where('user_id', $current->user_id)->latest('recorded_at')->first();
        if (! $latest || $latest->recorded_at->diffInSeconds($current->recorded_at) >= config('officer-location.history_interval_seconds')) {
            return true;
        }

        return $this->distanceMeters($latest->latitude, $latest->longitude, $current->latitude, $current->longitude)
            >= config('officer-location.history_minimum_distance_meters');
    }

    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
