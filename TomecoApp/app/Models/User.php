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

    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_OFFICER,
        self::ROLE_SUPERVISOR,
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PENDING = 'pending';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_BANNED = 'banned';

    public const ACCOUNT_STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_PENDING,
        self::STATUS_INACTIVE,
        self::STATUS_SUSPENDED,
        self::STATUS_BANNED,
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
        'email',
        'role',
        'account_status',
        'attendance_restrictions_enabled',
        'attendance_time_in_start',
        'attendance_time_in_end',
        'attendance_time_out_start',
        'attendance_time_out_end',
        'attendance_working_days',
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
            'attendance_restrictions_enabled' => 'boolean',
            'attendance_working_days' => 'array',
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

    public function effectiveAccountStatus(): string
    {
        if (in_array($this->account_status, [self::STATUS_INACTIVE, self::STATUS_SUSPENDED, self::STATUS_BANNED], true)) {
            return $this->account_status;
        }

        return $this->hasVerifiedEmail() ? self::STATUS_ACTIVE : self::STATUS_PENDING;
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

    public function enforcerAttendances(): HasMany
    {
        return $this->hasMany(EnforcerAttendance::class);
    }

    public function recordedEnforcerAttendances(): HasMany
    {
        return $this->hasMany(EnforcerAttendance::class, 'supervisor_id');
    }

    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class);
    }
}
