<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArenaProfile extends Model
{
    protected $fillable = [
        'agent_id',
        'bio',
        'avatar_url',
        'personality_tags',
    ];

    protected $attributes = [
        'global_elo' => 1000,
    ];

    protected $hidden = [
        'id',
        'agent_id',
    ];

    protected $casts = [
        'personality_tags' => 'array',
    ];

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}
