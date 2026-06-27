<?php

namespace App\Console\Commands;

use App\Jobs\ScrapePortalJob;
use App\Services\CompetitionIngestor;
use App\Services\Scraper\Exceptions\ScraperException;
use App\Services\ScraperService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Trigger scraping untuk 1 atau lebih portal.
 *
 * Contoh:
 *   php artisan scrape                                # semua portal, ke queue
 *   php artisan scrape --portal=lombahub_com          # 1 portal, ke queue
 *   php artisan scrape --portal=lombahub_com --sync   # foreground, langsung ingest
 *   php artisan scrape --sync --max-pages=5           # sync + batasi halaman
 *   php artisan scrape --health                       # cek apakah scraper up
 *
 * Scheduler: bootstrap/app.php jalankan command ini 2x seminggu
 * (Senin 05:00, Jumat 15:00 WIB) — lihat AGENTS.md §3.4.
 */
class ScrapeCommand extends Command
{
    protected $signature = 'scrape
        {--portal=* : Filter portal (key). Dipisah spasi untuk banyak. Default: semua}
        {--max-pages= : Batas halaman listing per portal (default: env SCRAPER_MAX_PAGES_PER_PORTAL)}
        {--sync : Jalankan langsung di foreground (langsung ingest), jangan dispatch ke queue}
        {--health : Hanya cek apakah scraper service up, lalu exit}';

    protected $description = 'Trigger scraping kompetisi dari portal target (Laravel ↔ Python scraper)';

    public function handle(CompetitionIngestor $ingestor): int
    {
        if ($this->option('health')) {
            return $this->doHealth();
        }

        $portals = $this->resolvePortals();
        if ($portals === []) {
            $this->error('Tidak ada portal valid. Pilihan: ' . implode(', ', $this->knownPortalKeys()));
            return self::INVALID;
        }

        $maxPages = $this->option('max-pages');
        $maxPages = $maxPages !== null ? (int) $maxPages : null;

        if ($this->option('sync')) {
            return $this->runSync($portals, $maxPages, $ingestor);
        }
        return $this->dispatchJobs($portals, $maxPages);
    }

    /**
     * @return array<int, string>
     */
    private function resolvePortals(): array
    {
        $requested = $this->option('portal');
        if (! is_array($requested) || $requested === []) {
            // Default: semua portal di registry.
            return $this->knownPortalKeys();
        }
        // Validasi
        $known = $this->knownPortalKeys();
        $invalid = array_diff($requested, $known);
        if ($invalid !== []) {
            $this->warn('Portal tidak dikenal di-skip: ' . implode(', ', $invalid));
        }
        return array_values(array_intersect($requested, $known));
    }

    /**
     * @return array<int, string>
     */
    private function knownPortalKeys(): array
    {
        // Source of truth: TARGET_PORTALS di config. Karena ScraperService
        // cuma tahu 6 portal itu, kita hardcode di sini. Kalau tambah
        // portal nanti, update sini + Python registry.
        return [
            'lombahub_com',
            'ikutlomba_id',
            'kompetisi_co_id',
            'ajangjuara_com',
            'sejutacita_id',
            'luarkampus_id',
        ];
    }

    /**
     * @param  array<int, string>  $portals
     */
    private function dispatchJobs(array $portals, ?int $maxPages): int
    {
        foreach ($portals as $portal) {
            ScrapePortalJob::dispatch($portal, $maxPages);
            $this->info("→ dispatched ScrapePortalJob(portal={$portal})");
        }
        $this->info("Total job di-dispatch: " . count($portals));
        $this->comment('Jalankan worker dengan: php artisan queue:work --queue=scraping');
        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $portals
     */
    private function runSync(array $portals, ?int $maxPages, CompetitionIngestor $ingestor): int
    {
        $this->info('Mode SYNC — scrape langsung, tidak via queue.');

        try {
            $scraper = ScraperService::fromConfig();
        } catch (\Throwable $e) {
            $this->error("ScraperService init gagal: " . $e->getMessage());
            return self::FAILURE;
        }

        $totalInserted = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;
        $totalRooms = 0;
        $allErrors = [];

        foreach ($portals as $portal) {
            $this->line("→ scraping {$portal}...");
            try {
                $response = $scraper->scrape($portal, $maxPages);
            } catch (ScraperException $e) {
                $this->error("  ✗ {$portal} gagal: " . $e->getMessage());
                $allErrors[] = "{$portal}: " . $e->getMessage();
                continue;
            }

            $stats = $ingestor->ingest($response['items']);
            $this->line(sprintf(
                "  ✓ items=%d inserted=%d updated=%d skipped=%d rooms=%d",
                count($response['items']),
                $stats['inserted'],
                $stats['updated'],
                $stats['skipped'],
                $stats['rooms_created'],
            ));
            if ($response['errors'] !== []) {
                $this->warn('  scraper reported ' . count($response['errors']) . ' error(s):');
                foreach (array_slice($response['errors'], 0, 3) as $err) {
                    $this->line('    - ' . Str::limit($err, 150));
                }
            }
            $totalInserted += $stats['inserted'];
            $totalUpdated += $stats['updated'];
            $totalSkipped += $stats['skipped'];
            $totalRooms += $stats['rooms_created'];
            $allErrors = array_merge($allErrors, $stats['errors']);
        }

        $this->newLine();
        $this->info(sprintf(
            'TOTAL: inserted=%d updated=%d skipped=%d rooms_created=%d errors=%d',
            $totalInserted,
            $totalUpdated,
            $totalSkipped,
            $totalRooms,
            count($allErrors),
        ));
        return $allErrors === [] ? self::SUCCESS : self::FAILURE;
    }

    private function doHealth(): int
    {
        try {
            $scraper = ScraperService::fromConfig();
        } catch (\Throwable $e) {
            $this->error('ScraperService init gagal: ' . $e->getMessage());
            return self::FAILURE;
        }
        $ok = $scraper->health();
        if ($ok) {
            $this->info('scraper service: OK');
            return self::SUCCESS;
        }
        $this->error('scraper service: TIDAK BISA DIHUBUNGI');
        return self::FAILURE;
    }
}
