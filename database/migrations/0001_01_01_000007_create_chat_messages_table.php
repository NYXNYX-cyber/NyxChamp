<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `chat_messages` (lihat AGENTS.md §3.3 & Rancangan §3).
 *
 * - chat_room_id ON DELETE CASCADE: hapus room = hapus semua pesannya.
 * - sender_id RESTRICT: tidak boleh hapus user yang masih punya pesan
 *   (integritas histori). Kalau mau hapus user, set null atau soft-delete.
 * - created_at tanpa updated_at: pesan immutable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_room_id')
                ->constrained('chat_rooms')
                ->cascadeOnDelete();
            $table->foreignId('sender_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('message_text');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['chat_room_id', 'created_at'], 'chat_messages_room_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
