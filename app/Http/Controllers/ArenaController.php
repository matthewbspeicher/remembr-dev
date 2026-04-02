<?php

namespace App\Http\Controllers;

use App\Models\ArenaGym;
use App\Models\ArenaMatch;
use Inertia\Inertia;

class ArenaController extends Controller
{
    public function index()
    {
        $gyms = ArenaGym::withCount('challenges')
            ->where('is_official', true)
            ->get();

        $recentMatches = ArenaMatch::with(['agent1', 'agent2', 'challenge'])
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('Arena', [
            'gyms' => $gyms,
            'recentMatches' => $recentMatches,
        ]);
    }

    public function showGym(ArenaGym $gym)
    {
        return Inertia::render('ArenaGym', [
            'gym' => $gym->load('challenges'),
        ]);
    }

    public function showMatch(ArenaMatch $match)
    {
        return Inertia::render('ArenaMatch', [
            'match' => $match->load(['agent1', 'agent2', 'challenge', 'sessions.turns']),
        ]);
    }
}
