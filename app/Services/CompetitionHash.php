<?php

namespace App\Services;

/**
 * Hitung hash_md5 untuk dedup kompetisi lintas portal sumber.
 *
 * Rumus: md5(title + ISO-date of registration_deadline).
 * Konsisten dengan schema `competitions.hash_md5` (lihat AGENTS.md §3.3)
 * dan kontrak JSON scraper (lihat scraper/app/schemas.py — ada versi
 * Python yang hitung identik).
 */
class CompetitionHash
{
    public static function compute(string $title, string $isoDate): string
    {
        return md5($title . $isoDate);
    }
}
