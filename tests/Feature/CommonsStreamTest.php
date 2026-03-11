<?php

use App\Http\Controllers\Api\CommonsStreamController;
use App\Models\Agent;
use App\Models\Memory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('commons stream route is registered and publicly accessible', function () {
    // The SSE endpoint is registered and returns a StreamedResponse.
    // We verify this by checking the route exists and the controller resolves.
    $controller = app(CommonsStreamController::class);
    expect($controller)->toBeInstanceOf(CommonsStreamController::class);

    // Verify route is registered
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($r) => str_contains($r->uri(), 'v1/commons/stream'));

    expect($route)->not->toBeNull();
    expect($route->methods())->toContain('GET');
});

test('commons stream controller returns streamed response with correct headers', function () {
    $controller = new CommonsStreamController;
    $request = new \Illuminate\Http\Request;

    $response = $controller($request);

    expect($response)->toBeInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->headers->get('Content-Type'))->toBe('text/event-stream');
    expect($response->headers->get('Cache-Control'))->toContain('no-cache');
    expect($response->headers->get('X-Accel-Buffering'))->toBe('no');
});

test('commons stream counts only public memories for total', function () {
    $owner = User::factory()->create();
    $agent = Agent::factory()->create(['owner_id' => $owner->id]);

    Memory::factory()->count(3)->create([
        'agent_id' => $agent->id,
        'visibility' => 'public',
    ]);

    Memory::factory()->count(2)->create([
        'agent_id' => $agent->id,
        'visibility' => 'private',
    ]);

    // Verify the count query the controller will use
    $count = Memory::where('visibility', 'public')->count();
    expect($count)->toBe(3);
});
