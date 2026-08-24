<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Violation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'driver_name',
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
            'driver_name' => 'encrypted',
            'license_number' => 'encrypted',
            'location' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Violation $violation): void {
            if ($violation->isDirty('user_id') || blank($violation->plate_number)) {
                return;
            }

            $violation->user_id = User::query()
                ->where('role', User::ROLE_DRIVER)
                ->whereRaw('LOWER(TRIM(plateNumber)) = ?', [Str::lower(trim($violation->plate_number))])
                ->value('id');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
