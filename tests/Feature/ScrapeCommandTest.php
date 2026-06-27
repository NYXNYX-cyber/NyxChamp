<?php

namespace Tests\Feature;

use App\Jobs\ScrapePortalJob;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScrapeCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(['role' => 'admin']);
    }

    public function test_health_flag_returns_success_when_scraper_up(): void
    {
        Http::fake([
            '*/health' => Http::response(['ok' => true], 200),
        ]);
        $this->artisan('scrape', ['--health' => true])
            ->expectsOutputToContain('scraper service: OK')
            ->assertExitCode(0);
    }

    public function test_health_flag_returns_failure_when_scraper_down(): void
    {
        Http::fake([
            '*/health' => Http::response('', 500),
        ]);
        $this->artisan('scrape', ['--health' => true])
            ->expectsOutputToContain('TIDAK BISA DIHUBUNGI')
            ->assertExitCode(1);
    }

    public function test_dispatches_jobs_for_all_portals_by_default(): void
    {
        Bus::fake();
        $this->artisan('scrape')
            ->expectsOutputToContain('Total job di-dispatch: 6')
            ->assertExitCode(0);

        Bus::assertDispatched(ScrapePortalJob::class, 6);
    }

    public function test_dispatches_jobs_only_for_specified_portal(): void
    {
        Bus::fake();
        $this->artisan('scrape', ['--portal' => ['lombahub_com']])
            ->expectsOutputToContain('Total job di-dispatch: 1')
            ->assertExitCode(0);

        Bus::assertDispatched(ScrapePortalJob::class, 1);
        Bus::assertDispatched(ScrapePortalJob::class, fn ($job) => $job->portal === 'lombahub_com');
    }

    public function test_sync_mode_inserts_competitions(): void
    {
        Http::fake([
            '*/scrape' => Http::response([
                'job_id' => 'j-sync',
                'portal' => 'lombahub_com',
                'items' => [
                    [
                        'title' => 'Lomba Sync Test 2026',
                        'organizer' => 'Org Sync',
                        'description' => 'Test',
                        'registration_deadline' => '2026-12-31',
                        'level' => 'nasional',
                        'registration_fee' => 0,
                        'source_url' => 'https://lombahub.com/lomba/sync-test-2026',
                    ],
                ],
                'errors' => [],
            ], 200),
        ]);

        $this->artisan('scrape', [
            '--portal' => ['lombahub_com'],
            '--sync' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('competitions', 1);
        $c = Competition::first();
        $this->assertSame('Lomba Sync Test 2026', $c->title);
        $this->assertSame('https://lombahub.com/lomba/sync-test-2026', $c->source_url);
    }

    public function test_sync_mode_returns_failure_when_scraper_4xx(): void
    {
        Http::fake([
            '*/scrape' => Http::response('Bad Request', 400),
        ]);

        $this->artisan('scrape', [
            '--portal' => ['lombahub_com'],
            '--sync' => true,
        ])
            ->expectsOutputToContain('gagal')
            ->assertExitCode(1);
    }

    public function test_sync_mode_handles_empty_items(): void
    {
        Http::fake([
            '*/scrape' => Http::response([
                'job_id' => 'j-empty',
                'portal' => 'lombahub_com',
                'items' => [],
                'errors' => ['tidak ada link detail'],
            ], 200),
        ]);

        $this->artisan('scrape', [
            '--portal' => ['lombahub_com'],
            '--sync' => true,
        ])
            ->expectsOutputToContain('items=0')
            ->assertExitCode(0);

        $this->assertDatabaseCount('competitions', 0);
    }

    public function test_invalid_portal_filter_skipped_with_warning(): void
    {
        Bus::fake();
        $this->artisan('scrape', ['--portal' => ['unknown_portal_xyz', 'lombahub_com']])
            ->expectsOutputToContain('Portal tidak dikenal')
            ->expectsOutputToContain('Total job di-dispatch: 1')
            ->assertExitCode(0);
    }
}
