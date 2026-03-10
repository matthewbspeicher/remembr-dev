<?php

use App\Models\Agent;
use App\Models\Memory;
use App\Models\User;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeOwner(): User
{
    return User::factory()->create([
        'api_token' => 'owner_test_token',
    ]);
}

function makeAgent(User $owner, array $overrides = []): Agent
{
    return Agent::factory()->create(array_merge([
        'owner_id'  => $owner->id,
        'api_token' => 'amc_test_agent_token',
    ], $overrides));
}

function withAgent(Agent $agent): array
{
    return ['Authorization' => "Bearer {$agent->api_token}"];
}

// Mock embeddings for all tests — we don't want real OpenAI calls
beforeEach(function () {
    $this->mock(EmbeddingService::class, function ($mock) {
        $mock->shouldReceive('embed')
            ->andReturn(array_fill(0, 1536, 0.1));

        $mock->shouldReceive('embedBatch')
            ->andReturn([array_fill(0, 1536, 0.1)]);
    });
});

// ---------------------------------------------------------------------------
// Agent Registration
// ---------------------------------------------------------------------------

describe('POST /v1/agents/register', function () {

    it('registers an agent with a valid owner token', function () {
        $owner = makeOwner();

        $response = $this->postJson('/api/v1/agents/register', [
            'name'        => 'TestBot',
            'description' => 'A test agent',
            'owner_token' => 'owner_test_token',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['agent_id', 'agent_token', 'message'])
            ->assertJsonFragment(['message' => 'Agent registered. Store your agent_token — it will not be shown again.']);

        expect($response->json('agent_token'))->toStartWith('amc_');
    });

    it('rejects registration with an invalid owner token', function () {
        $response = $this->postJson('/api/v1/agents/register', [
            'name'        => 'TestBot',
            'owner_token' => 'bad_token',
        ]);

        $response->assertUnauthorized();
    });

    it('requires a name', function () {
        makeOwner();

        $response = $this->postJson('/api/v1/agents/register', [
            'owner_token' => 'owner_test_token',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

});

// ---------------------------------------------------------------------------
// Memory — Store
// ---------------------------------------------------------------------------

describe('POST /v1/memories', function () {

    it('stores a private memory', function () {
        $agent = makeAgent(makeOwner());

        $response = $this->postJson('/api/v1/memories', [
            'key'        => 'user-preference',
            'value'      => 'The user prefers dark mode.',
            'visibility' => 'private',
        ], withAgent($agent));

        $response->assertCreated()
            ->assertJsonFragment([
                'key'        => 'user-preference',
                'value'      => 'The user prefers dark mode.',
                'visibility' => 'private',
            ]);

        expect(Memory::where('agent_id', $agent->id)->count())->toBe(1);
    });

    it('stores a public memory', function () {
        $agent = makeAgent(makeOwner());

        $response = $this->postJson('/api/v1/memories', [
            'value'      => 'The sky is blue.',
            'visibility' => 'public',
        ], withAgent($agent));

        $response->assertCreated()
            ->assertJsonFragment(['visibility' => 'public']);
    });

    it('upserts when key already exists', function () {
        $agent = makeAgent(makeOwner());

        $this->postJson('/api/v1/memories', [
            'key'   => 'my-key',
            'value' => 'original value',
        ], withAgent($agent));

        $this->postJson('/api/v1/memories', [
            'key'   => 'my-key',
            'value' => 'updated value',
        ], withAgent($agent));

        expect(Memory::where('agent_id', $agent->id)->count())->toBe(1);
        expect(Memory::where('agent_id', $agent->id)->first()->value)->toBe('updated value');
    });

    it('rejects unauthenticated requests', function () {
        $this->postJson('/api/v1/memories', [
            'value' => 'some value',
        ])->assertUnauthorized();
    });

    it('requires a value', function () {
        $agent = makeAgent(makeOwner());

        $this->postJson('/api/v1/memories', [], withAgent($agent))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);
    });

    it('stores metadata', function () {
        $agent = makeAgent(makeOwner());

        $response = $this->postJson('/api/v1/memories', [
            'value'    => 'remembered something',
            'metadata' => ['tags' => ['important', 'user']],
        ], withAgent($agent));

        $response->assertCreated()
            ->assertJsonFragment(['metadata' => ['tags' => ['important', 'user']]]);
    });

});

// ---------------------------------------------------------------------------
// Memory — Retrieve
// ---------------------------------------------------------------------------

describe('GET /v1/memories/{key}', function () {

    it('retrieves a memory by key', function () {
        $agent = makeAgent(makeOwner());

        Memory::factory()->create([
            'agent_id' => $agent->id,
            'key'      => 'my-key',
            'value'    => 'my value',
        ]);

        $this->getJson('/api/v1/memories/my-key', withAgent($agent))
            ->assertOk()
            ->assertJsonFragment(['key' => 'my-key', 'value' => 'my value']);
    });

    it('returns 404 for unknown key', function () {
        $agent = makeAgent(makeOwner());

        $this->getJson('/api/v1/memories/nonexistent', withAgent($agent))
            ->assertNotFound();
    });

    it('cannot retrieve another agent\'s private memory', function () {
        $owner = makeOwner();
        $agentA = makeAgent($owner, ['api_token' => 'amc_agent_a']);
        $agentB = makeAgent($owner, ['api_token' => 'amc_agent_b']);

        Memory::factory()->create([
            'agent_id'   => $agentA->id,
            'key'        => 'secret',
            'value'      => 'secret value',
            'visibility' => 'private',
        ]);

        $this->getJson('/api/v1/memories/secret', withAgent($agentB))
            ->assertNotFound();
    });

    it('does not return expired memories', function () {
        $agent = makeAgent(makeOwner());

        Memory::factory()->create([
            'agent_id'   => $agent->id,
            'key'        => 'expired-key',
            'expires_at' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/memories/expired-key', withAgent($agent))
            ->assertNotFound();
    });

});

// ---------------------------------------------------------------------------
// Memory — Update
// ---------------------------------------------------------------------------

describe('PATCH /v1/memories/{key}', function () {

    it('updates a memory value', function () {
        $agent = makeAgent(makeOwner());

        Memory::factory()->create([
            'agent_id' => $agent->id,
            'key'      => 'updatable',
            'value'    => 'old value',
        ]);

        $this->patchJson('/api/v1/memories/updatable', [
            'value' => 'new value',
        ], withAgent($agent))
            ->assertOk()
            ->assertJsonFragment(['value' => 'new value']);
    });

    it('updates visibility', function () {
        $agent = makeAgent(makeOwner());

        Memory::factory()->create([
            'agent_id'   => $agent->id,
            'key'        => 'private-key',
            'visibility' => 'private',
        ]);

        $this->patchJson('/api/v1/memories/private-key', [
            'visibility' => 'public',
        ], withAgent($agent))
            ->assertOk()
            ->assertJsonFragment(['visibility' => 'public']);
    });

});

// ---------------------------------------------------------------------------
// Memory — Delete
// ---------------------------------------------------------------------------

describe('DELETE /v1/memories/{key}', function () {

    it('deletes a memory', function () {
        $agent = makeAgent(makeOwner());

        Memory::factory()->create([
            'agent_id' => $agent->id,
            'key'      => 'deletable',
        ]);

        $this->deleteJson('/api/v1/memories/deletable', [], withAgent($agent))
            ->assertOk()
            ->assertJsonFragment(['message' => 'Memory deleted.']);

        expect(Memory::where('agent_id', $agent->id)->count())->toBe(0);
    });

});

// ---------------------------------------------------------------------------
// Memory — Semantic Search
// ---------------------------------------------------------------------------

describe('GET /v1/memories/search', function () {

    it('returns search results for the agent', function () {
        $agent = makeAgent(makeOwner());

        Memory::factory(3)->create(['agent_id' => $agent->id]);

        $this->getJson('/api/v1/memories/search?q=test+query', withAgent($agent))
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'key', 'value', 'similarity']]]);
    });

    it('requires a query parameter', function () {
        $agent = makeAgent(makeOwner());

        $this->getJson('/api/v1/memories/search', withAgent($agent))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    });

});

// ---------------------------------------------------------------------------
// Commons Search
// ---------------------------------------------------------------------------

describe('GET /v1/commons/search', function () {

    it('returns public memories from all agents', function () {
        $owner = makeOwner();
        $agentA = makeAgent($owner, ['api_token' => 'amc_agent_a']);
        $agentB = makeAgent($owner, ['api_token' => 'amc_agent_b']);

        Memory::factory()->create([
            'agent_id'   => $agentB->id,
            'visibility' => 'public',
            'value'      => 'public knowledge',
        ]);

        Memory::factory()->create([
            'agent_id'   => $agentB->id,
            'visibility' => 'private',
            'value'      => 'private knowledge',
        ]);

        $response = $this->getJson('/api/v1/commons/search?q=knowledge', withAgent($agentA));

        $response->assertOk();

        $values = collect($response->json('data'))->pluck('value');
        expect($values)->toContain('public knowledge')
            ->not->toContain('private knowledge');
    });

});

// ---------------------------------------------------------------------------
// Sharing
// ---------------------------------------------------------------------------

describe('POST /v1/memories/{key}/share', function () {

    it('shares a memory with another agent', function () {
        $owner = makeOwner();
        $agentA = makeAgent($owner, ['api_token' => 'amc_agent_a']);
        $agentB = makeAgent($owner, ['api_token' => 'amc_agent_b']);

        Memory::factory()->create([
            'agent_id' => $agentA->id,
            'key'      => 'shared-key',
            'value'    => 'shared value',
        ]);

        $this->postJson('/api/v1/memories/shared-key/share', [
            'agent_id' => $agentB->id,
        ], withAgent($agentA))
            ->assertOk();
    });

    it('rejects sharing a non-existent memory', function () {
        $owner = makeOwner();
        $agentA = makeAgent($owner, ['api_token' => 'amc_agent_a']);
        $agentB = makeAgent($owner, ['api_token' => 'amc_agent_b']);

        $this->postJson('/api/v1/memories/nonexistent/share', [
            'agent_id' => $agentB->id,
        ], withAgent($agentA))
            ->assertNotFound();
    });

});
