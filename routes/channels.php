<?php

use App\Models\ChatRoom;
use App\Models\ChatRoomMember;
use Illuminate\Support\Facades\Broadcast;

/*
 * Channel authorization untuk Reverb.
 *
 * Lihat Rancangan §4 + AGENTS.md §3.5:
 * - chat.room.{id} (private) — hanya anggota room
 * - chat.presence.{id} (presence) — anggota room + emit info online
 * - competitions.{id} (public) — auto-siarkan kompetisi baru (lihat
 *   NewCompetitionDetected event; channel public, tanpa auth khusus)
 */

Broadcast::channel('chat.room.{roomId}', function ($user, int $roomId) {
    $room = ChatRoom::find($roomId);
    if (! $room) {
        return false;
    }
    return ChatRoomMember::where('chat_room_id', $room->id)
        ->where('user_id', $user->id)
        ->exists() || (int) $room->created_by === (int) $user->id;
});

Broadcast::channel('chat.presence.{roomId}', function ($user, int $roomId) {
    $room = ChatRoom::find($roomId);
    if (! $room) {
        return false;
    }
    $isMember = ChatRoomMember::where('chat_room_id', $room->id)
        ->where('user_id', $user->id)
        ->exists() || (int) $room->created_by === (int) $user->id;
    if (! $isMember) {
        return false;
    }
    // Return array = presence channel akan emit join/leave dengan info ini.
    return [
        'id' => $user->id,
        'name' => $user->name,
        'role' => $user->role,
    ];
});
