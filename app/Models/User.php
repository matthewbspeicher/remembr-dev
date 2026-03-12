<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'api_token',
        'magic_link_token',
        'magic_link_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
        'magic_link_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'magic_link_expires_at' => 'datetime',
        ];
    }

    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class, 'owner_id');
    }

    public function ownedGyms(): HasMany
    {
        return $this->hasMany(ArenaGym::class, 'owner_id');
    }

    public function generateMagicLink(): string
    {
        $token = Str::random(64);

        $this->update([
            'magic_link_token' => $token,
            'magic_link_expires_at' => now()->addMinutes(30),
        ]);

        return $token;
    }

    public function hasValidMagicLink(string $token): bool
    {
        return $this->magic_link_token === $token
            && $this->magic_link_expires_at
            && $this->magic_link_expires_at->isFuture();
    }

    public function clearMagicLink(): void
    {
        $this->update([
            'magic_link_token' => null,
            'magic_link_expires_at' => null,
        ]);
    }

    public static function generateToken(): string
    {
        return 'own_'.Str::random(40);
    }

    public function ensureApiToken(): string
    {
        if (! $this->api_token) {
            $this->update(['api_token' => self::generateToken()]);
        }

        return $this->api_token;
    }
}
