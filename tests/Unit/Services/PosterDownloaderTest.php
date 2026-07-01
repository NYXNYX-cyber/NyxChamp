<?php

namespace Tests\Unit\Services;

use App\Models\Competition;
use App\Services\PosterDownloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Test PosterDownloader — service yang download poster lomba dari
 * URL portal sumber dan simpan ke disk private 'competitions'.
 *
 * Kritis karena: file storage tidak boleh expose di public/, harus
 * disk private + StreamedResponse, dan validation Content-Type
 * wajib untuk avoid XSS via SVG (lihat juga Fase 9 attachment).
 */
class PosterDownloaderTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompetition(): Competition
    {
        return Competition::create([
            'title' => 'Test Lomba',
            'slug' => 'test-lomba',
            'organizer' => 'Test',
            'description' => 'Test',
            'registration_deadline' => '2026-12-31',
            'level' => 'nasional',
            'registration_fee' => 0,
            'source_url' => 'https://example.com/lomba/test',
            'hash_md5' => md5('Test Lomba2026-12-31'),
        ]);
    }

    public function test_downloads_jpeg_to_disk_and_returns_path(): void
    {
        Http::fake([
            'lombahub.com/*' => Http::response(
                "\xff\xd8\xff\xe0" . str_repeat('x', 1000),
                200,
                ['Content-Type' => 'image/jpeg'],
            ),
        ]);

        $comp = $this->makeCompetition();
        $dl = new PosterDownloader();

        $path = $dl->download($comp, 'https://lombahub.com/img/poster.jpg');

        $this->assertNotNull($path);
        $this->assertStringStartsWith($comp->id . '/poster-', $path);
        $this->assertStringEndsWith('.jpg', $path);
        $this->assertTrue(Storage::disk('competitions')->exists($path));
    }

    public function test_returns_null_for_invalid_url(): void
    {
        $comp = $this->makeCompetition();
        $dl = new PosterDownloader();

        $this->assertNull($dl->download($comp, ''));
        $this->assertNull($dl->download($comp, 'not-a-url'));
        $this->assertNull($dl->download($comp, 'file:///etc/passwd'));
        $this->assertNull($dl->download($comp, 'javascript:alert(1)'));
    }

    public function test_skips_svg_to_prevent_xss(): void
    {
        Http::fake([
            '*' => Http::response(
                '<svg onload="alert(1)"></svg>',
                200,
                ['Content-Type' => 'image/svg+xml'],
            ),
        ]);

        $comp = $this->makeCompetition();
        $dl = new PosterDownloader();
        $path = $dl->download($comp, 'https://example.com/x.svg');

        $this->assertNull($path, 'SVG harus ditolak (XSS prevention, sama dgn Fase 9)');
    }

    public function test_rejects_response_larger_than_5mb(): void
    {
        Http::fake([
            '*' => Http::response(
                str_repeat('A', 6 * 1024 * 1024), // 6MB
                200,
                ['Content-Type' => 'image/jpeg'],
            ),
        ]);

        $comp = $this->makeCompetition();
        $dl = new PosterDownloader();
        $path = $dl->download($comp, 'https://example.com/huge.jpg');

        $this->assertNull($path);
    }

    public function test_rejects_non_2xx_response(): void
    {
        Http::fake([
            '*' => Http::response('not found', 404, ['Content-Type' => 'text/html']),
        ]);

        $comp = $this->makeCompetition();
        $dl = new PosterDownloader();
        $path = $dl->download($comp, 'https://example.com/missing.jpg');

        $this->assertNull($path);
    }

    public function test_is_idempotent_when_competition_already_has_poster(): void
    {
        $comp = $this->makeCompetition();
        $comp->poster_path = '99/poster-existing.jpg';
        $comp->save();

        $dl = new PosterDownloader();
        $path = $dl->download($comp, 'https://example.com/new.jpg');

        $this->assertEquals('99/poster-existing.jpg', $path);
    }

    public function test_returns_null_when_url_is_empty(): void
    {
        $comp = $this->makeCompetition();
        $dl = new PosterDownloader();
        $this->assertNull($dl->download($comp, null));
    }
}
