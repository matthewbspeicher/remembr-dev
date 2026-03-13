<?php

namespace App\Listeners;

use App\Models\User;
use Laravel\Cashier\Events\WebhookReceived;

class SyncAgentQuotas
{
    public function handle(WebhookReceived $event): void
    {
        $payload = $event->payload;
        $type = $payload['type'] ?? null;

        if (! in_array($type, [
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
        ])) {
            return;
        }

        $stripeId = $payload['data']['object']['customer'] ?? null;
        $user = User::where('stripe_id', $stripeId)->first();

        if (! $user) {
            return;
        }

        $limit = $user->maxMemoriesPerAgent();
        $user->agents()->update(['max_memories' => $limit]);
    }
}
