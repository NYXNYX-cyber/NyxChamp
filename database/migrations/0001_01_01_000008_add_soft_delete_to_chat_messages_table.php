<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8 — Chat polish (lihat AGENTS.md §6b).
 *
 * Tambah 3 kolom ke `chat_messages`:
 * - `edited_at` (nullable timestamp): kapan pesan diedit terakhir kali.
 *   NULL = belum pernah diedit.
 * - `deleted_at` (nullable timestamp): soft delete. Pesan dengan nilai
 *   non-NULL tidak ditampilkan text-nya di UI (ditampilkan placeholder
 *   "[Pesan dihapus]") tapi ROW tetap ada untuk audit trail.
 * - `deleted_by` (nullable FK ke users): siapa yang hapus. RESTRICT
 *   supaya user yang pernah hapus pesan tidak bisa hilang tanpa
 *   kebijakan eksplisit (konsisten dengan `sender_id`).
 *
 * Indexes:
 * - `chat_messages_room_time_idx` sudah ada (dari migrasi 0007).
 * - Tidak perlu index tambahan — query utama tetap by room_id +
 *   created_at, dan deleted_at/edited_at bukan kolom pencarian.
 *
 * Kolom `message_text` TIDAK di-null-kan saat delete: text asli tetap
 * ada di DB untuk audit, hanya UI yang menyembunyikan via
 * `Message::isDeleted()` accessor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->timestamp('edited_at')->nullable()->after('message_text');
            $table->timestamp('deleted_at')->nullable()->after('edited_at');
            $table->foreignId('deleted_by')
                ->nullable()
                ->after('deleted_at')
                ->constrained('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropColumn(['edited_at', 'deleted_at']);
        });
    }
};
