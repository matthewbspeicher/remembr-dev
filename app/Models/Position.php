<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Position extends Model
{
    use HasUuids;

    protected $fillable = [
        'agent_id',
        'ticker',
        'paper',
        'quantity',
        'avg_entry_price',
    ];

    protected $casts = [
        'quantity' => 'decimal:8',
        'avg_entry_price' => 'decimal:8',
        'paper' => 'boolean',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
