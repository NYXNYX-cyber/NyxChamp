<?php

namespace Tests\Feature;

use App\Services\Scraper\Exceptions\ScraperException;
use App\Services\ScraperService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScraperServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Env default di set di phpunit.xml
    }

    private function makeService(int $maxRetries = 2): ScraperService
    {
        return new ScraperService(
            baseUrl: 'http://scraper.test:8001',
            token: 'test-token',
            timeoutSeconds: 5,
            maxRetries: $maxRetries,
        );
    }

    public function test_scrape_sends_bearer_token_and_json_payload(): void
    {
        Http::fake([
            'scraper.test:8001/scrape' => Http::response([
                'success' => true,
                'job_id' => 'abc-123',
                'portal' => 'lombahub_com',
                'items' => [],
                'errors' => [],
            ], 200),
        ]);

        $svc = $this->makeService();
        $resp = $svc->scrape('lombahub_com', 10);

        $this->assertSame('lombahub_com', $resp['portal']);
        $this->assertSame([], $resp['items']);

        Http::assertSent(function ($request) {
            $body = json_decode($request->body(), true);
            return $request->hasHeader('Authorization', 'Bearer test-token')
                && $request->url() === 'http://scraper.test:8001/scrape'
                && $body['portal'] === 'lombahub_com'
                && $body['max_pages'] === 10
                && ! empty($body['job_id']);
        });
    }

    public function test_scrape_returns_normalized_response(): void
    {
        Http::fake([
            'scraper.test:8001/scrape' => Http::response([
                'job_id' => 'j-1',
                'portal' => 'lombahub_com',
                'items' => [
                    [
                        'title' => 'Lomba A',
                        'organizer' => 'Org A',
                        'description' => 'Desc A',
                        'registration_deadline' => '2026-12-01',
                        'level' => 'nasional',
                        'registration_fee' => 50000,
                        'source_url' => 'https://lombahub.com/lomba/a',
                        'hash_md5' => 'should-be-ignored-by-us',
                    ],
                ],
                'errors' => [],
            ], 200),
        ]);

        $resp = $this->makeService()->scrape('lombahub_com');

        $this->assertCount(1, $resp['items']);
        $this->assertSame('Lomba A', $resp['items'][0]['title']);
        $this->assertSame([], $resp['errors']);
    }

    public function test_scrape_throws_on_4xx_non_429(): void
    {
        Http::fake([
            'scraper.test:8001/scrape' => Http::response('Bad Request', 400),
        ]);

        $this->expectException(ScraperException::class);
        $this->expectExceptionMessageMatches('/Scraper 4xx/');

        $this->makeService(maxRetries: 3)->scrape('lombahub_com');
    }

    public function test_scrape_retries_on_5xx_then_throws(): void
    {
        Http::fake([
            'scraper.test:8001/scrape' => Http::response('Internal Server Error', 500),
        ]);

        $this->expectException(ScraperException::class);

        $start = microtime(true);
        try {
            $this->makeService(maxRetries: 2)->scrape('lombahub_com');
        } finally {
            $elapsed = microtime(true) - $start;
            // 2 attempt → 1 backoff (1s) → minimal 1 detik elapsed.
            $this->assertGreaterThanOrEqual(0.9, $elapsed);
        }
    }

    public function test_scrape_retries_on_429(): void
    {
        Http::fake([
            'scraper.test:8001/scrape' => Http::response('Too Many Requests', 429),
        ]);

        $this->expectException(ScraperException::class);
        $this->makeService(maxRetries: 1)->scrape('lombahub_com');
    }

    public function test_scrape_throws_on_invalid_response_shape(): void
    {
        Http::fake([
            'scraper.test:8001/scrape' => Http::response([
                'unexpected' => 'shape',
            ], 200),
        ]);

        $this->expectException(ScraperException::class);
        $this->expectExceptionMessageMatches('/shape tidak valid/');

        $this->makeService()->scrape('lombahub_com');
    }

    public function test_health_returns_true_when_200(): void
    {
        Http::fake([
            'scraper.test:8001/health' => Http::response(['ok' => true], 200),
        ]);

        $this->assertTrue($this->makeService()->health());
    }

    public function test_health_returns_false_when_500(): void
    {
        Http::fake([
            'scraper.test:8001/health' => Http::response('', 500),
        ]);

        $this->assertFalse($this->makeService()->health());
    }

    public function test_health_returns_false_on_connection_error(): void
    {
        // Tidak fake → connection refused
        $this->assertFalse($this->makeService()->health());
    }

    public function test_from_config_throws_when_url_and_token_empty(): void
    {
        // Constructor dengan empty string → throw InvalidArgumentException.
        $this->expectException(\InvalidArgumentException::class);
        new ScraperService(
            baseUrl: '',
            token: '',
        );
    }
}
