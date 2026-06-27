<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pesan individual di dalam chat room (lihat Rancangan §3 + AGENTS.md §3.3).
 *
 * Pesan immutable: tidak ada updated_at. ON DELETE CASCADE dari
 * `chat_room_id` artinya hapus room = hapus semua pesannya. `sender_id`
 * RESTRICT supaya user yang punya histori pesan tidak bisa hilang
 * tanpa kebijakan eksplisit.
 */
class ChatMessage extends Model
{
    /** @use HasFactory<\Database\Factories\ChatMessageFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'chat_room_id',
        'sender_id',
        'message_text',
    ];

    public function room(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
