<?php

use App\Http\Controllers\Auth\DashboardController;
use App\Http\Controllers\Auth\MagicLinkController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/skill.md', fn () => response()->file(public_path('skill.md'), ['Content-Type' => 'text/markdown']));
Route::get('/docs', fn () => Inertia::render('Docs'))->name('docs');

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
    Route::post('/dashboard/agents', [DashboardController::class, 'registerAgent'])->name('dashboard.register-agent');
    Route::post('/logout', [MagicLinkController::class, 'logout'])->name('logout');
});
