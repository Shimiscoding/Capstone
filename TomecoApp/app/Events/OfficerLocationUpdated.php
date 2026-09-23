<?php

namespace App\Events;

use App\Models\OfficerLocation;
use App\Services\OfficerLocationService;
use App\Support\OfficerLocationData;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OfficerLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public OfficerLocation $location) {}

    public function broadcastOn(): array
    {
        return $this->location->team_id ? [new PrivateChannel('team.'.$this->location->team_id.'.locations')] : [];
    }

    public function broadcastAs(): string
    {
        return 'OfficerLocationUpdated';
    }

    public function broadcastWith(): array
    {
        return ['officer' => OfficerLocationData::make($this->location, app(OfficerLocationService::class))];
    }
}
