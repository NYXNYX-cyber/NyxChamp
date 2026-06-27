<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot `chat_room_members` (lihat AGENTS.md §3.3).
 *
 * Tabel ini punya composite primary key (chat_room_id, user_id), bukan
 * id auto-increment. Laravel 11+ support pivot tanpa id lewat Model
 * biasa; di sini kita turunkan dari Pivot untuk konsistensi.
 */
class ChatRoomMember extends Pivot
{
    protected $table = 'chat_room_members';

    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = null;

    protected $fillable = [
        'chat_room_id',
        'user_id',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }
}
