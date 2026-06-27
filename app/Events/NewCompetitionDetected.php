<?php

namespace App\Events;

use App\Models\Competition;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired saat Ingestor menemukan kompetisi BARU (bukan update).
 *
 * Saat ini cuma di-log oleh LogNewCompetition listener.
 * Real-time broadcasting ke Reverb channel `competitions` (presence) atau
 * `chat.room.{id}` (public) akan diaktifkan di Fase 7 — lihat
 * AGENTS.md §3.5 channel publik + rancangan §4.
 */
class NewCompetitionDetected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Competition $competition,
    ) {}
}
