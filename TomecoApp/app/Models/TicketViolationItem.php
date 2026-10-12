<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketViolationItem extends Model
{
    protected $fillable = ['ticket_issued_id', 'violation_issued_id', 'violations_type_id', 'description_snapshot', 'remarks'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Violation::class, 'ticket_issued_id');
    }

    public function formOption(): BelongsTo
    {
        return $this->belongsTo(ViolationIssued::class, 'violation_issued_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ViolationType::class, 'violations_type_id', 'section_id');
    }
}
