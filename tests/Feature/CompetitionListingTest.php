<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitionListingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CompetitionSeeder::class);
    }

    public function test_halaman_index_dapat_diakses_tanpa_login(): void
    {
        $this->get(route('competitions.index', ['status' => 'all']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Competitions/Index')
                ->has('competitions.data', 8)
            );
    }

    public function test_default_filter_hanya_tampilkan_yang_pendaftaran_masih_buka(): void
    {
        $this->get(route('competitions.index'))
            ->assertInertia(fn ($page) => $page
                ->has('competitions.data', 7) // 1 lomba di CompetitionSeeder punya deadline di masa lalu
            );
    }

    public function test_filter_status_closed_menampilkan_lomba_yang_sudah_lewat(): void
    {
        $this->get(route('competitions.index', ['status' => 'closed']))
            ->assertInertia(fn ($page) => $page
                ->has('competitions.data', 1)
            );
    }

    public function test_filter_level_nasional(): void
    {
        $this->get(route('competitions.index', ['level' => 'nasional', 'status' => 'all']))
            ->assertInertia(fn ($page) => $page
                ->where('filters.level', 'nasional')
                ->has('competitions.data', 3) // 3 nasional di seeder (omim, esai, short video)
            );
    }

    public function test_filter_level_invalid_ditolak_validasi(): void
    {
        $this->get(route('competitions.index', ['level' => 'planet-bukan-level']))
            ->assertSessionHasErrors('level');
    }

    public function test_filter_pencarian_berdasarkan_judul(): void
    {
        $this->get(route('competitions.index', ['q' => 'olimpiade', 'status' => 'all']))
            ->assertInertia(fn ($page) => $page
                ->has('competitions.data', 1) // "Olimpiade Matematika Nasional" (ejaan Indonesia: -e)
            );
    }

    public function test_filter_pencarian_berdasarkan_penyelenggara(): void
    {
        $this->get(route('competitions.index', ['q' => 'kemendikbud', 'status' => 'all']))
            ->assertInertia(fn ($page) => $page
                ->has('competitions.data', 1)
            );
    }

    public function test_halaman_show_merender_detail_lomba(): void
    {
        $comp = Competition::firstWhere('level', Competition::LEVEL_KABUPATEN);

        $this->get(route('competitions.show', $comp->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Competitions/Show')
                ->where('competition.id', $comp->id)
                ->where('competition.title', $comp->title)
            );
    }

    public function test_slug_tidak_ditemukan_memberi_404(): void
    {
        $this->get(route('competitions.show', 'lomba-tidak-ada'))
            ->assertNotFound();
    }

    public function test_scope_open_mengecualikan_deadline_masa_lalu(): void
    {
        $open = Competition::open()->count();
        $all = Competition::count();
        $this->assertLessThan($all, $open);
        $this->assertSame(7, $open);
    }

    public function test_scope_of_level_memfilter_tingkat(): void
    {
        $nasional = Competition::ofLevel(Competition::LEVEL_NASIONAL)->count();
        $this->assertSame(3, $nasional);
    }
}
