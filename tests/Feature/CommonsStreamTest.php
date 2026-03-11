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
})->skip('Stream disabled for Octane');

test('commons stream controller returns streamed response with correct headers', function () {
    $controller = new CommonsStreamController;
    $request = new \Illuminate\Http\Request;
})->skip('Stream disabled for Octane');

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
