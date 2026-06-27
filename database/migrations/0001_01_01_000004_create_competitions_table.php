<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `competitions` (lihat AGENTS.md §3.3 & Rancangan §3).
 *
 * Kolom wajib:
 * - hash_md5 UNIQUE 32 char, dihitung dari title + registration_deadline
 *   (lihat App\Services\CompetitionHash::compute). Ini kunci dedup
 *   lintas portal sumber.
 * - slug UNIQUE untuk SEO-friendly URL.
 * - level enum kabupaten|provinsi|nasional|internasional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('organizer');
            $table->text('description');
            $table->date('registration_deadline');
            $table->enum('level', ['kabupaten', 'provinsi', 'nasional', 'internasional']);
            $table->decimal('registration_fee', 10, 2)->default(0);
            $table->text('source_url');
            $table->string('hash_md5', 32)->unique();
            $table->timestamps();

            // Index untuk filter halaman index (level + tenggat)
            $table->index(['level', 'registration_deadline'], 'competitions_level_deadline_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitions');
    }
};
