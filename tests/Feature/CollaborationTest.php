<?php

use App\Models\Workspace;
use App\Models\WorkspaceEvent;
use App\Services\EmbeddingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->mock(EmbeddingService::class, function ($mock) {
        $mock->shouldReceive('embed')
            ->andReturn(array_fill(0, 1536, 0.1));
        $mock->shouldReceive('embedBatch')
            ->andReturn([array_fill(0, 1536, 0.1)]);
    });
});

// ---------------------------------------------------------------------------
// Helper
// ---------------------------------------------------------------------------

function createWorkspaceWithAgent(): array
{
    $user = makeOwner();
    $agent = makeAgent($user);
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $agent->workspaces()->attach($workspace->id);

    return [$workspace, $agent, $user];
}

// ===========================================================================
// Phase 1: Presence
// ===========================================================================

describe('Presence', function () {
    it('lists workspace presence', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/presence", withAgent($agent));

        $response->assertOk();
        expect($response->json('data'))->toBeArray();
    });

    it('shows agent presence', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/presence/{$agent->id}", withAgent($agent));

        $response->assertOk();
        expect($response->json('agent_id'))->toBe($agent->id);
    });

    it('sends heartbeat', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/presence/heartbeat", [], withAgent($agent));

        $response->assertOk();
        expect($response->json('status'))->toBe('online');
    });

    it('marks agent offline', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/presence/offline", [], withAgent($agent));

        $response->assertOk();
        expect($response->json('status'))->toBe('offline');
    });

    it('rejects presence for agent not in workspace', function () {
        [$workspace] = createWorkspaceWithAgent();
        $otherUser = makeOwner();
        $otherAgent = makeAgent($otherUser);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/presence", withAgent($otherAgent));

        $response->assertForbidden();
    });
});

// ===========================================================================
// Phase 2: Event Subscriptions
// ===========================================================================

describe('Event Subscriptions', function () {
    it('lists subscriptions', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/subscriptions", withAgent($agent));

        $response->assertOk();
        expect($response->json('data'))->toBeArray();
    });

    it('creates a subscription', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/subscriptions", [
            'event_pattern' => 'memory.*',
        ], withAgent($agent));

        $response->assertCreated();
        expect($response->json('event_pattern'))->toBe('memory.*');
        expect($response->json('agent_id'))->toBe($agent->id);
    });

    it('updates a subscription', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $sub = $workspace->subscriptions()->create([
            'agent_id' => $agent->id,
            'event_pattern' => 'memory.created',
        ]);

        $response = $this->patchJson("/api/v1/workspaces/{$workspace->id}/subscriptions/{$sub->id}", [
            'event_pattern' => 'memory.*',
            'is_active' => false,
        ], withAgent($agent));

        $response->assertOk();
        expect($response->json('event_pattern'))->toBe('memory.*');
        expect($response->json('is_active'))->toBeFalse();
    });

    it('deletes a subscription', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $sub = $workspace->subscriptions()->create([
            'agent_id' => $agent->id,
            'event_pattern' => 'memory.*',
        ]);

        $response = $this->deleteJson("/api/v1/workspaces/{$workspace->id}/subscriptions/{$sub->id}", [], withAgent($agent));

        $response->assertOk();
        $this->assertDatabaseMissing('workspace_subscriptions', ['id' => $sub->id]);
    });

    it('polls workspace events', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $workspace->subscriptions()->create([
            'agent_id' => $agent->id,
            'event_pattern' => '*',
        ]);

        WorkspaceEvent::dispatch($workspace->id, WorkspaceEvent::TYPE_MEMORY_CREATED, $agent->id, [
            'memory_id' => 'test-id',
        ]);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/events", withAgent($agent));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('filters events by pattern', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $workspace->subscriptions()->create([
            'agent_id' => $agent->id,
            'event_pattern' => 'task.*',
        ]);

        WorkspaceEvent::dispatch($workspace->id, WorkspaceEvent::TYPE_MEMORY_CREATED, $agent->id, [
            'memory_id' => 'test-id',
        ]);

        WorkspaceEvent::dispatch($workspace->id, WorkspaceEvent::TYPE_TASK_CREATED, $agent->id, [
            'task_id' => 'task-id',
        ]);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/events", withAgent($agent));

        $response->assertOk();
        $events = $response->json('data');
        expect($events)->toHaveCount(1);
        expect($events[0]['event_type'])->toBe('task.created');
    });
});

