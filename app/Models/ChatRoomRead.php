<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read receipt per user per room (lihat AGENTS.md §6b Fase 8).
 *
 * Composite primary key (chat_room_id, user_id). Dipakai untuk
 * render indikator "Dibaca oleh X" di message bubble dan event
 * broadcast `MessagesRead` ke presence channel.
 *
 * TIDAK punya updated_at — `read_at` di-overwrite setiap kali user
 * mark-read di posisi lebih baru.
 */
class ChatRoomRead extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'chat_room_reads';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = null;

    protected $fillable = [
        'chat_room_id',
        'user_id',
        'last_read_message_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'last_read_message_id');
    }
}
