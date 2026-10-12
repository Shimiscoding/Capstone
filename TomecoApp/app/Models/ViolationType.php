<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViolationType extends Model
{
    protected $table = 'violations_type';

    protected $primaryKey = 'section_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['section_id', 'description', 'violation_issued_id'];

    public function getIdAttribute(): int
    {
        return (int) $this->section_id;
    }

    public function formOption(): BelongsTo
    {
        return $this->belongsTo(ViolationIssued::class, 'violation_issued_id');
    }

    public function issuedViolations(): HasMany
    {
        return $this->hasMany(TicketViolationItem::class, 'violations_type_id', 'section_id');
    }
}
