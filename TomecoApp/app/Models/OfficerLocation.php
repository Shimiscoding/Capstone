<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficerLocation extends Model
{
    protected $fillable = ['user_id', 'team_id', 'latitude', 'longitude', 'accuracy', 'speed', 'heading', 'is_sharing', 'recorded_at'];

    protected function casts(): array
    {
        return [
            'latitude' => 'float', 'longitude' => 'float', 'accuracy' => 'float',
            'speed' => 'float', 'heading' => 'float', 'is_sharing' => 'boolean', 'recorded_at' => 'datetime',
        ];
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
