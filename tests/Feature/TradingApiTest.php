<?php

use App\Models\Agent;
use App\Models\Memory;
use App\Models\Trade;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mock(EmbeddingService::class, function ($mock) {
        $mock->shouldReceive('embed')->andReturn(array_fill(0, 1536, 0.1));
        $mock->shouldReceive('embedBatch')->andReturn([array_fill(0, 1536, 0.1)]);
    });
});

// ---------------------------------------------------------------------------
// POST /v1/trading/trades — Record a trade
// ---------------------------------------------------------------------------

describe('POST /v1/trading/trades', function () {
    it('creates an entry trade', function () {
        $agent = makeAgent(makeOwner());

        $response = $this->postJson('/api/v1/trading/trades', [
            'ticker' => 'AAPL',
            'direction' => 'long',
            'entry_price' => 185.50,
            'quantity' => 100,
            'entry_at' => '2026-03-31T14:30:00Z',
            'strategy' => 'momentum',
            'confidence' => 0.85,
            'paper' => true,
            'fees' => 1.50,
        ], withAgent($agent));

        $response->assertCreated()
            ->assertJsonFragment([
                'ticker' => 'AAPL',
                'direction' => 'long',
                'status' => 'open',
            ]);

        $this->assertDatabaseHas('trades', [
            'agent_id' => $agent->id,
            'ticker' => 'AAPL',
            'direction' => 'long',
        ]);
    });

    it('closes a parent trade when exit quantity matches', function () {
        $agent = makeAgent(makeOwner());

        $parent = Trade::factory()->create([
            'agent_id' => $agent->id,
            'ticker' => 'AAPL',
            'direction' => 'long',
            'entry_price' => '100.00000000',
            'quantity' => '10.00000000',
            'paper' => true,
        ]);

        $response = $this->postJson('/api/v1/trading/trades', [
            'ticker' => 'AAPL',
            'direction' => 'short',
            'entry_price' => 110.00,
            'quantity' => 10,
            'entry_at' => '2026-03-31T16:00:00Z',
            'parent_trade_id' => $parent->id,
            'paper' => true,
        ], withAgent($agent));

        $response->assertCreated();

        $parent->refresh();
        expect($parent->status)->toBe('closed');
        expect((float) $parent->pnl)->toBeGreaterThan(0);
    });

    it('rejects exit with same direction as parent', function () {
        $agent = makeAgent(makeOwner());

        $parent = Trade::factory()->create([
            'agent_id' => $agent->id,
            'direction' => 'long',
            'paper' => true,
        ]);

        $response = $this->postJson('/api/v1/trading/trades', [
            'ticker' => $parent->ticker,
            'direction' => 'long',
            'entry_price' => 110.00,
            'quantity' => 10,
            'entry_at' => now()->toIso8601String(),
            'parent_trade_id' => $parent->id,
            'paper' => true,
        ], withAgent($agent));

        $response->assertUnprocessable();
    });

    it('rejects exit quantity exceeding remaining parent quantity', function () {
        $agent = makeAgent(makeOwner());

        $parent = Trade::factory()->create([
            'agent_id' => $agent->id,
            'ticker' => 'AAPL',
            'direction' => 'long',
            'entry_price' => '100.00000000',
            'quantity' => '10.00000000',
            'paper' => true,
        ]);

        Trade::factory()->create([
            'agent_id' => $agent->id,
            'ticker' => 'AAPL',
            'direction' => 'short',
            'entry_price' => '105.00000000',
            'quantity' => '7.00000000',
            'parent_trade_id' => $parent->id,
            'paper' => true,
        ]);

        $response = $this->postJson('/api/v1/trading/trades', [
            'ticker' => 'AAPL',
            'direction' => 'short',
            'entry_price' => 110.00,
            'quantity' => 5,
            'entry_at' => now()->toIso8601String(),
            'parent_trade_id' => $parent->id,
            'paper' => true,
        ], withAgent($agent));

        $response->assertUnprocessable();
    });

    it('rejects cross-agent parent_trade_id', function () {
        $owner = makeOwner();
        $agent1 = makeAgent($owner);
        $agent2 = makeAgent($owner, ['api_token' => 'amc_other_agent_token']);

        $parent = Trade::factory()->create([
            'agent_id' => $agent1->id,
            'direction' => 'long',
            'paper' => true,
        ]);

        $response = $this->postJson('/api/v1/trading/trades', [
            'ticker' => $parent->ticker,
            'direction' => 'short',
            'entry_price' => 110.00,
            'quantity' => 10,
            'entry_at' => now()->toIso8601String(),
            'parent_trade_id' => $parent->id,
            'paper' => true,
        ], withAgent($agent2));

        $response->assertUnprocessable();
    });

    it('rejects zero quantity', function () {
        $agent = makeAgent(makeOwner());

        $response = $this->postJson('/api/v1/trading/trades', [
            'ticker' => 'AAPL',
            'direction' => 'long',
            'entry_price' => 100,
            'quantity' => 0,
            'entry_at' => now()->toIso8601String(),
            'paper' => true,
        ], withAgent($agent));

        $response->assertUnprocessable();
    });

    it('requires authentication', function () {
        $response = $this->postJson('/api/v1/trading/trades', [
            'ticker' => 'AAPL',
            'direction' => 'long',
            'entry_price' => 100,
            'quantity' => 10,
            'entry_at' => now()->toIso8601String(),
        ]);

        $response->assertUnauthorized();
    });
});

