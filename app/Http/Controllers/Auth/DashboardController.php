<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $agents = $user->agents()->withCount('memories')->latest()->get();
        $agentCount = $agents->count();
        $avgMemories = $agentCount > 0 ? (int) $agents->avg('memories_count') : 0;

        return Inertia::render('Dashboard', [
            'apiToken' => $user->api_token,
            'agents' => $agents->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'description' => $a->description,
                'created_at' => $a->created_at,
            ]),
            'workspaces' => $user->sharedWorkspaces()->select('workspaces.id', 'workspaces.name', 'workspaces.description', 'workspaces.owner_id')->get(),
            'isPro' => $user->isPro(),
            'isOnGracePeriod' => $user->isOnGracePeriod(),
            'hasPaymentFailure' => $user->hasPaymentFailure(),
            'isDowngraded' => $user->isDowngraded(),
            'currentPlan' => $user->isPro() ? 'pro' : ($user->hasUnlimitedAgentAccess() ? 'unlimited' : 'free'),
            'agentCount' => $agentCount,
            'maxAgents' => $user->hasUnlimitedAgentAccess() ? 'unlimited' : $user->maxAgents(),
            'avgMemoriesPerAgent' => $avgMemories,
            'maxMemoriesPerAgent' => $user->maxMemoriesPerAgent(),
        ]);
    }

    public function registerAgent(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        if ($user->agents()->count() >= $user->maxAgents()) {
            return back()->withErrors(['name' => 'Agent limit reached. Upgrade to Pro for unlimited agents.']);
        }

        $token = Agent::generateToken();

        $request->user()->agents()->create([
            'name' => $request->name,
            'description' => $request->description,
            'api_token' => $token,
            'token_hash' => hash('sha256', $token),
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
            'token_hash' => hash('sha256', $token),
        ]);

        return back()->with('message', "Token rotated! New Token: {$token}");
    }

    public function rotateOwnerToken(Request $request)
    {
        $user = $request->user();
        $user->api_token = User::generateToken();
        $user->api_token_hash = hash('sha256', $user->api_token);
        $user->save();

        return back()->with('message', "Owner API token rotated! New Token: {$user->api_token}");
    }
}