// ===========================================================================
// Phase 3: @Mentions
// ===========================================================================

describe('@Mentions', function () {
    it('lists mentions', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();
        [$otherWorkspace, $otherAgent] = createWorkspaceWithAgent();

        $workspace->agents()->attach($otherAgent->id);

        $workspace->mentions()->create([
            'from_agent_id' => $agent->id,
            'to_agent_id' => $otherAgent->id,
            'workspace_id' => $workspace->id,
            'content' => 'Can you help with this?',
        ]);

        $response = $this->getJson('/api/v1/mentions', withAgent($otherAgent));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('lists received mentions', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();
        [$otherWorkspace, $otherAgent] = createWorkspaceWithAgent();

        $workspace->agents()->attach($otherAgent->id);

        $workspace->mentions()->create([
            'from_agent_id' => $agent->id,
            'to_agent_id' => $otherAgent->id,
            'workspace_id' => $workspace->id,
            'content' => 'Can you help with this?',
        ]);

        $response = $this->getJson('/api/v1/mentions/received', withAgent($otherAgent));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
    });

    it('creates a mention', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();
        [$otherWorkspace, $otherAgent] = createWorkspaceWithAgent();

        $workspace->agents()->attach($otherAgent->id);

        $response = $this->postJson('/api/v1/mentions', [
            'to_agent_id' => $otherAgent->id,
            'workspace_id' => $workspace->id,
            'content' => 'Can you help with this?',
        ], withAgent($agent));

        $response->assertCreated();
        expect($response->json('content'))->toBe('Can you help with this?');
        expect($response->json('status'))->toBe('pending');
    });

    it('responds to a mention', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();
        [$otherWorkspace, $otherAgent] = createWorkspaceWithAgent();

        $workspace->agents()->attach($otherAgent->id);

        $mention = $workspace->mentions()->create([
            'from_agent_id' => $agent->id,
            'to_agent_id' => $otherAgent->id,
            'workspace_id' => $workspace->id,
            'content' => 'Can you help with this?',
        ]);

        $response = $this->postJson("/api/v1/mentions/{$mention->id}/respond", [
            'response_content' => 'Sure, I can help!',
            'accept' => true,
        ], withAgent($otherAgent));

        $response->assertOk();
        expect($response->json('status'))->toBe('accepted');
    });
});

// ===========================================================================
// Phase 4: Shared Tasks
// ===========================================================================

