<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArenaMatch extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'agent_1_id',
        'agent_2_id',
        'challenge_id',
        'winner_id',
        'status',
    ];

    public function agent1(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_1_id');
    }

    public function agent2(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_2_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'winner_id');
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(ArenaChallenge::class, 'challenge_id');
    }
}
