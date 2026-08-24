<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';
    public const ROLE_OFFICER = 'officer';
    public const ROLE_SUPERVISOR = 'supervisor';
    public const ROLE_DRIVER = 'driver';

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_OFFICER,
        self::ROLE_SUPERVISOR,
        self::ROLE_DRIVER,
    ];

    protected $fillable = [
        'fullName',
        'firstName',
        'middleName',
        'lastName',
        'nameExtension',
        'username',
        'address',
        'area',
        'barangay',
        'supervisor_id',
        'phoneNumber',
        'driverLicense',
        'plateNumber',
        'email',
        'role',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_otp',
    ];

    protected $appends = ['fullName'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_otp_expires_at' => 'datetime',
            'email_verification_otp_sent_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return collect([
            $this->firstName,
            $this->middleName,
            $this->lastName,
            $this->nameExtension,
        ])->filter(fn ($part) => filled($part))->join(' ');
    }

    public function setFullNameAttribute(?string $value): void
    {
        $parts = preg_split('/\s+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $extension = null;

        if ($parts !== [] && preg_match('/^(jr\.?|sr\.?|ii|iii|iv)$/i', (string) end($parts))) {
            $extension = array_pop($parts);
        }

        $this->attributes['firstName'] = array_shift($parts) ?? '';
        $this->attributes['lastName'] = count($parts) > 0 ? array_pop($parts) : '';
        $this->attributes['middleName'] = $parts !== [] ? implode(' ', $parts) : null;
        $this->attributes['nameExtension'] = $extension;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isOfficer(): bool
    {
        return $this->role === self::ROLE_OFFICER;
    }

    public function isSupervisor(): bool
    {
        return $this->role === self::ROLE_SUPERVISOR;
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    public function enforcers(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_id');
    }

    public function supervisorAttendances(): HasMany
    {
        return $this->hasMany(SupervisorAttendance::class);
    }

    public function isDriver(): bool
    {
        return $this->role === self::ROLE_DRIVER;
    }

    public function impoundedVehicles(): HasMany
    {
        return $this->hasMany(ImpoundedVehicle::class);
    }

    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class);
    }
}
