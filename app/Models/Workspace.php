<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workspace extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'description',
        'owner_id',
        'is_guild',
        'guild_elo',
        'api_token',
    ];

    protected $hidden = [
        'api_token',
    ];

    protected $casts = [
        'is_guild' => 'boolean',
    ];

    protected $attributes = [
        'is_guild' => false,
        'guild_elo' => 1000,
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withTimestamps();
    }

    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'agent_workspace')
            ->withTimestamps();
    }

    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    public static function generateToken(): string
    {
        return 'wks_'.\Illuminate\Support\Str::random(40);
    }

    public function ensureApiToken(): string
    {
        if (! $this->api_token) {
            $this->update(['api_token' => self::generateToken()]);
        }

        return $this->api_token;
    }
}
