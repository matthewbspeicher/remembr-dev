<?php

use App\Http\Controllers\Auth\DashboardController;
use App\Http\Controllers\Auth\MagicLinkController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/health', fn () => response('ok', 200));

Route::get('/', function () {
    $totalMemories = \App\Models\Memory::count();
    return Inertia::render('Home', ['totalMemories' => $totalMemories]);
});

Route::get('/skill.md', fn () => response()->file(public_path('skill.md'), ['Content-Type' => 'text/markdown']));

Route::get('/docs', function () {
    return view('docs');
});
Route::get('/commons', function () {
    $initialMemories = \App\Models\Memory::with('agent:id,name,description')
        ->where('visibility', 'public')
        ->latest()
        ->limit(100)
        ->get();
    return Inertia::render('Commons', ['initialMemories' => $initialMemories]);
})->name('commons');

// Auth — magic link flow
Route::middleware('guest')->group(function () {
    Route::get('/login', [MagicLinkController::class, 'showLogin'])->name('login');
    Route::post('/login', [MagicLinkController::class, 'sendLink'])->middleware('throttle:3,1');
    Route::get('/auth/check-email', [MagicLinkController::class, 'checkEmail'])->name('auth.check-email');
});

Route::get('/auth/verify/{token}', [MagicLinkController::class, 'verifyLink'])->name('auth.verify');

// Authenticated
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'show'])->name('dashboard');
    Route::post('/dashboard/token/rotate', [DashboardController::class, 'rotateOwnerToken'])->name('dashboard.token.rotate');
    Route::get('/memories', [\App\Http\Controllers\MemoryBrowserController::class, 'index'])->name('memories.index');
    Route::post('/dashboard/agents', [DashboardController::class, 'registerAgent'])->name('dashboard.register-agent');
    Route::delete('/dashboard/agents/{agent}', [DashboardController::class, 'destroy'])->name('dashboard.agents.destroy');
    Route::post('/dashboard/agents/{agent}/rotate', [DashboardController::class, 'rotateToken'])->name('dashboard.agents.rotate');
    Route::post('/logout', [MagicLinkController::class, 'logout'])->name('logout');
});
