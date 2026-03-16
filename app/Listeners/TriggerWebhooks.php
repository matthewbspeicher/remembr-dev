<?php

namespace App\Listeners;

use App\Events\MemoryShared;
use App\Jobs\DispatchWebhook;
use App\Models\WebhookSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;

class TriggerWebhooks implements ShouldQueue
{
    public function handle(MemoryShared $event): void
    {
        $subscriptions = WebhookSubscription::where('agent_id', $event->recipient->id)
            ->where('is_active', true)
            ->whereJsonContains('events', 'memory.shared')
            ->get();

        $event->memory->loadMissing('agent');

        foreach ($subscriptions as $sub) {
            DispatchWebhook::dispatch($sub, 'memory.shared', [
                'memory_id' => $event->memory->id,
                'memory_key' => $event->memory->key,
                'memory_value' => $event->memory->value,
                'shared_by_agent_id' => $event->memory->agent_id,
                'shared_by_agent_name' => $event->memory->agent->name ?? null,
            ]);
        }
    }
}
