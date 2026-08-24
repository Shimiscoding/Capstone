<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpoundedVehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'reference', 'owner', 'vehicle', 'type', 'plate', 'violation',
        'impounded_at', 'location', 'status',
    ];

    protected function casts(): array
    {
        return [
            'owner' => 'encrypted',
            'location' => 'encrypted',
            'impounded_at' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
