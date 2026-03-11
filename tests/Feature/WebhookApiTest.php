<?php

use App\Events\MemoryShared;
use App\Jobs\DispatchWebhook;
use App\Models\Agent;
use App\Models\Memory;
use App\Models\User;
use App\Models\WebhookSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->owner = User::factory()->create(['api_token' => 'owner_token']);
    $this->agent = Agent::factory()->create([
        'owner_id' => $this->owner->id,
        'api_token' => 'amc_webhook_agent',
    ]);
});

it('can register a webhook', function () {
    $response = $this->postJson('/api/v1/webhooks', [
        'url' => 'https://example.com/webhook',
        'events' => ['memory.shared'],
    ], ['Authorization' => 'Bearer '.$this->agent->api_token]);

    $response->assertCreated();
    $response->assertJsonFragment([
        'url' => 'https://example.com/webhook',
        'events' => ['memory.shared'],
    ]);
});

it('validates webhook url is https', function () {
    $this->postJson('/api/v1/webhooks', [
        'url' => 'http://example.com/webhook',
        'events' => ['memory.shared'],
    ], ['Authorization' => 'Bearer '.$this->agent->api_token])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url']);
});

it('can list webhooks', function () {
    WebhookSubscription::create([
        'agent_id' => $this->agent->id,
        'url' => 'https://example.com/webhook',
        'events' => ['memory.shared'],
        'secret' => 'secret',
    ]);

    $response = $this->getJson('/api/v1/webhooks', [
        'Authorization' => 'Bearer '.$this->agent->api_token,
    ]);

    $response->assertOk();
    expect(count($response->json('data')))->toBe(1);
});

it('can delete a webhook', function () {
    $webhook = WebhookSubscription::create([
        'agent_id' => $this->agent->id,
        'url' => 'https://example.com/webhook',
        'events' => ['memory.shared'],
        'secret' => 'secret',
    ]);

    $this->deleteJson('/api/v1/webhooks/'.$webhook->id, [], [
        'Authorization' => 'Bearer '.$this->agent->api_token,
    ])->assertOk();

    expect(WebhookSubscription::count())->toBe(0);
});

it('queues a webhook job when a memory is shared', function () {
    Queue::fake();

    $sender = Agent::factory()->create(['owner_id' => $this->owner->id]);
    $memory = Memory::factory()->create([
        'agent_id' => $sender->id,
        'value' => 'shared content',
    ]);

    $webhook = WebhookSubscription::create([
        'agent_id' => $this->agent->id,
        'url' => 'https://example.com/webhook',
        'events' => ['memory.shared'],
        'secret' => 'secret',
    ]);

    // This listener should be called when MemoryShared is fired
    \App\Events\MemoryShared::dispatch($memory, $this->agent);

    Queue::assertPushed(DispatchWebhook::class, function ($job) use ($webhook) {
        return $job->subscription->id === $webhook->id && $job->event === 'memory.shared';
    });
});

it('dispatches the webhook correctly via HTTP', function () {
    Http::fake([
        '*' => Http::response('ok', 200),
    ]);

    $webhook = WebhookSubscription::create([
        'agent_id' => $this->agent->id,
        'url' => 'https://example.com/webhook',
        'events' => ['memory.shared'],
        'secret' => 'secret_key',
    ]);

    $job = new DispatchWebhook($webhook, 'memory.shared', ['foo' => 'bar']);
    $job->handle();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example.com/webhook'
            && $request->hasHeader('Webhook-Signature')
            && $request['event'] === 'memory.shared'
            && $request['data']['foo'] === 'bar';
    });

    expect(\App\Models\WebhookDelivery::count())->toBe(1);
    expect($webhook->fresh()->failure_count)->toBe(0);
});

it('increments failure count and deactivates after 10 failures', function () {
    Http::fake([
        '*' => Http::response('error', 500),
    ]);

    $webhook = WebhookSubscription::create([
        'agent_id' => $this->agent->id,
        'url' => 'https://example.com/webhook',
        'events' => ['memory.shared'],
        'secret' => 'secret_key',
        'failure_count' => 9,
    ]);

    $job = new DispatchWebhook($webhook, 'memory.shared', ['foo' => 'bar']);

    try {
        $job->handle();
    } catch (\Exception $e) {
        // HTTP exception thrown
    }

    $webhook->refresh();
    expect($webhook->failure_count)->toBe(10);
    expect($webhook->is_active)->toBeFalse();
});
