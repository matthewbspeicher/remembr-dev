<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Agent extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'owner_id',
        'name',
        'description',
        'api_token',
        'is_active',
        'max_memories',
        'last_seen_at',
    ];

    protected $hidden = [
        'api_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public static function generateToken(): string
    {
        return 'amc_'.Str::random(60);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    public function workspaces(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'agent_workspace')
            ->withTimestamps();
    }

    public function touchLastSeen(): void
    {
        $this->updateQuietly(['last_seen_at' => now()]);
    }
}
