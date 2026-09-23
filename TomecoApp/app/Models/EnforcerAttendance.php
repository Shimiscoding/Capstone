<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnforcerAttendance extends Model
{
    protected $fillable = [
        'user_id', 'supervisor_id', 'attendance_date', 'time_in', 'time_out',
        'time_in_latitude', 'time_in_longitude', 'time_out_latitude', 'time_out_longitude',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date', 'time_in' => 'datetime', 'time_out' => 'datetime',
            'time_in_latitude' => 'float', 'time_in_longitude' => 'float',
            'time_out_latitude' => 'float', 'time_out_longitude' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
