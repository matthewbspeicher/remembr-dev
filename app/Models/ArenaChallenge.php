<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArenaChallenge extends Model
{
    use HasFactory;
    protected $fillable = [
        'gym_id',
        'title',
        'prompt',
        'difficulty_level',
        'xp_reward',
        'validator_type',
        'validator_config',
    ];

    protected $casts = [
        'validator_config' => 'array',
    ];

    public function gym(): BelongsTo
    {
        return $this->belongsTo(ArenaGym::class, 'gym_id');
    }
}
