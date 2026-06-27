<?php

namespace App\Services;

use App\Events\NewCompetitionDetected;
use App\Models\ChatRoom;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ubah array items dari ScraperResponse jadi baris di tabel `competitions`.
 *
 * - Dedup by `hash_md5` (lihat AGENTS.md §3.3) — pakai updateOrCreate
 *   untuk handle kasus hash sama tapi field lain berubah.
 * - Validasi level → fallback ke 'nasional' kalau tidak dikenal.
 * - Untuk kompetisi BARU: dispatch event `NewCompetitionDetected`
 *   (listener saat ini cuma log; real-time UI ada di Fase 7).
 * - Auto-create public chat_room yang terikat competition (lihat
 *   AGENTS.md §3.5 channel publik).
 *
 * Return statistik: { inserted, updated, skipped, rooms_created }.
 */
class CompetitionIngestor
{
    /**
     * Ingest array items hasil scrape.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{inserted: int, updated: int, skipped: int, rooms_created: int, errors: array<int, string>}
     */
    public function ingest(array $items): array
    {
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $roomsCreated = 0;
        $errors = [];

        foreach ($items as $raw) {
            try {
                $normalized = $this->normalize($raw);
                if ($normalized === null) {
                    $skipped++;
                    continue;
                }

                $existing = Competition::where('hash_md5', $normalized['hash_md5'])->first();

                $competition = Competition::updateOrCreate(
                    ['hash_md5' => $normalized['hash_md5']],
                    $normalized,
                );

                $wasCreated = $existing === null;
                if ($wasCreated) {
                    $inserted++;
                } else {
                    $updated++;
                }

                if ($wasCreated) {
                    // Auto-create public room untuk kompetisi baru
                    if ($this->createPublicRoom($competition)) {
                        $roomsCreated++;
                    }
                    event(new NewCompetitionDetected($competition));
                }
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = "item gagal ingest: " . Str::limit($e->getMessage(), 200);
                Log::warning('competition ingest error', [
                    'error' => $e->getMessage(),
                    'item' => $raw,
                ]);
            }
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'rooms_created' => $roomsCreated,
            'errors' => $errors,
        ];
    }

    /**
     * Normalisasi 1 item dari scraper ke field Competition.
     * Return null kalau item tidak valid (skip).
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    private function normalize(array $raw): ?array
    {
        $title = trim((string) ($raw['title'] ?? ''));
        if ($title === '') {
            return null;
        }

        $deadline = $raw['registration_deadline'] ?? null;
        if (! $deadline) {
            return null;
        }
        // Normalize ke Y-m-d string.
        try {
            $deadlineStr = \Carbon\Carbon::parse($deadline)->toDateString();
        } catch (\Throwable) {
            return null;
        }

        $level = strtolower(trim((string) ($raw['level'] ?? 'nasional')));
        if (! in_array($level, Competition::LEVELS, true)) {
            $level = Competition::LEVEL_NASIONAL;
        }

        $fee = (float) ($raw['registration_fee'] ?? 0);
        if ($fee < 0) {
            $fee = 0;
        }

        $sourceUrl = trim((string) ($raw['source_url'] ?? ''));
        if ($sourceUrl === '') {
            return null;
        }

        $hash = CompetitionHash::compute($title, $deadlineStr);

        return [
            'title' => Str::limit($title, 255, ''),
            'organizer' => Str::limit(trim((string) ($raw['organizer'] ?? 'Tidak diketahui')), 255, ''),
            'description' => (string) ($raw['description'] ?? ''),
            'registration_deadline' => $deadlineStr,
            'level' => $level,
            'registration_fee' => $fee,
            'source_url' => Str::limit($sourceUrl, 255, ''),
            'hash_md5' => $hash,
        ];
    }

    /**
     * Buat public chat room yang terikat competition. Return true kalau dibuat.
     * Kalau sudah ada (race condition), return false.
     */
    private function createPublicRoom(Competition $competition): bool
    {
        $existing = ChatRoom::where('competition_id', $competition->id)
            ->where('is_group', true)
            ->first();
        if ($existing) {
            return false;
        }

        // created_by WAJIB ada (schema tidak nullable). Pakai admin sistem.
        // Fail-fast kalau tidak ada user di sistem — jangan diam-diam skip.
        $systemUserId = $this->getSystemUserId();
        if ($systemUserId === null) {
            throw new \RuntimeException(
                'Tidak bisa auto-create public room: tidak ada user di sistem. ' .
                'Jalankan seeder dulu (php artisan db:seed).'
            );
        }

        ChatRoom::create([
            'competition_id' => $competition->id,
            'name' => 'Diskusi: ' . $competition->title,
            'is_group' => true,
            'created_by' => $systemUserId,
        ]);

        return true;
    }

    /**
     * Cari user 'sistem' untuk jadi created_by chat room otomatis.
     * Prefer admin pertama; fallback ke user pertama di sistem.
     * Return null kalau tabel users kosong.
     */
    private function getSystemUserId(): ?int
    {
        $admin = User::where('role', 'admin')->orderBy('id')->first();
        return $admin?->id ?? User::orderBy('id')->first()?->id;
    }
}
