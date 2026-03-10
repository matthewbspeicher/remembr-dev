<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'apiToken' => $user->api_token,
            'agents' => $user->agents()->select('id', 'name', 'description', 'created_at')->latest()->get(),
        ]);
    }

    public function registerAgent(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $token = Agent::generateToken();

        $request->user()->agents()->create([
            'name' => $request->name,
            'description' => $request->description,
            'api_token' => $token,
        ]);

        return back()->with('message', "Agent created! Token: {$token}");
    }

    public function destroy(Request $request, Agent $agent)
    {
        if ($request->user()->id !== $agent->owner_id) {
            abort(403);
        }

        $agent->delete();

        return back()->with('message', 'Agent deleted successfully.');
    }

    public function rotateToken(Request $request, Agent $agent)
    {
        if ($request->user()->id !== $agent->owner_id) {
            abort(403);
        }

        $token = Agent::generateToken();
        
        $agent->update([
            'api_token' => $token,
        ]);

        return back()->with('message', "Token rotated! New Token: {$token}");
    }

    public function rotateOwnerToken(Request $request)
    {
        $user = $request->user();
        $user->api_token = \App\Models\User::generateToken();
        $user->save();

        return back()->with('message', "Owner API token rotated! New Token: {$user->api_token}");
    }
}
