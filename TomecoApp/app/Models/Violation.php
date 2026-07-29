<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Violation extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_name',
        'license_number',
        'plate_number',
        'violation_type',
        'fine_amount',
        'status',
        'location',
        'evidence_image',
    ];

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
