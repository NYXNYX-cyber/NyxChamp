<?php

namespace Tests\Feature;

use App\Jobs\ScrapePortalJob;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Admin ScraperController — manual scrape trigger + health check.
 *
 * Use case: jadwal scraping otomatis hanya 2x seminggu (Senin+Jumat).
 * Admin butuh trigger manual kalau ada info lomba urgent mid-week.
 */
class AdminScraperControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $response = $this->get(route('admin.dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_student_cannot_access_admin_dashboard(): void
    {
        $student = User::factory()->student()->create();
        $this->actingAs($student)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_teacher_cannot_access_admin_dashboard(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->actingAs($teacher)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admin_can_view_dashboard_with_stats(): void
    {
        $admin = User::factory()->admin()->create();
        Competition::factory()->count(3)->create([
            'level' => Competition::LEVEL_NASIONAL,
            'registration_deadline' => now()->addDays(7)->toDateString(),
        ]);
        Competition::factory()->create([
            'level' => Competition::LEVEL_PROVINSI,
            'registration_deadline' => now()->subDays(2)->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard')
                ->where('stats.total', 4)
                ->where('stats.open', 3)
                ->where('stats.closed', 1)
                ->where('stats.by_level.nasional', 3)
                ->where('stats.by_level.provinsi', 1)
            );
    }

    public function test_admin_can_trigger_scrape_and_dispatches_jobs(): void
    {
        Bus::fake([ScrapePortalJob::class]);
        Cache::flush();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.scrape.trigger'), ['max_pages' => 5])
            ->assertRedirect();

        // 6 portal × 1 job = 6 jobs dispatched ke queue 'scraping'
        Bus::assertDispatchedTimes(ScrapePortalJob::class, 6);
    }

    public function test_trigger_uses_default_max_pages_when_not_specified(): void
    {
        Bus::fake([ScrapePortalJob::class]);
        Cache::flush();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.scrape.trigger'))
            ->assertRedirect();

        // Verify max_pages default 5
        Bus::assertDispatched(ScrapePortalJob::class, function (ScrapePortalJob $job) {
            return $job->maxPages === 5;
        });
    }

    public function test_trigger_has_cooldown_5_minutes(): void
    {
        Bus::fake([ScrapePortalJob::class]);
        Cache::flush();
        $admin = User::factory()->admin()->create();

        // First trigger: OK
        $this->actingAs($admin)
            ->post(route('admin.scrape.trigger'))
            ->assertRedirect();

        Bus::assertDispatchedTimes(ScrapePortalJob::class, 6);

        // Second trigger immediately: rejected (cooldown)
        $this->actingAs($admin)
            ->post(route('admin.scrape.trigger'))
            ->assertRedirect()
            ->assertSessionHasErrors('trigger');

        // Job count tidak bertambah
        Bus::assertDispatchedTimes(ScrapePortalJob::class, 6);
    }

    public function test_student_cannot_trigger_scrape(): void
    {
        Bus::fake([ScrapePortalJob::class]);
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->post(route('admin.scrape.trigger'))
            ->assertForbidden();

        Bus::assertNotDispatched(ScrapePortalJob::class);
    }

    public function test_teacher_cannot_trigger_scrape(): void
    {
        Bus::fake([ScrapePortalJob::class]);
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)
            ->post(route('admin.scrape.trigger'))
            ->assertForbidden();

        Bus::assertNotDispatched(ScrapePortalJob::class);
    }

    public function test_admin_can_check_scraper_health(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.scrape.health'))
            ->assertRedirect();

        // Health bisa ok atau error tergantung scraper running,
        // tapi yang penting: response redirect + session flash 'health' set
        $response->assertSessionHas('health');
    }
}
