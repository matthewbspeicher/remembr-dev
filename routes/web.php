<?php

use App\Http\Controllers\ArenaController;
use App\Http\Controllers\Auth\DashboardController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MemoryBrowserController;
use App\Http\Controllers\WorkspaceSettingsController;
use App\Models\Memory;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/health', fn () => response('ok', 200));

Route::get('/arena', [ArenaController::class, 'index'])->name('arena.index');
Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard.index');

Route::get('/', HomeController::class);

Route::get('/skill.md', fn () => response()->file(public_path('skill.md'), ['Content-Type' => 'text/markdown']));

Route::get('/docs', function () {
    return view('docs');
});
Route::get('/commons', function () {
    try {
        $initialMemories = cache()->remember('commons:initial', 30, function () {
            return Memory::with('agent:id,name,description')
                ->where('visibility', 'public')
                ->latest()
                ->limit(100)
                ->get();
        });
    } catch (Throwable $e) {
        $initialMemories = collect();
    }

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
    Route::get('/memories', [MemoryBrowserController::class, 'index'])->name('memories.index');
    Route::post('/dashboard/agents', [DashboardController::class, 'registerAgent'])->name('dashboard.register-agent');
    Route::delete('/dashboard/agents/{agent}', [DashboardController::class, 'destroy'])->name('dashboard.agents.destroy');
    Route::post('/dashboard/agents/{agent}/rotate', [DashboardController::class, 'rotateToken'])->name('dashboard.agents.rotate');
    Route::post('/logout', [MagicLinkController::class, 'logout'])->name('logout');

    // Workspace Settings
    Route::post('/workspaces', [WorkspaceSettingsController::class, 'store'])->name('workspaces.store');
    Route::get('/workspaces/{workspace}/settings', [WorkspaceSettingsController::class, 'show'])->name('workspaces.settings');
    Route::post('/workspaces/{workspace}/invite', [WorkspaceSettingsController::class, 'inviteUser'])->name('workspaces.invite');
    Route::delete('/workspaces/{workspace}/users/{user}', [WorkspaceSettingsController::class, 'removeUser'])->name('workspaces.remove-user');
    Route::post('/workspaces/{workspace}/token/rotate', [WorkspaceSettingsController::class, 'rotateToken'])->name('workspaces.token.rotate');

    Route::get('/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('/billing/success', [BillingController::class, 'success'])->name('billing.success');
    Route::get('/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');
});

Route::get('/pricing', [BillingController::class, 'pricing'])->name('pricing');

// Stripe Cashier webhook routes are automatically registered in Cashier v15+
