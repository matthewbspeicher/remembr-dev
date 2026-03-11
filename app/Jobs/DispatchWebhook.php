<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DispatchWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function backoff()
    {
        return [10, 60, 300];
    }

    public function __construct(
        public WebhookSubscription $subscription,
        public string $event,
        public array $payload
    ) {}

    public function handle(): void
    {
        if (! $this->subscription->is_active) {
            return;
        }

        $body = [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'event' => $this->event,
            'timestamp' => now()->toIso8601String(),
            'data' => $this->payload,
        ];

        $jsonBody = json_encode($body);

        $signature = hash_hmac('sha256', $jsonBody, $this->subscription->secret);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Webhook-Signature' => $signature,
            'User-Agent' => 'Remembr-Webhook/1.0',
        ])->timeout(5)->post($this->subscription->url, $body);

        WebhookDelivery::create([
            'subscription_id' => $this->subscription->id,
            'event' => $this->event,
            'payload' => $body,
            'response_status' => $response->status(),
            'attempt' => $this->attempts(),
        ]);

        if ($response->failed()) {
            $this->subscription->increment('failure_count');
            if ($this->subscription->failure_count >= 10) {
                $this->subscription->update(['is_active' => false]);
            }
            $response->throw();
        } else {
            $this->subscription->update(['failure_count' => 0]);
        }
    }
}
