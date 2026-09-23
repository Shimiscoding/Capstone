<?php

namespace App\Support;

use App\Models\OfficerLocation;
use App\Services\OfficerLocationService;

class OfficerLocationData
{
    public static function make(OfficerLocation $location, OfficerLocationService $service): array
    {
        $officer = $location->officer;
        $attendance = $officer->enforcerAttendances->first();
        $onDuty = (bool) ($attendance?->time_in && ! $attendance?->time_out);

        return [
            'id' => $officer->id, 'name' => $officer->fullName, 'personnel_id' => $officer->username,
            'team_id' => $officer->supervisor_id,
            'team' => $officer->supervisor?->fullName ? $officer->supervisor->fullName.' Team' : 'Unassigned',
            'supervisor' => $officer->supervisor?->fullName, 'area' => $officer->area,
            'latitude' => $location->latitude, 'longitude' => $location->longitude,
            'accuracy' => $location->accuracy,
            'low_accuracy' => $location->accuracy !== null && $location->accuracy > config('officer-location.low_accuracy_meters'),
            'speed' => $location->speed, 'heading' => $location->heading,
            'is_sharing' => $location->is_sharing, 'on_duty' => $onDuty,
            'status' => $service->status($location, $onDuty),
            'time_in' => $attendance?->time_in?->toIso8601String(),
            'recorded_at' => $location->recorded_at?->toIso8601String(),
            'received_at' => $location->updated_at?->toIso8601String(),
            'details_url' => route('dashboard.users.enforcers.show', $officer),
        ];
    }
}
