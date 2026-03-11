<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Memory extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'agent_id',
        'key',
        'value',
        'embedding',
        'metadata',
        'visibility',
        'expires_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'expires_at' => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function sharedWith(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'memory_shares')
            ->withPivot('created_at');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', 'public');
    }

    public function scopeVisibleTo(Builder $query, Agent $agent): Builder
    {
        return $query->where(function ($q) use ($agent) {
            // Own memories
            $q->where('agent_id', $agent->id)
              // Public memories
                ->orWhere('visibility', 'public')
              // Explicitly shared with this agent
                ->orWhereHas('sharedWith', fn ($sq) => $sq->where('agent_id', $agent->id));
        });
    }

    public function scopeWithTags(Builder $query, array $tags): Builder
    {
        foreach ($tags as $tag) {
            $query->whereJsonContains('metadata->tags', $tag);
        }

        return $query;
    }

    // -------------------------------------------------------------------------
    // Semantic search (pgvector cosine similarity)
    // -------------------------------------------------------------------------

    public function scopeSemanticSearch(Builder $query, array $embedding, int $limit = 10): Builder
    {
        $vector = '['.implode(',', $embedding).']';

        return $query
            ->selectRaw('*, 1 - (embedding <=> ?) AS similarity', [$vector])
            ->whereNotNull('embedding')
            ->orderByRaw('embedding <=> ?', [$vector])
            ->limit($limit);
    }

    // -------------------------------------------------------------------------
    // Keyword search (PostgreSQL full-text search)
    // -------------------------------------------------------------------------

    public function scopeKeywordSearch(Builder $query, string $searchTerm, int $limit = 10): Builder
    {
        // Replace spaces with & for to_tsquery
        $tsQuery = implode(' & ', array_filter(explode(' ', $searchTerm)));

        if (empty($tsQuery)) {
            return $query;
        }

        return $query
            ->selectRaw('*, ts_rank(search_vector, to_tsquery(\'english\', ?)) AS rank', [$tsQuery])
            ->whereRaw("search_vector @@ to_tsquery('english', ?)", [$tsQuery])
            ->orderByRaw("rank DESC")
            ->limit($limit);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
