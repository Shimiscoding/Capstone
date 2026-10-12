<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ViolationIssued extends Model
{
    protected $table = 'violation_issued';

    protected $fillable = ['label', 'sort_order'];

    public function ticketItems(): HasMany
    {
        return $this->hasMany(TicketViolationItem::class, 'violation_issued_id');
    }
}
