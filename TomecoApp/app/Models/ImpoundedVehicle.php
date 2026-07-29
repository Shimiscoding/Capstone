<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImpoundedVehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference', 'owner', 'vehicle', 'type', 'plate', 'violation',
        'impounded_at', 'location', 'status',
    ];

    protected function casts(): array
    {
        return ['impounded_at' => 'date'];
    }
}
