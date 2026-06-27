<?php

namespace Tests\Feature;

use App\Jobs\ScrapePortalJob;
use App\Models\Competition;
use App\Models\User;
use App\Services\Scraper\Exceptions\ScraperException;
use App\Services\ScraperService;
use App\Services\CompetitionIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScrapePortalJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(['role' => 'admin']);
    }

    public function test_job_runs_and_ingests_competitions(): void
    {
        Http::fake([
            '*/scrape' => Http::response([
                'job_id' => 'j-1',
                'portal' => 'lombahub_com',
                'items' => [
                    [
                        'title' => 'Lomba Job Test',
                        'organizer' => 'Org Job',
                        'description' => 'Test',
                        'registration_deadline' => '2026-12-31',
                        'level' => 'nasional',
                        'registration_fee' => 0,
                        'source_url' => 'https://lombahub.com/lomba/job-test',
                    ],
                ],
                'errors' => [],
            ], 200),
        ]);

        $job = new ScrapePortalJob('lombahub_com');
        $job->handle(app(CompetitionIngestor::class));

        $this->assertDatabaseCount('competitions', 1);
        $this->assertSame('Lomba Job Test', Competition::first()->title);
    }

    public function test_job_uses_scraping_queue(): void
    {
        $job = new ScrapePortalJob('lombahub_com');
        $this->assertSame('scraping', $job->queue);
    }

    public function test_job_backoff_is_60_300_900(): void
    {
        $job = new ScrapePortalJob('lombahub_com');
        $this->assertSame([60, 300, 900], $job->backoff());
    }

    public function test_job_tries_is_3(): void
    {
        $job = new ScrapePortalJob('lombahub_com');
        $this->assertSame(3, $job->tries);
    }

    public function test_job_fails_permanently_on_4xx_no_retry(): void
    {
        Http::fake([
            '*/scrape' => Http::response('Bad Request', 400),
        ]);

        $job = new ScrapePortalJob('lombahub_com');

        // Should call $this->fail() and not throw — Laravel marks job as
        // failed permanently, no retry.
        $job->handle(app(CompetitionIngestor::class));

        // No competitions inserted because scraper failed.
        $this->assertDatabaseCount('competitions', 0);
    }

    public function test_job_rethrows_on_5xx_to_trigger_backoff(): void
    {
        Http::fake([
            '*/scrape' => Http::response('Server Error', 500),
        ]);

        $job = new ScrapePortalJob('lombahub_com');

        // maxRetries=1 → 1 attempt → throw ScraperException
        $this->expectException(ScraperException::class);
        $job->handle(app(CompetitionIngestor::class));
    }
}
