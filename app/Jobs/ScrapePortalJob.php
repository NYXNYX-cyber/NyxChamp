<?php

namespace App\Jobs;

use App\Services\CompetitionIngestor;
use App\Services\Scraper\Exceptions\ScraperException;
use App\Services\ScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Scrape satu portal → kirim ke Ingestor.
 *
 * Queue: 'scraping' (terpisah dari default agar bisa diprioritaskan).
 * Worker jalankan: `php artisan queue:work --queue=scraping,default`.
 *
 * Retry: 3 attempt dengan backoff 60s, 300s, 15m. Setelah 3x gagal,
 * Laravel akan pindahkan ke failed_jobs (bisa di-ignore untuk sekarang,
 * lihat AGENTS.md §7 retry policy belum final).
 */
class ScrapePortalJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int Total attempt sebelum masuk failed_jobs */
    public int $tries = 3;

    /** @var int|null Timeout job dalam detik (10 menit) */
    public ?int $timeout = 600;

    /**
     * Drop job kalau ScraperException 4xx (config error, dsb).
     * Retry cuma untuk error sementara (5xx, connection).
     */
    public function failed(\Throwable $e): void
    {
        Log::channel('scraper')->error('scrape job gagal permanen', [
            'portal' => $this->portal,
            'max_pages' => $this->maxPages,
            'error' => $e->getMessage(),
        ]);
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function __construct(
        public readonly string $portal,
        public readonly ?int $maxPages = null,
    ) {
        $this->onQueue('scraping');
    }

    public function handle(CompetitionIngestor $ingestor): void
    {
        Log::channel('scraper')->info('scrape job mulai', [
            'portal' => $this->portal,
            'max_pages' => $this->maxPages,
        ]);

        try {
            $scraper = ScraperService::fromConfig();
            $response = $scraper->scrape($this->portal, $this->maxPages);
        } catch (ScraperException $e) {
            // 4xx → permanent fail. Jangan retry.
            if (str_contains($e->getMessage(), 'Scraper 4xx')) {
                $this->fail($e);
                return;
            }
            throw $e; // 5xx/connection → trigger backoff retry
        }

        $stats = $ingestor->ingest($response['items']);

        Log::channel('scraper')->info('scrape job selesai', [
            'portal' => $this->portal,
            'items_received' => count($response['items']),
            'scraper_errors' => count($response['errors']),
            'stats' => $stats,
        ]);

        // Catat error dari scraper (mis. 1 halaman gagal scrape) sebagai warning.
        foreach ($response['errors'] as $err) {
            Log::channel('scraper')->warning("scraper error: {$err}", [
                'portal' => $this->portal,
            ]);
        }
    }
}
