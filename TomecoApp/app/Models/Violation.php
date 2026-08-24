<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Violation extends Model
{
    use HasFactory;

    protected $fillable = [
        'motorist_name',
        'license_number',
        'plate_number',
        'violation_type',
        'fine_amount',
        'status',
        'location',
        'evidence_image',
    ];

    protected function casts(): array
    {
        return [
            'motorist_name' => 'encrypted',
            'license_number' => 'encrypted',
            'location' => 'encrypted',
        ];
    }

}
