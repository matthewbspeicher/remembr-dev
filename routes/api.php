<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\CommonsPollController;
use App\Http\Controllers\Api\CommonsStreamController;
use App\Http\Controllers\Api\MemoryController;
use App\Http\Middleware\AuthenticateAgent;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------------------------------
// Public — no auth required
// -------------------------------------------------------------------------

Route::prefix('v1')->middleware(['throttle:api', 'rate.headers'])->group(function () {

    // Agent registration (requires owner_token, not agent_token)
    Route::post('agents/register', [AgentController::class, 'register']);

    // Public agent profile
    Route::get('agents/{agentId}', [AgentController::class, 'show']);

    // Badges
    Route::get('badges/agent/{agentId}/memories', [\App\Http\Controllers\Api\BadgeController::class, 'memories'])->whereUuid('agentId');
    Route::get('badges/agent/{agentId}/status', [\App\Http\Controllers\Api\BadgeController::class, 'status'])->whereUuid('agentId');

    // Public SSE stream of new public memories
    Route::get('commons/poll', CommonsPollController::class);
    // SSE stream disabled — causes worker exhaustion under FrankenPHP/Octane
    // Route::get('commons/stream', CommonsStreamController::class);

    // -------------------------------------------------------------------------
    // Agent-authenticated routes
    // -------------------------------------------------------------------------

    Route::middleware([AuthenticateAgent::class, 'throttle:agent_api'])->group(function () {

        // Memories — own
        Route::get('memories/search', [MemoryController::class, 'search']);
        Route::get('memories', [MemoryController::class, 'index']);
        Route::post('memories', [MemoryController::class, 'store']);
        Route::get('memories/{key}', [MemoryController::class, 'show']);
        Route::patch('memories/{key}', [MemoryController::class, 'update']);
        Route::delete('memories/{key}', [MemoryController::class, 'destroy']);

        // Sharing
        Route::post('/memories/{key}/share', [MemoryController::class, 'share']);

        // Webhooks
        Route::get('/webhooks', [\App\Http\Controllers\Api\WebhookController::class, 'index']);
        Route::post('/webhooks', [\App\Http\Controllers\Api\WebhookController::class, 'store']);
        Route::delete('/webhooks/{id}', [\App\Http\Controllers\Api\WebhookController::class, 'destroy']);
        Route::post('/webhooks/{id}/test', [\App\Http\Controllers\Api\WebhookController::class, 'test']);

        // Commons — public memory generic list across all agents
        Route::get('commons', [MemoryController::class, 'commonsIndex']);

        // Commons — public memory search across all agents
        Route::get('commons/search', [MemoryController::class, 'commonsSearch']);

    });

});
