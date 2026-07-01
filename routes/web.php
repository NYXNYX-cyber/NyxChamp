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
        // Stats + flag trigger availability di-render via Inertia.
        $stats = app(\App\Http\Controllers\Admin\ScraperController::class)->stats();
        return \Inertia\Inertia::render('Admin/Dashboard', [
            'stats' => $stats,
        ]);
    })->name('admin.dashboard');

    // Manual scrape trigger (emergency: jadwal auto gagal atau
    // ada info lomba urgent mid-week).
    Route::post('/admin/scrape/trigger', [\App\Http\Controllers\Admin\ScraperController::class, 'trigger'])
        ->name('admin.scrape.trigger');

    // Health check Python scraper service.
    Route::post('/admin/scrape/health', [\App\Http\Controllers\Admin\ScraperController::class, 'health'])
        ->name('admin.scrape.health');
});

// Chat real-time (lihat AGENTS.md §3.5 + Rancangan §4).
// Channel Reverb: chat.room.{id} (private) + chat.presence.{id} (presence).
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/{room}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/{room}/messages', [ChatController::class, 'storeMessage'])->name('chat.messages.store');
    Route::patch('/chat/{room}/messages/{message}', [ChatController::class, 'updateMessage'])->name('chat.messages.update');
    Route::delete('/chat/{room}/messages/{message}', [ChatController::class, 'deleteMessage'])->name('chat.messages.delete');
    Route::post('/chat/{room}/messages/{message}/attachments', [ChatController::class, 'uploadAttachment'])->name('chat.messages.attachments.store');
    Route::get('/chat/{room}/attachments/{attachment}/download', [ChatController::class, 'downloadAttachment'])->name('chat.attachments.download');
    Route::post('/chat/{room}/read', [ChatController::class, 'markRead'])->name('chat.messages.read');
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
