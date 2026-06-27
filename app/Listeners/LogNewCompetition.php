<?php

namespace App\Listeners;

use App\Events\NewCompetitionDetected;
use Illuminate\Support\Facades\Log;

/**
 * Listener stub untuk NewCompetitionDetected.
 *
 * Fase 5: hanya log ke channel 'scraper' untuk audit trail. Fase 7
 * akan ganti body dengan dispatch ke Reverb channel publik
 * (lihat AGENTS.md §3.5).
 *
 * Listener diregistrasi via auto-discovery (EventServiceProvider
 * default Laravel 11/12/13).
 */
class LogNewCompetition
{
    public function handle(NewCompetitionDetected $event): void
    {
        $c = $event->competition;
        Log::channel('scraper')->info('kompetisi baru terdeteksi', [
            'id' => $c->id,
            'slug' => $c->slug,
            'title' => $c->title,
            'level' => $c->level,
            'deadline' => $c->registration_deadline?->toDateString(),
            'hash_md5' => $c->hash_md5,
        ]);
    }
}
