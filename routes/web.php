<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\CompetitionController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Kompetensi publik (read-only) — lihat AGENTS.md §3.3.
// Filter via query string: ?level=nasional&status=open&q=design
Route::get('/lomba', [CompetitionController::class, 'index'])->name('competitions.index');
Route::get('/lomba/{competition:slug}', [CompetitionController::class, 'show'])->name('competitions.show');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Smoke-test RBAC: hanya admin yang boleh masuk.
Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::get('/admin', function () {
        return Inertia::render('Admin/Dashboard');
    })->name('admin.dashboard');
});

// Chat real-time (lihat AGENTS.md §3.5 + Rancangan §4).
// Channel Reverb: chat.room.{id} (private) + chat.presence.{id} (presence).
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/{room}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/{room}/messages', [ChatController::class, 'storeMessage'])->name('chat.messages.store');
    Route::post('/chat/{room}/members', [ChatController::class, 'inviteMember'])->name('chat.members.invite');

    // Guru/admin buat grup bimbingan untuk kompetisi.
    Route::post('/lomba/{competition:slug}/grup-bimbingan', [ChatController::class, 'createGroupBimbingan'])
        ->name('competitions.groups.create');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
