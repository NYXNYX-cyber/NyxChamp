<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel `chat_room_members` (lihat AGENTS.md §3.3 & Rancangan §3).
 *
 * Composite primary key (chat_room_id, user_id) — pasangan ini unik,
 * jadi tidak butuh id terpisah. ON DELETE CASCADE di kedua FK: kalau
 * room dihapus ATAU user dihapus, membership hilang sekalian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_room_members', function (Blueprint $table) {
            $table->foreignId('chat_room_id')
                ->constrained('chat_rooms')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamp('joined_at')->useCurrent();

            $table->primary(['chat_room_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_room_members');
    }
};
