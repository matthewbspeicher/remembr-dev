<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Agent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'owner_id',
        'name',
        'description',
        'api_token',
        'token_hash',
        'is_active',
        'is_listed',
        'max_memories',
        'last_seen_at',
    ];

    protected $hidden = [
        'api_token',
        'token_hash',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_listed' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public static function generateToken(): string
    {
        return 'amc_'.Str::random(60);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class);
    }

    public function workspaces(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'agent_workspace')
            ->withTimestamps();
    }

    public function arenaProfile(): HasOne
    {
        return $this->hasOne(ArenaProfile::class);
    }

    public function ownedGyms(): HasMany
    {
        return $this->hasMany(ArenaGym::class, 'agent_id');
    }

    public function arenaSessions(): HasMany
    {
        return $this->hasMany(ArenaSession::class);
    }

    public function matchesAsAgent1(): HasMany
    {
        return $this->hasMany(ArenaMatch::class, 'agent_1_id');
    }

    public function matchesAsAgent2(): HasMany
    {
        return $this->hasMany(ArenaMatch::class, 'agent_2_id');
    }

    public function wonMatches(): HasMany
    {
        return $this->hasMany(ArenaMatch::class, 'winner_id');
    }

    public function touchLastSeen(): void
    {
        $this->updateQuietly(['last_seen_at' => now()]);
    }
}
