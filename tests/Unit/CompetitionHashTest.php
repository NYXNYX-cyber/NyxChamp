<?php

namespace Tests\Unit;

use App\Models\Competition;
use App\Services\CompetitionHash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompetitionHashTest extends TestCase
{
    use RefreshDatabase;

    public function test_hash_md5_dihasil_otomatis_saat_create(): void
    {
        $c = Competition::create([
            'title' => 'Lomba Test',
            'organizer' => 'Org',
            'description' => 'Desc',
            'registration_deadline' => '2026-12-31',
            'level' => Competition::LEVEL_NASIONAL,
            'registration_fee' => 0,
            'source_url' => 'https://example.com',
        ]);

        $expected = CompetitionHash::compute('Lomba Test', '2026-12-31');
        $this->assertSame($expected, $c->hash_md5);
        $this->assertSame(32, strlen($c->hash_md5));
    }

    public function test_slug_terbentuk_otomatis_saat_create(): void
    {
        $c = Competition::create([
            'title' => 'Lomba Test dengan Judul Panjang',
            'organizer' => 'Org',
            'description' => 'Desc',
            'registration_deadline' => '2026-12-31',
            'level' => Competition::LEVEL_NASIONAL,
            'registration_fee' => 0,
            'source_url' => 'https://example.com',
        ]);

        $this->assertSame('lomba-test-dengan-judul-panjang', $c->slug);
    }

    public function test_slug_duplikat_mendapatkan_suffix_nomor(): void
    {
        $base = [
            'organizer' => 'Org',
            'description' => 'Desc',
            'level' => Competition::LEVEL_NASIONAL,
            'registration_fee' => 0,
            'source_url' => 'https://example.com',
        ];

        $a = Competition::create(array_merge(['title' => 'Lomba Sama', 'registration_deadline' => '2026-12-31'], $base));
        $b = Competition::create(array_merge(['title' => 'Lomba Sama', 'registration_deadline' => '2027-01-31'], $base));
        $c = Competition::create(array_merge(['title' => 'Lomba Sama', 'registration_deadline' => '2027-02-28'], $base));

        $this->assertSame('lomba-sama', $a->slug);
        $this->assertSame('lomba-sama-2', $b->slug);
        $this->assertSame('lomba-sama-3', $c->slug);
    }

    public function test_factory_menghasilkan_kompetisi_valid(): void
    {
        $c = Competition::factory()->create();
        $this->assertNotEmpty($c->slug);
        $this->assertNotEmpty($c->hash_md5);
        $this->assertContains($c->level, Competition::LEVELS);
    }

    public function test_factory_state_of_level(): void
    {
        $c = Competition::factory()->ofLevel(Competition::LEVEL_INTERNASIONAL)->create();
        $this->assertSame(Competition::LEVEL_INTERNASIONAL, $c->level);
    }

    public function test_factory_state_deadline_in_days(): void
    {
        $c = Competition::factory()->deadlineInDays(7)->create();
        $this->assertSame(now()->addDays(7)->toDateString(), $c->registration_deadline->toDateString());
    }

    public function test_dedup_melalui_hash_md5(): void
    {
        $title = 'Lomba Test Dedup';
        $deadline = '2026-12-31';
        $hash = CompetitionHash::compute($title, $deadline);

        $this->assertSame(32, strlen($hash));
        $this->assertSame($hash, CompetitionHash::compute($title, $deadline)); // deterministik
        $this->assertNotSame($hash, CompetitionHash::compute($title, '2027-01-01')); // beda deadline = beda hash
    }
}
