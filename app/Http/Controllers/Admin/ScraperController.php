<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ScrapePortalJob;
use App\Models\Competition;
use App\Services\Scraper\Exceptions\ScraperException;
use App\Services\ScraperService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Admin tools untuk kendali manual scraper.
 *
 * Use case: jadwal scraping normal hanya Senin 05:00 + Jumat 15:00
 * (lihat AGENTS.md §3.4). Admin butuh trigger manual kalau:
 * - Ada info lomba baru penting yang harusnya cepat muncul
 *   (mis. sponsor publish lomba mid-week, misal hari Rabu)
 * - Jadwal otomatis gagal / queue worker down, perlu recovery
 *
 * Endpoints:
 *   POST /admin/scrape/trigger   → dispatch ScrapePortalJob untuk
 *                                   semua portal ke queue 'scraping'
 *   POST /admin/scrape/health    → cek status Python scraper service
 *
 * Middleware: ['auth', 'verified', 'role:admin'] (lihat routes/web.php)
 */
class ScraperController extends Controller
{
    /** Default max pages kalau admin tidak specify. */
    public const DEFAULT_MAX_PAGES = 5;

    /** Rate limit: max 1 trigger per 5 menit (anti-spam klik). */
    public const TRIGGER_COOLDOWN_SECONDS = 300;

    /**
     * Trigger scrape untuk semua portal. Dispatch ke queue 'scraping'
     * (tidak sync, supaya admin tidak nungguin hasil — lihat jobs
     * ScrapePortalJob + queue worker).
     */
    public function trigger(Request $request): RedirectResponse
    {
        $request->validate([
            'max_pages' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $request->user();
        $maxPages = (int) $request->input('max_pages', self::DEFAULT_MAX_PAGES);

        // Rate limit: cache last trigger time per admin
        $cacheKey = "admin:scrape:trigger:{$user->id}";
        $lastTrigger = cache()->get($cacheKey);

        if ($lastTrigger !== null) {
            $elapsed = time() - $lastTrigger;
            if ($elapsed < self::TRIGGER_COOLDOWN_SECONDS) {
                $wait = self::TRIGGER_COOLDOWN_SECONDS - $elapsed;
                return back()->withErrors([
                    'trigger' => sprintf(
                        'Cooldown aktif. Coba lagi dalam %d detik.',
                        $wait,
                    ),
                ]);
            }
        }

        $portals = array_keys(config('services.scraper.portals') ?? []);
        // Fallback kalau config kosong: pakai PORTALS di Python scraper
        // (lihat scraper/app/services/portals.py — sumber kebenaran).
        if ($portals === []) {
            $portals = [
                'lombahub_com',
                'kompetisi_co_id',
                'luarkampus_id',
                'ajangjuara_com',
                'ikutlomba_id',
                'sejutacita_id',
            ];
        }

        $dispatched = 0;
        foreach ($portals as $portalKey) {
            ScrapePortalJob::dispatch($portalKey, $maxPages);
            $dispatched++;
        }

        cache()->put($cacheKey, time(), self::TRIGGER_COOLDOWN_SECONDS);

        Log::info('admin: scrape triggered', [
            'admin_id' => $user->id,
            'admin_email' => $user->email,
            'portals' => $portals,
            'max_pages' => $maxPages,
            'dispatched' => $dispatched,
        ]);

        return back()->with('status', sprintf(
            'Scraping dijadwalkan: %d job masuk queue (max %d halaman/portal). Cek storage/logs/laravel.log untuk progress.',
            $dispatched,
            $maxPages,
        ));
    }

    /**
     * Health check Python scraper service.
     */
    public function health(Request $request): RedirectResponse
    {
        $result = ['ok' => false, 'message' => ''];

        try {
            $scraper = ScraperService::fromConfig();
            $ok = $scraper->health();
            if ($ok) {
                $result['ok'] = true;
                $result['message'] = 'Scraper service hidup.';
            } else {
                $result['message'] = 'Scraper service tidak merespon (mungkin down).';
            }
        } catch (\InvalidArgumentException $e) {
            $result['message'] = 'Config scraper error: ' . $e->getMessage();
        } catch (ScraperException $e) {
            $result['message'] = 'Scraper down: ' . $e->getMessage();
        } catch (\Throwable $e) {
            $result['message'] = 'Error: ' . $e->getMessage();
        }

        return back()->with('health', $result);
    }

    /**
     * Stats kompetisi untuk dashboard.
     *
     * Return: total, breakdown by level, breakdown by is_open (deadline
     * belum lewat), kompetisi terbaru.
     */
    public function stats(): array
    {
        $total = Competition::count();
        $open = Competition::where('registration_deadline', '>=', now()->toDateString())->count();
        $closed = $total - $open;

        $byLevel = Competition::query()
            ->selectRaw('level, count(*) as count')
            ->groupBy('level')
            ->pluck('count', 'level')
            ->all();

        $latest = Competition::orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'title', 'source_url', 'registration_deadline', 'level']);

        return [
            'total' => $total,
            'open' => $open,
            'closed' => $closed,
            'by_level' => $byLevel,
            'latest' => $latest,
        ];
    }
}
