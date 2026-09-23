<?php

namespace App\Services;

use App\Events\OfficerLocationUpdated;
use App\Models\OfficerLocation;
use App\Models\OfficerLocationHistory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class OfficerLocationService
{
    public function update(User $officer, array $data): OfficerLocation
    {
        if (! $officer->isOfficer() || $officer->effectiveAccountStatus() !== User::STATUS_ACTIVE) {
            throw new AuthorizationException('Only active officer accounts may share a location.');
        }
        if (! $officer->isOnDuty()) {
            throw new AuthorizationException('Location sharing is only allowed while you are timed in.');
        }

        $location = DB::transaction(function () use ($officer, $data): OfficerLocation {
            $previous = OfficerLocation::where('user_id', $officer->id)->lockForUpdate()->first();
            $recordedAt = isset($data['recorded_at']) ? now()->parse($data['recorded_at']) : now();
            $values = [...$data, 'team_id' => $officer->supervisor_id, 'is_sharing' => true, 'recorded_at' => $recordedAt];
            $location = OfficerLocation::updateOrCreate(['user_id' => $officer->id], $values);

            if ($this->shouldSaveHistory($previous, $location)) {
                OfficerLocationHistory::create($location->only(['user_id', 'team_id', 'latitude', 'longitude', 'accuracy', 'speed', 'heading', 'recorded_at']));
            }

            return $location;
        });

        $location->load(['officer.supervisor']);
        broadcast(new OfficerLocationUpdated($location))->toOthers();

        return $location;
    }

    public function stopSharing(User $officer): void
    {
        $location = OfficerLocation::where('user_id', $officer->id)->first();
        if (! $location) {
            return;
        }
        $location->update(['is_sharing' => false]);
        $location->load(['officer.supervisor']);
        broadcast(new OfficerLocationUpdated($location));
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
