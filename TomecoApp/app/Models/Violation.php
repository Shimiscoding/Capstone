<?php

namespace App\Models;

use App\Services\ViolationCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Violation extends Model
{
    use HasFactory;

    // Keep the existing class/routes for compatibility with the mobile client.
    protected $table = 'Ticket_issued';

    protected $with = ['issuedViolations.type', 'issuedViolations.formOption'];

    protected $appends = ['violation_type'];

    private ?string $pendingViolationText = null;

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
