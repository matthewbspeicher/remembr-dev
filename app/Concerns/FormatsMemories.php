<?php

namespace App\Concerns;

use App\Models\Memory;

trait FormatsMemories
{
    private function formatMemory(Memory $memory): array
    {
        $metadata = $memory->metadata ?? [];
        $tags = $metadata['tags'] ?? [];
        unset($metadata['tags']);

        $relations = [];
        if ($memory->relationLoaded('relatedTo')) {
            $relations = $memory->relatedTo->map(fn($rel) => [
                'id' => $rel->id,
                'type' => $rel->pivot->type ?? 'related',
            ])->toArray();
        }

        return [
            'id' => $memory->id,
            'key' => $memory->key,
            'value' => $memory->value,
            'type' => $memory->type,
            'visibility' => $memory->visibility,
            'workspace_id' => $memory->workspace_id,
            'importance' => $memory->importance,
            'confidence' => $memory->confidence,
            'metadata' => empty($metadata) ? new \stdClass : $metadata,
            'tags' => array_values($tags),
            'relations' => empty($relations) ? [] : $relations,
            'created_at' => $memory->created_at->toIso8601String(),
            'updated_at' => $memory->updated_at->toIso8601String(),
            'expires_at' => $memory->expires_at?->toIso8601String(),
        ];
    }
}
