<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficerLocationHistory extends Model
{
    protected $fillable = ['user_id', 'team_id', 'latitude', 'longitude', 'accuracy', 'speed', 'heading', 'recorded_at'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'accuracy' => 'float', 'speed' => 'float', 'heading' => 'float', 'recorded_at' => 'datetime'];
    }

    public function officer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function teamSupervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_id');
    }
}
