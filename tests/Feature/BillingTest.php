<?php

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeProUser(array $overrides = []): User
{
    $user = makeOwner(array_merge([
        'stripe_id' => 'cus_test_' . Str::random(10),
    ], $overrides));

    $subscription = $user->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_' . Str::random(10),
        'stripe_status' => 'active',
        'stripe_price' => config('stripe.pro_price_id') ?: 'price_test',
        'quantity' => 1,
    ]);

    $subscription->items()->create([
        'stripe_id' => 'si_test_' . Str::random(10),
        'stripe_product' => 'prod_test',
        'stripe_price' => config('stripe.pro_price_id') ?: 'price_test',
        'quantity' => 1,
    ]);

    return $user->fresh();
}

// ---------------------------------------------------------------------------
// Plan Helper Tests
// ---------------------------------------------------------------------------

describe('plan helpers', function () {
    it('returns false for isPro when user has no subscription', function () {
        $user = makeOwner();
        expect($user->isPro())->toBeFalse();
    });

    it('returns true for isPro when user has active subscription', function () {
        $user = makeProUser();
        expect($user->isPro())->toBeTrue();
    });

    it('returns 3 max agents for free user', function () {
        $user = makeOwner();
        expect($user->maxAgents())->toBe(3);
    });

    it('returns PHP_INT_MAX max agents for pro user', function () {
        $user = makeProUser();
        expect($user->maxAgents())->toBe(PHP_INT_MAX);
    });

    it('returns 1000 max memories per agent for free user', function () {
        $user = makeOwner();
        expect($user->maxMemoriesPerAgent())->toBe(1000);
    });

    it('returns 10000 max memories per agent for pro user', function () {
        $user = makeProUser();
        expect($user->maxMemoriesPerAgent())->toBe(10000);
    });

    it('returns false for canCreateWorkspace for free user', function () {
        $user = makeOwner();
        expect($user->canCreateWorkspace())->toBeFalse();
    });

    it('returns true for canCreateWorkspace for pro user', function () {
        $user = makeProUser();
        expect($user->canCreateWorkspace())->toBeTrue();
    });

    it('returns true for isDowngraded when not pro but has more than 3 agents', function () {
        $user = makeOwner();
        for ($i = 0; $i < 4; $i++) {
            makeAgent($user, ['api_token' => 'amc_' . \Illuminate\Support\Str::random(40)]);
        }
        expect($user->isDowngraded())->toBeTrue();
    });

    it('returns true for isDowngraded when not pro but owns workspaces', function () {
        $user = makeOwner();
        Workspace::factory()->create(['owner_id' => $user->id]);
        expect($user->isDowngraded())->toBeTrue();
    });

    it('returns false for isDowngraded for pro user with many agents', function () {
        $user = makeProUser();
        for ($i = 0; $i < 5; $i++) {
            makeAgent($user, ['api_token' => 'amc_' . \Illuminate\Support\Str::random(40)]);
        }
        expect($user->isDowngraded())->toBeFalse();
    });

    it('returns false for isDowngraded for free user within limits and no workspaces', function () {
        $user = makeOwner();
        makeAgent($user);
        expect($user->isDowngraded())->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// Agent Cap Enforcement
// ---------------------------------------------------------------------------

describe('agent creation cap', function () {
    it('allows free user to register 3 agents via API', function () {
        $owner = makeOwner();
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/api/v1/agents/register', [
                'name' => "Agent $i",
                'owner_token' => $owner->api_token,
            ]);
            $response->assertStatus(201);
        }
    });

    it('blocks free user from registering 4th agent via API', function () {
        $owner = makeOwner();
        for ($i = 0; $i < 3; $i++) {
            makeAgent($owner, ['api_token' => 'amc_' . \Illuminate\Support\Str::random(40)]);
        }

        $response = $this->postJson('/api/v1/agents/register', [
            'name' => 'Agent 4',
            'owner_token' => $owner->api_token,
        ]);
        $response->assertStatus(403);
        $response->assertJsonFragment(['error' => 'Agent limit reached. Upgrade to Pro for unlimited agents.']);
    });

    it('allows pro user to register unlimited agents via API', function () {
        $owner = makeProUser();
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/agents/register', [
                'name' => "Agent $i",
                'owner_token' => $owner->api_token,
            ]);
            $response->assertStatus(201);
        }
    });

    it('blocks free user from registering 4th agent via dashboard', function () {
        $owner = makeOwner();
        for ($i = 0; $i < 3; $i++) {
            makeAgent($owner, ['api_token' => 'amc_' . \Illuminate\Support\Str::random(40)]);
        }

        $response = $this->actingAs($owner)->post('/dashboard/agents', [
            'name' => 'Agent 4',
        ]);
        $response->assertSessionHasErrors('name');
    });
});
