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
}