// ---------------------------------------------------------------------------
// PATCH /v1/trading/trades/{id} — Metadata updates only
// ---------------------------------------------------------------------------

describe('PATCH /v1/trading/trades/{id}', function () {
    it('allows updating strategy and confidence', function () {
        $agent = makeAgent(makeOwner());
        $trade = Trade::factory()->create(['agent_id' => $agent->id]);

        $response = $this->patchJson("/api/v1/trading/trades/{$trade->id}", [
            'strategy' => 'new_strategy',
            'confidence' => 0.95,
        ], withAgent($agent));

        $response->assertOk();
        $trade->refresh();
        expect($trade->strategy)->toBe('new_strategy');
        expect($trade->confidence)->toBe(0.95);
    });

    it('rejects changes to immutable fields', function () {
        $agent = makeAgent(makeOwner());
        $trade = Trade::factory()->create(['agent_id' => $agent->id]);

        $response = $this->patchJson("/api/v1/trading/trades/{$trade->id}", [
            'entry_price' => 999.99,
        ], withAgent($agent));

        $response->assertUnprocessable();
    });

    it('allows cancelling an open trade', function () {
        $agent = makeAgent(makeOwner());
        $trade = Trade::factory()->create(['agent_id' => $agent->id, 'status' => 'open']);

        $response = $this->patchJson("/api/v1/trading/trades/{$trade->id}", [
            'status' => 'cancelled',
        ], withAgent($agent));

        $response->assertOk();
        $trade->refresh();
        expect($trade->status)->toBe('cancelled');
    });

    it('rejects closing via PATCH', function () {
        $agent = makeAgent(makeOwner());
        $trade = Trade::factory()->create(['agent_id' => $agent->id, 'status' => 'open']);

        $response = $this->patchJson("/api/v1/trading/trades/{$trade->id}", [
            'status' => 'closed',
        ], withAgent($agent));

        $response->assertUnprocessable();
    });
});

// ---------------------------------------------------------------------------
// GET /v1/trading/trades — List with filters
// ---------------------------------------------------------------------------

describe('GET /v1/trading/trades', function () {
    it('returns paginated trades for the agent', function () {
        $agent = makeAgent(makeOwner());
        Trade::factory()->count(3)->create(['agent_id' => $agent->id]);

        $response = $this->getJson('/api/v1/trading/trades', withAgent($agent));

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('filters by ticker', function () {
        $agent = makeAgent(makeOwner());
        Trade::factory()->create(['agent_id' => $agent->id, 'ticker' => 'AAPL']);
        Trade::factory()->create(['agent_id' => $agent->id, 'ticker' => 'TSLA']);

        $response = $this->getJson('/api/v1/trading/trades?ticker=AAPL', withAgent($agent));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('filters by status', function () {
        $agent = makeAgent(makeOwner());
        Trade::factory()->create(['agent_id' => $agent->id, 'status' => 'open']);
        Trade::factory()->create(['agent_id' => $agent->id, 'status' => 'closed', 'pnl' => 100]);

        $response = $this->getJson('/api/v1/trading/trades?status=open', withAgent($agent));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('does not show other agents trades', function () {
        $owner = makeOwner();
        $agent1 = makeAgent($owner);
        $agent2 = makeAgent($owner, ['api_token' => 'amc_other']);

        Trade::factory()->create(['agent_id' => $agent1->id]);
        Trade::factory()->create(['agent_id' => $agent2->id]);

        $response = $this->getJson('/api/v1/trading/trades', withAgent($agent1));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });
});

// ---------------------------------------------------------------------------
// DELETE /v1/trading/trades/{id}
// ---------------------------------------------------------------------------

describe('DELETE /v1/trading/trades/{id}', function () {
    it('soft deletes an open trade', function () {
        $agent = makeAgent(makeOwner());
        $trade = Trade::factory()->create(['agent_id' => $agent->id, 'status' => 'open']);

        $response = $this->deleteJson("/api/v1/trading/trades/{$trade->id}", [], withAgent($agent));

        $response->assertOk();
        expect($trade->fresh())->toBeNull();
        expect(Trade::withTrashed()->find($trade->id))->not->toBeNull();
    });

    it('rejects deleting a closed trade', function () {
        $agent = makeAgent(makeOwner());
        $trade = Trade::factory()->create(['agent_id' => $agent->id, 'status' => 'closed', 'pnl' => 100]);

        $response = $this->deleteJson("/api/v1/trading/trades/{$trade->id}", [], withAgent($agent));

        $response->assertUnprocessable();
    });

    it('rejects deleting a parent trade with children', function () {
        $agent = makeAgent(makeOwner());
        $parent = Trade::factory()->create(['agent_id' => $agent->id, 'status' => 'open']);
        Trade::factory()->create([
            'agent_id' => $agent->id,
            'parent_trade_id' => $parent->id,
        ]);

        $response = $this->deleteJson("/api/v1/trading/trades/{$parent->id}", [], withAgent($agent));

        $response->assertUnprocessable();
    });
});
