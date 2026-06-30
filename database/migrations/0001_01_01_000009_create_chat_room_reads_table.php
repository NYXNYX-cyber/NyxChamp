<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 8 — Read receipts (lihat AGENTS.md §6b).
 *
 * Tabel `chat_room_reads` melacak posisi baca terakhir tiap user
 * per room. Composite primary key (chat_room_id, user_id) — satu
 * user hanya punya 1 read state per room.
 *
 * Cara kerja:
 * 1. Saat user buka halaman Show chat room, client auto POST ke
 *    `chat.messages.mark-read` dengan `last_message_id` = message
 *    terakhir yang terlihat di viewport.
 * 2. Backend update row `(room_id, user_id, last_read_message_id,
 *    read_at)`. INSERT ON DUPLICATE KEY UPDATE via upsert.
 * 3. Backend dispatch event `MessagesRead` ke presence channel
 *    `chat.presence.{room.id}` dengan payload `{user_id,
 *    last_read_message_id, read_at}`.
 * 4. Client lain receive event, update UI "Dibaca oleh X" di
 *    bubble message yang last_read_message_id <= message.id.
 *
 * Trade-off: pola ini LIGHT (1 row per user per room) — scalable
 * untuk 100+ user per room. Alternatif "heavy" (track per message
 * per user) tidak dipakai karena kompleksitas tidak sebanding
 * dengan value untuk portal lomba.
 *
 * Indexes:
 * - PK (chat_room_id, user_id): cukup untuk query utama.
 * - Index pada (room_id, last_read_message_id) untuk query
 *   "siapa yang sudah baca message X" (future).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_room_reads', function (Blueprint $table) {
            $table->foreignId('chat_room_id')
                ->constrained('chat_rooms')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('last_read_message_id')
                ->nullable()
                ->constrained('chat_messages')
                ->nullOnDelete();
            $table->timestamp('read_at')->useCurrent();

            $table->primary(['chat_room_id', 'user_id']);
            $table->index(['chat_room_id', 'last_read_message_id'], 'chat_room_reads_progress_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_room_reads');
    }
};
