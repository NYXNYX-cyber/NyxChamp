<?php

namespace App\Http\Controllers;

use App\Models\Competition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman kompetisi untuk publik (read-only). Filter dilakukan
 * via query string (level, status, q) — lihat AGENTS.md §3.3
 * dan rancangan §6 untuk field yang tersedia.
 *
 * Aksi `create`/`store`/`edit`/`update`/`destroy` belum tersedia
 * untuk publik: kompetisi hanya di-insert oleh scraper di Fase 5.
 */
class CompetitionController extends Controller
{
    /**
     * Daftar kompetisi dengan filter (level, status, pencarian).
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'level' => ['nullable', 'string', 'in:kabupaten,provinsi,nasional,internasional'],
            'status' => ['nullable', 'string', 'in:open,closed,all'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Competition::query()
            ->orderBy('registration_deadline')
            ->orderByDesc('id');

        if (! empty($validated['level'])) {
            $query->ofLevel($validated['level']);
        }

        if (($validated['status'] ?? 'open') === 'open') {
            $query->open();
        } elseif (($validated['status'] ?? null) === 'closed') {
            $query->whereDate('registration_deadline', '<', now()->toDateString());
        }

        if (! empty($validated['q'])) {
            $term = '%' . $validated['q'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('organizer', 'like', $term);
            });
        }

        $competitions = $query->paginate(12)->withQueryString()->through(fn (Competition $c) => [
            'id' => $c->id,
            'title' => $c->title,
            'slug' => $c->slug,
            'organizer' => $c->organizer,
            'level' => $c->level,
            'registration_deadline' => $c->registration_deadline?->toDateString(),
            'registration_fee' => (float) $c->registration_fee,
            'is_open' => $c->isOpenForRegistration(),
            'has_poster' => $c->hasPoster(),
            'poster_url' => $c->hasPoster()
                ? route('competitions.poster', ['competition' => $c->slug])
                : null,
        ]);

        return Inertia::render('Competitions/Index', [
            'competitions' => $competitions,
            'filters' => [
                'level' => $validated['level'] ?? null,
                'status' => $validated['status'] ?? 'open',
                'q' => $validated['q'] ?? null,
            ],
            'levels' => Competition::LEVELS,
        ]);
    }

    /**
     * Detail satu kompetisi. Pakai route model binding via `slug`.
     */
    public function show(Competition $competition): Response
    {
        $competition->loadCount('rooms');

        return Inertia::render('Competitions/Show', [
            'competition' => [
                'id' => $competition->id,
                'title' => $competition->title,
                'slug' => $competition->slug,
                'organizer' => $competition->organizer,
                'description' => $competition->description,
                'level' => $competition->level,
                'registration_deadline' => $competition->registration_deadline?->toDateString(),
                'registration_fee' => (float) $competition->registration_fee,
                'source_url' => $competition->source_url,
                'is_open' => $competition->isOpenForRegistration(),
                'rooms_count' => $competition->rooms_count,
                'has_poster' => $competition->hasPoster(),
                'poster_url' => $competition->hasPoster()
                    ? route('competitions.poster', ['competition' => $competition->slug])
                    : null,
            ],
            'auth' => [
                'user' => auth()->user() ? [
                    'role' => auth()->user()->role,
                ] : null,
            ],
        ]);
    }

    /**
     * Serve poster gambar dari disk private 'competitions'.
     * Public route (poster adalah data publik, bukan user-generated).
     * Return 404 kalau belum ada poster.
     */
    public function poster(Competition $competition): StreamedResponse|\Illuminate\Http\Response
    {
        $path = $competition->poster_path;
        if (empty($path)) {
            abort(404, 'Poster belum tersedia untuk lomba ini.');
        }

        $disk = Storage::disk('competitions');
        if (! $disk->exists($path)) {
            abort(404, 'File poster hilang di disk.');
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };

        return response()->stream(function () use ($disk, $path) {
            $stream = $disk->readStream($path);
            if (is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Length' => (string) $disk->size($path),
            'Cache-Control' => 'public, max-age=86400', // 1 hari
        ]);
    }
}
