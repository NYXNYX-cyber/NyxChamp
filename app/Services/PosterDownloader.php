<?php

namespace App\Services;

use App\Models\Competition;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Download poster/gambar utama kompetisi dari URL portal sumber
 * dan simpan ke storage/app/competitions/ (disk private 'competitions').
 *
 * Race-safe: pakai ulid untuk nama file.
 * Idempotent: kalau competition sudah punya poster_path, skip.
 * Defensive: timeout 15s, max 5MB, validate Content-Type.
 */
class PosterDownloader
{
    /** Batas ukuran download. */
    private const MAX_BYTES = 5 * 1024 * 1024; // 5MB

    /** Content-Type yang diizinkan. */
    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /**
     * Download poster untuk 1 competition. Return path relative
     * terhadap disk (mis. "{id}/poster-{ulid}.jpg"), atau null kalau
     * tidak ada URL / gagal download.
     */
    public function download(Competition $competition, ?string $imageUrl): ?string
    {
        if (empty($imageUrl)) {
            return null;
        }

        // Idempotent: kalau sudah punya poster, skip.
        if (! empty($competition->poster_path)) {
            return $competition->poster_path;
        }

        // Validasi URL.
        if (! filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            Log::debug('poster download skip: invalid URL', [
                'competition_id' => $competition->id,
                'url' => $imageUrl,
            ]);
            return null;
        }

        // Hanya izinkan http/https.
        $scheme = parse_url($imageUrl, PHP_URL_SCHEME);
        if (! in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'NyxChamp-Bot/1.0 (+https://nyxchamp.test)',
            ])
                ->timeout(15)
                ->connectTimeout(5)
                ->get($imageUrl);
        } catch (ConnectionException $e) {
            Log::warning('poster download connection failed', [
                'competition_id' => $competition->id,
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        } catch (RequestException $e) {
            Log::warning('poster download HTTP error', [
                'competition_id' => $competition->id,
                'url' => $imageUrl,
                'status' => $e->response?->status(),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::warning('poster download unexpected', [
                'competition_id' => $competition->id,
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (! $response->successful()) {
            Log::debug('poster download non-2xx', [
                'competition_id' => $competition->id,
                'url' => $imageUrl,
                'status' => $response->status(),
            ]);
            return null;
        }

        $contentType = strtolower(trim(explode(';', $response->header('Content-Type') ?? '')[0]));
        if (! in_array($contentType, self::ALLOWED_MIME, true)) {
            Log::debug('poster download skip: bad content-type', [
                'competition_id' => $competition->id,
                'url' => $imageUrl,
                'content_type' => $contentType,
            ]);
            return null;
        }

        $body = $response->body();
        if (strlen($body) > self::MAX_BYTES) {
            Log::warning('poster download too large', [
                'competition_id' => $competition->id,
                'url' => $imageUrl,
                'bytes' => strlen($body),
            ]);
            return null;
        }
        if (strlen($body) === 0) {
            return null;
        }

        $ext = $this->extFromMime($contentType);
        $ulid = (string) Str::ulid();
        $relativePath = "{$competition->id}/poster-{$ulid}.{$ext}";

        $disk = Storage::disk('competitions');
        $disk->put($relativePath, $body);

        return $relativePath;
    }

    private function extFromMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'bin',
        };
    }
}
