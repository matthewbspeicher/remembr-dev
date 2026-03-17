<?php

use App\Http\Controllers\Api\AchievementController;
use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\CommonsPollController;
use App\Http\Controllers\Api\CommonsStreamController;
use App\Http\Controllers\Api\MemoryController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Middleware\AuthenticateAgent;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------------------------------
// Public — no auth required
// -------------------------------------------------------------------------

Route::prefix('v1')->middleware(['throttle:api', 'rate.headers'])->group(function () {

    // Agent registration (requires owner_token, not agent_token)
    Route::post('agents/register', [AgentController::class, 'register']);

    // Public agent directory
    Route::get('agents/directory', [AgentController::class, 'directory']);

    // Public agent profile (UUID constraint prevents swallowing literal paths)
    Route::get('agents/{agentId}', [AgentController::class, 'show'])->where('agentId', '[0-9a-f\-]{36}');

    // Badges
    Route::get('badges/agent/{agentId}/memories', [\App\Http\Controllers\Api\BadgeController::class, 'memories'])->whereUuid('agentId');
    Route::get('badges/agent/{agentId}/status', [\App\Http\Controllers\Api\BadgeController::class, 'status'])->whereUuid('agentId');

    // Platform stats (public, no auth)
    Route::get('stats', StatsController::class);

    // Public SSE stream of new public memories
    Route::get('commons/poll', CommonsPollController::class);
    // SSE stream disabled — causes worker exhaustion under FrankenPHP/Octane
    // Route::get('commons/stream', CommonsStreamController::class);

    // -------------------------------------------------------------------------
    // Agent-authenticated routes
    // -------------------------------------------------------------------------

    Route::middleware([AuthenticateAgent::class, 'plan.limits', 'throttle:agent_api'])->group(function () {

        // Agent self-service
        Route::get('agents/me', [AgentController::class, 'me']);
        Route::patch('agents/me', [AgentController::class, 'update']);

        // Achievements
        Route::get('agents/me/achievements', [AchievementController::class, 'index']);

        // Memories — own
        Route::post('memories/compact', [MemoryController::class, 'compact']);
        Route::get('memories/search', [MemoryController::class, 'search']);
        Route::get('memories', [MemoryController::class, 'index']);
        Route::post('memories', [MemoryController::class, 'store']);
        Route::get('memories/{key}', [MemoryController::class, 'show']);
        Route::patch('memories/{key}', [MemoryController::class, 'update']);
        Route::delete('memories/{key}', [MemoryController::class, 'destroy']);

        // Sharing
        Route::post('/memories/{key}/share', [MemoryController::class, 'share']);

        // Feedback
        Route::post('/memories/{key}/feedback', [MemoryController::class, 'feedback']);

        // Session extraction
        Route::post('sessions/extract', [SessionController::class, 'extract']);

        // Workspaces
        Route::get('/workspaces', [\App\Http\Controllers\Api\WorkspaceController::class, 'index']);
        Route::post('/workspaces', [\App\Http\Controllers\Api\WorkspaceController::class, 'store']);
        Route::post('/workspaces/{id}/join', [\App\Http\Controllers\Api\WorkspaceController::class, 'join']);

        // Webhooks
        Route::get('/webhooks', [\App\Http\Controllers\Api\WebhookController::class, 'index']);
        Route::post('/webhooks', [\App\Http\Controllers\Api\WebhookController::class, 'store']);
        Route::delete('/webhooks/{id}', [\App\Http\Controllers\Api\WebhookController::class, 'destroy']);
        Route::post('/webhooks/{id}/test', [\App\Http\Controllers\Api\WebhookController::class, 'test']);

        // Commons — public memory generic list across all agents
        Route::get('commons', [MemoryController::class, 'commonsIndex']);

        // Commons — public memory search across all agents
        Route::get('commons/search', [MemoryController::class, 'commonsSearch']);

        // Arena
        Route::get('arena/profile', [\App\Http\Controllers\Api\ArenaProfileController::class, 'show']);
        Route::put('arena/profile', [\App\Http\Controllers\Api\ArenaProfileController::class, 'update']);
        Route::patch('arena/profile', [\App\Http\Controllers\Api\ArenaProfileController::class, 'update']);

    });

});
