<?php

use Illuminate\Support\Facades\Schedule;

/**
 * Penjadwalan scraping mingguan.
 *
 * AGENTS.md §3.4 + Rancangan §2:
 * - Senin 05:00 WIB (Asia/Jakarta): info siap sebelum upacara sekolah,
 *   dipajang setelahnya untuk showcase mingguan.
 * - Jumat 15:00 WIB: info segar untuk weekend planning.
 *
 * Jangan naikkan frekuensi tanpa diskusi eksplisit (lihat §3.4).
 */
Schedule::command('scrape')
    ->weeklyOn(1, '05:00')  // Senin 05:00
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(60)  // max 60 menit lock
    ->onOneServer()
    ->name('scrape-mingguan-senin')
    ->description('Scrape 6 portal Indonesia (Senin pagi, info untuk showcase setelah upacara)');

Schedule::command('scrape')
    ->weeklyOn(5, '15:00')  // Jumat 15:00
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(60)
    ->onOneServer()
    ->name('scrape-mingguan-jumat')
    ->description('Scrape 6 portal Indonesia (Jumat siang, info untuk weekend planning)');
