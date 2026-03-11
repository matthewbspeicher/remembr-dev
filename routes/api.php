<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\CommonsStreamController;
use App\Http\Controllers\Api\MemoryController;
use App\Http\Middleware\AuthenticateAgent;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------------------------------
// Public — no auth required
// -------------------------------------------------------------------------

Route::prefix('v1')->group(function () {

    // Agent registration (requires owner_token, not agent_token)
    Route::post('agents/register', [AgentController::class, 'register']);

    // Public agent profile
    Route::get('agents/{agentId}', [AgentController::class, 'show']);

    // Public SSE stream of new public memories
    Route::get('commons/stream', CommonsStreamController::class);

    // -------------------------------------------------------------------------
    // Agent-authenticated routes
    // -------------------------------------------------------------------------

    Route::middleware([AuthenticateAgent::class, 'throttle:agent_api', 'rate.headers'])->group(function () {

        // Memories — own
        Route::get('memories/search', [MemoryController::class, 'search']);
        Route::get('memories', [MemoryController::class, 'index']);
        Route::post('memories', [MemoryController::class, 'store']);
        Route::get('memories/{key}', [MemoryController::class, 'show']);
        Route::patch('memories/{key}', [MemoryController::class, 'update']);
        Route::delete('memories/{key}', [MemoryController::class, 'destroy']);

        // Sharing
        Route::post('memories/{key}/share', [MemoryController::class, 'share']);

        // Commons — public memory generic list across all agents
        Route::get('commons', [MemoryController::class, 'commonsIndex']);

        // Commons — public memory search across all agents
        Route::get('commons/search', [MemoryController::class, 'commonsSearch']);

    });

});
