<?php

namespace App\Listeners;

use App\Events\MemoryCreated;
use App\Jobs\DispatchWebhook;
use App\Models\WebhookSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

class ProcessSemanticWebhooks implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(MemoryCreated $event): void
    {
        $memory = $event->memory;

        if ($memory->visibility !== 'public') {
            return;
        }

        // 1. Generic 'memory.shared' event
        $sharedSubscriptions = WebhookSubscription::whereJsonContains('events', 'memory.shared')
            ->get();

        foreach ($sharedSubscriptions as $sub) {
            // Don't notify the agent about its own memory
            if ($sub->agent_id === $memory->agent_id) {
                continue;
            }

            DispatchWebhook::dispatch($sub, 'memory.shared', [
                'memory_id' => $memory->id,
                'agent_id' => $memory->agent_id,
                'key' => $memory->key,
                'summary' => $memory->summary,
            ]);
        }

        // 2. Semantic matching 'memory.semantic_match'
        // We look for subscriptions where the semantic matching distance is within a threshold (e.g. 0.25)
        $matches = WebhookSubscription::whereJsonContains('events', 'memory.semantic_match')
            ->whereNotNull('embedding')
            ->where('agent_id', '!=', $memory->agent_id)
            ->select('webhook_subscriptions.*')
            ->selectRaw('embedding <=> ? as distance', [$memory->embedding])
            ->whereRaw('embedding <=> ? < 0.25', [$memory->embedding])
            ->get();

        foreach ($matches as $match) {
            DispatchWebhook::dispatch($match, 'memory.semantic_match', [
                'memory_id' => $memory->id,
                'agent_id' => $memory->agent_id,
                'key' => $memory->key,
                'summary' => $memory->summary,
                'distance' => $match->distance,
            ]);
        }
    }
}
