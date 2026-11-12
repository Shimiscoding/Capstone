<?php

namespace App\Models;

use App\Services\ViolationCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Violation extends Model
{
    use HasFactory;

    // Keep the existing class/routes for compatibility with the mobile client.
    protected $table = 'Ticket_issued';

    protected $with = ['issuedViolations.type', 'issuedViolations.formOption'];

    protected $appends = ['violation_type'];

    protected $hidden = ['vehicle_type_key', 'submission_fingerprint'];

    private ?string $pendingViolationText = null;

    protected $fillable = [
        'citation_number',
        'enforcer_id',
        'enforcer_name',
        'first_name',
        'middle_name',
        'last_name',
        'license_number',
        'license_type',
        'license_expiration_date',
        'driver_address',
        'accident_involved',
        'driver_response',
        'plate_number',
        'vehicle_type',
        'registration_plate_number',
        'vehicle_make',
        'vehicle_series',
        'vehicle_body_type',
        'vehicle_year_model',
        'registered_owner_name',
        'registered_owner_address',
        'evidence_images',
        'or_number',
        'cr_number',
        'violation_type',
        'fine_amount',
        'status',
        'location',
        'signature',
        'enforcer_signature',
        'motorist_key',
        'submission_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'first_name' => 'encrypted',
            'middle_name' => 'encrypted',
            'last_name' => 'encrypted',
            'license_number' => 'encrypted',
            'license_type' => 'encrypted',
            'license_expiration_date' => 'encrypted',
            'driver_address' => 'encrypted',
            'accident_involved' => 'boolean',
            'driver_response' => 'encrypted',
            'plate_number' => 'encrypted',
            'vehicle_type' => 'encrypted',
            'registration_plate_number' => 'encrypted',
            'vehicle_make' => 'encrypted',
            'vehicle_series' => 'encrypted',
            'vehicle_body_type' => 'encrypted',
            'vehicle_year_model' => 'encrypted',
            'registered_owner_name' => 'encrypted',
            'registered_owner_address' => 'encrypted',
            'evidence_images' => 'encrypted:array',
            'or_number' => 'encrypted',
            'cr_number' => 'encrypted',
            'location' => 'encrypted',
            'signature' => 'encrypted',
            'enforcer_signature' => 'encrypted',
            'fine_amount' => 'float',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Violation $violation): void {
            $motoristKey = self::makeMotoristKey($violation->license_number, $violation->full_name);
            if ($motoristKey === self::makeMotoristKey(null, null)) {
                $originalName = implode(' ', [
                    $violation->getOriginal('first_name'), $violation->getOriginal('middle_name'), $violation->getOriginal('last_name'),
                ]);
                $wasAnonymous = self::makeMotoristKey($violation->getOriginal('license_number'), $originalName) === $motoristKey;
                $originalKey = $violation->getRawOriginal('motorist_key');
                $motoristKey = $wasAnonymous && $originalKey && $originalKey !== $motoristKey
                    ? $originalKey
                    : self::makeAnonymousMotoristKey((string) Str::uuid());
            }
            $violation->motorist_key = $motoristKey;
            if ($violation->isDirty('vehicle_type')) {
                $violation->vehicle_type_key = self::makeVehicleTypeKey($violation->vehicle_type);
            }
        });
    }

    public static function makeVehicleTypeKey(?string $vehicleType): ?string
    {
        if ($vehicleType === null) {
            return null;
        }

        $label = mb_convert_case(rtrim($vehicleType, ' '), MB_CASE_FOLD, 'UTF-8');

        return hash_hmac('sha256', 'ticket-vehicle-type:'.$label, Crypt::getKey());
    }

    public static function makeMotoristKey(?string $licenseNumber, ?string $fullName): string
    {
        $license = strtolower((string) preg_replace('/[^a-z0-9]/i', '', (string) $licenseNumber));
        $name = strtolower((string) preg_replace('/[^a-z0-9]/i', '', (string) $fullName));
        $identity = $license !== '' ? 'license:'.$license : 'name:'.$name;

        return hash_hmac('sha256', $identity, (string) config('app.key'));
    }

    public static function makeAnonymousMotoristKey(string $reference): string
    {
        return hash_hmac('sha256', 'unidentified-ticket:'.$reference, (string) config('app.key'));
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter(fn (?string $name): bool => filled($name))
            ->implode(' ');
    }

    public function getDisplayTicketNumberAttribute(): string
    {
        return $this->citation_number ?? '#'.str_pad((string) $this->getKey(), 7, '0', STR_PAD_LEFT);
    }

    public function enforcer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enforcer_id');
    }

    public function getEvidencePhotosAttribute(): array
    {
        return $this->evidence_images ?: [];
    }

    public function issuedViolations(): HasMany
    {
        return $this->hasMany(TicketViolationItem::class, 'ticket_issued_id')->orderBy('id');
    }

    public function getViolationTypeAttribute(): string
    {
        return $this->pendingViolationText ?? $this->issuedViolations->pluck('description_snapshot')->implode(', ');
    }

    public function setViolationTypeAttribute(string $value): void
    {
        $this->pendingViolationText = $value;
    }

    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(function () use ($options) {
            // Older clients submit a label instead of catalog IDs. Never guess a section.
            if ($this->pendingViolationText !== null && $this->exists && ($this->issuedViolations()->count() > 1
                || $this->issuedViolations()->whereNotNull('violations_type_id')->exists())) {
                throw ValidationException::withMessages(['violation_type' => 'Use the violations list to update a ticket with recorded sections or multiple offenses.']);
            }
            $saved = parent::save($options);
            if ($saved && $this->pendingViolationText !== null) {
                $labels = ViolationCatalog::legacyLabels($this->pendingViolationText);
                foreach ($this->issuedViolations()->get() as $existing) {
                    $existing->delete();
                }
                $seen = [];
                foreach ($labels as $label) {
                    $option = ViolationCatalog::formOptionFor($label);
                    if (isset($seen[$option->id])) {
                        continue;
                    }
                    $this->issuedViolations()->create([
                        'violation_issued_id' => $option->id, 'violations_type_id' => null, 'description_snapshot' => $label,
                    ]);
                    $seen[$option->id] = true;
                }
                $this->pendingViolationText = null;
                $this->unsetRelation('issuedViolations');
            }

            return $saved;
        });
    }
}
