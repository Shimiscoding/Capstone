<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Violation extends Model
{
    use HasFactory;

    protected $fillable = [
        'enforcer_id',
        'enforcer_name',
        'first_name',
        'middle_name',
        'last_name',
        'license_number',
        'license_type',
        'plate_number',
        'vehicle_type',
        'or_number',
        'cr_number',
        'violation_type',
        'fine_amount',
        'status',
        'location',
        'evidence_image',
        'signature',
        'enforcer_signature',
        'motorist_key',
    ];

    protected function casts(): array
    {
        return [
            'first_name' => 'encrypted',
            'middle_name' => 'encrypted',
            'last_name' => 'encrypted',
            'license_number' => 'encrypted',
            'plate_number' => 'encrypted',
            'or_number' => 'encrypted',
            'cr_number' => 'encrypted',
            'location' => 'encrypted',
            'evidence_image' => 'encrypted',
            'signature' => 'encrypted',
            'enforcer_signature' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Violation $violation): void {
            $violation->motorist_key = self::makeMotoristKey(
                $violation->license_number,
                $violation->full_name,
            );
        });
    }

    public static function makeMotoristKey(?string $licenseNumber, ?string $fullName): string
    {
        $license = strtolower((string) preg_replace('/[^a-z0-9]/i', '', (string) $licenseNumber));
        $name = strtolower((string) preg_replace('/[^a-z0-9]/i', '', (string) $fullName));
        $identity = $license !== '' ? 'license:'.$license : 'name:'.$name;

        return hash_hmac('sha256', $identity, (string) config('app.key'));
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter(fn (?string $name): bool => filled($name))
            ->implode(' ');
    }

    public function enforcer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enforcer_id');
    }
}