describe('Shared Tasks', function () {
    it('lists workspace tasks', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/tasks", withAgent($agent));

        $response->assertOk();
        expect($response->json('data'))->toBeArray();
    });

    it('creates a task', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/tasks", [
            'title' => 'Implement feature X',
            'description' => 'Full details here',
            'priority' => 'high',
        ], withAgent($agent));

        $response->assertCreated();
        expect($response->json('title'))->toBe('Implement feature X');
        expect($response->json('status'))->toBe('pending');
        expect($response->json('priority'))->toBe('high');
    });

    it('shows a task', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $task = $workspace->tasks()->create([
            'created_by_agent_id' => $agent->id,
            'title' => 'Test task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/tasks/{$task->id}", withAgent($agent));

        $response->assertOk();
        expect($response->json('title'))->toBe('Test task');
    });

    it('updates a task', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $task = $workspace->tasks()->create([
            'created_by_agent_id' => $agent->id,
            'title' => 'Test task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $response = $this->patchJson("/api/v1/workspaces/{$workspace->id}/tasks/{$task->id}", [
            'title' => 'Updated task title',
            'priority' => 'high',
        ], withAgent($agent));

        $response->assertOk();
        expect($response->json('title'))->toBe('Updated task title');
        expect($response->json('priority'))->toBe('high');
    });

    it('assigns a task', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();
        [$otherWorkspace, $otherAgent] = createWorkspaceWithAgent();

        $workspace->agents()->attach($otherAgent->id);

        $task = $workspace->tasks()->create([
            'created_by_agent_id' => $agent->id,
            'title' => 'Test task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/tasks/{$task->id}/assign", [
            'agent_id' => $otherAgent->id,
        ], withAgent($agent));

        $response->assertOk();
        expect($response->json('assigned_to_agent_id'))->toBe($otherAgent->id);
    });

    it('updates task status', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $task = $workspace->tasks()->create([
            'created_by_agent_id' => $agent->id,
            'title' => 'Test task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $response = $this->postJson("/api/v1/workspaces/{$workspace->id}/tasks/{$task->id}/status", [
            'status' => 'in_progress',
        ], withAgent($agent));

        $response->assertOk();
        expect($response->json('status'))->toBe('in_progress');
    });

    it('deletes a task', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $task = $workspace->tasks()->create([
            'created_by_agent_id' => $agent->id,
            'title' => 'Test task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);

        $response = $this->deleteJson("/api/v1/workspaces/{$workspace->id}/tasks/{$task->id}", [], withAgent($agent));

        $response->assertOk();
        $this->assertDatabaseMissing('workspace_tasks', ['id' => $task->id]);
    });

    it('filters tasks by status', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $workspace->tasks()->create([
            'created_by_agent_id' => $agent->id,
            'title' => 'Pending task',
            'status' => 'pending',
            'priority' => 'medium',
        ]);
        $workspace->tasks()->create([
            'created_by_agent_id' => $agent->id,
            'title' => 'In progress task',
            'status' => 'in_progress',
            'priority' => 'medium',
        ]);

        $response = $this->getJson("/api/v1/workspaces/{$workspace->id}/tasks?status=pending", withAgent($agent));

        $response->assertOk();
        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.title'))->toBe('Pending task');
    });
});

// ===========================================================================
// Phase 2: Workspace Events (Memory Lifecycle)
// ===========================================================================

describe('Workspace Events on Memory Lifecycle', function () {
    it('dispatches memory.created event when storing workspace memory', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $response = $this->postJson('/api/v1/memories', [
            'value' => 'A shared memory',
            'visibility' => 'workspace',
            'workspace_id' => $workspace->id,
        ], withAgent($agent));

        $response->assertCreated();

        $this->assertDatabaseHas('workspace_events', [
            'workspace_id' => $workspace->id,
            'event_type' => WorkspaceEvent::TYPE_MEMORY_CREATED,
            'actor_agent_id' => $agent->id,
        ]);
    });

    it('dispatches memory.updated event when updating workspace memory', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $memory = Memory::factory()->create([
            'agent_id' => $agent->id,
            'workspace_id' => $workspace->id,
            'visibility' => 'workspace',
            'value' => 'Original value',
        ]);

        $response = $this->patchJson("/api/v1/memories/{$memory->key}", [
            'value' => 'Updated value',
        ], withAgent($agent));

        $response->assertOk();

        $this->assertDatabaseHas('workspace_events', [
            'workspace_id' => $workspace->id,
            'event_type' => WorkspaceEvent::TYPE_MEMORY_UPDATED,
            'actor_agent_id' => $agent->id,
        ]);
    });

    it('dispatches memory.deleted event when deleting workspace memory', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $memory = Memory::factory()->create([
            'agent_id' => $agent->id,
            'workspace_id' => $workspace->id,
            'visibility' => 'workspace',
        ]);

        $response = $this->deleteJson("/api/v1/memories/{$memory->key}", [], withAgent($agent));

        $response->assertOk();

        $this->assertDatabaseHas('workspace_events', [
            'workspace_id' => $workspace->id,
            'event_type' => WorkspaceEvent::TYPE_MEMORY_DELETED,
            'actor_agent_id' => $agent->id,
        ]);
    });

    it('does not dispatch workspace events for non-workspace memories', function () {
        [$workspace, $agent] = createWorkspaceWithAgent();

        $response = $this->postJson('/api/v1/memories', [
            'value' => 'A private memory',
            'visibility' => 'private',
        ], withAgent($agent));

        $response->assertCreated();

        $this->assertDatabaseMissing('workspace_events', [
            'event_type' => WorkspaceEvent::TYPE_MEMORY_CREATED,
        ]);
    });
});
