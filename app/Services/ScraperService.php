<?php

namespace App\Services;

use App\Services\Scraper\Exceptions\ScraperException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * HTTP client ke Python scraper service (lihat scraper/README.md + CONTRACT.md).
 *
 * Pipeline (sesuai AGENTS.md §3.2):
 *   Laravel → ScraperService → POST /scrape (Python) → ScrapeResponse JSON
 *
 * Kontrak: scraper/app/schemas.py::ScrapeResponse
 *   { job_id, portal, items: [Competition], errors: [string] }
 *
 * Retry policy: 3 attempt total, exponential 1s/2s/4s. 4xx (kecuali 429)
 * tidak retry — langsung raise. 5xx + timeout + connection error retry.
 */
class ScraperService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly int $timeoutSeconds = 120,
        private readonly int $maxRetries = 3,
    ) {
        if ($baseUrl === '' || $token === '') {
            throw new \InvalidArgumentException(
                'ScraperService butuh baseUrl dan token non-empty.'
            );
        }
    }

    /**
     * Konstruktor dari config env. Throw kalau env wajib tidak ada.
     */
    public static function fromConfig(): self
    {
        $url = config('services.scraper.url') ?? env('SCRAPER_SERVICE_URL');
        $token = config('services.scraper.token') ?? env('SCRAPER_SERVICE_TOKEN');

        if (! $url || ! $token) {
            throw new \RuntimeException(
                'SCRAPER_SERVICE_URL dan SCRAPER_SERVICE_TOKEN wajib di-set. Lihat .env.example.'
            );
        }

        return new self(
            baseUrl: rtrim($url, '/'),
            token: $token,
            timeoutSeconds: (int) env('SCRAPER_TIMEOUT_SECONDS', 120),
            maxRetries: (int) env('SCRAPER_MAX_RETRIES', 3),
        );
    }

    /**
     * Panggil scraper untuk satu portal. Return array decoded dari JSON.
     *
     * @return array{job_id: string, portal: string, items: array<int, array<string, mixed>>, errors: array<int, string>}
     *
     * @throws ScraperException
     */
    public function scrape(string $portal, ?int $maxPages = null): array
    {
        $jobId = (string) Str::uuid();
        $payload = [
            'portal' => $portal,
            'job_id' => $jobId,
        ];
        if ($maxPages !== null) {
            $payload['max_pages'] = $maxPages;
        }

        $lastException = null;
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                /** @var array $response */
                $response = $this->http()
                    ->post($this->baseUrl . '/scrape', $payload)
                    ->throw()
                    ->json();

                if (! is_array($response) || ! isset($response['items'], $response['errors'])) {
                    throw new ScraperException(
                        "Scraper response shape tidak valid: " . json_encode($response, JSON_UNESCAPED_UNICODE)
                    );
                }

                return [
                    'job_id' => $response['job_id'] ?? $jobId,
                    'portal' => $response['portal'] ?? $portal,
                    'items' => $response['items'] ?? [],
                    'errors' => $response['errors'] ?? [],
                ];
            } catch (RequestException $e) {
                // 4xx/5xx. 429 + 5xx retry, 4xx lain fail.
                $status = $e->response?->status();
                if ($status !== null && $status >= 400 && $status < 500 && $status !== 429) {
                    throw new ScraperException(
                        "Scraper 4xx (status={$status}): " . Str::limit($e->getMessage(), 200),
                        previous: $e,
                    );
                }
                $lastException = $e;
                Log::warning("scraper attempt={$attempt} gagal status={$status}", [
                    'portal' => $portal,
                    'job_id' => $jobId,
                ]);
            } catch (ConnectionException $e) {
                $lastException = $e;
                Log::warning("scraper attempt={$attempt} connection error: " . $e->getMessage(), [
                    'portal' => $portal,
                    'job_id' => $jobId,
                ]);
            }

            if ($attempt < $this->maxRetries) {
                sleep(2 ** ($attempt - 1));
            }
        }

        throw new ScraperException(
            "Scraper gagal setelah {$this->maxRetries} attempt: " . ($lastException?->getMessage() ?? 'unknown'),
            previous: $lastException,
        );
    }

    /**
     * Health check sederhana — panggil GET /health. Return true kalau 200.
     * Dipakai oleh command dan dashboard admin.
     */
    public function health(): bool
    {
        try {
            return $this->http()->get($this->baseUrl . '/health')->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->token)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeoutSeconds);
    }
}
