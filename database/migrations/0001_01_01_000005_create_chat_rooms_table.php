<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `chat_rooms` (lihat AGENTS.md §3.3 & Rancangan §3).
 *
 * Aturan penting:
 * - competition_id NULLABLE: grup non-kompetisi (mis. diskusi internal)
 *   diperbolehkan (lihat AGENTS.md §3.3).
 * - is_group: true untuk grup bimbingan, false untuk 1-on-1 (untuk
 *   ekstensi masa depan; saat ini hampir semua grup adalah is_group=true).
 * - created_by: user yang inisiasi grup. Untuk room publik hasil
 *   auto-create dari scraper, nilainya = admin sistem (lihat listener
 *   di Fase 9).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')
                ->nullable()
                ->constrained('competitions')
                ->nullOnDelete();
            $table->string('name');
            $table->boolean('is_group')->default(false);
            $table->foreignId('created_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('competition_id', 'chat_rooms_competition_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_rooms');
    }
};
