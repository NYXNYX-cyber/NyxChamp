<?php

namespace App\Events;

use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event saat user menandai room "sudah dibaca" (lihat
 * AGENTS.md §6b Fase 8).
 *
 * Disiarkan ke PRESENCE channel `chat.presence.{room.id}` supaya
 * client lain di room yang sama bisa update indikator "Dibaca oleh X"
 * di message bubble.
 *
 * Tidak pakai PrivateChannel karena read receipt BUKA ke semua
 * anggota room (siapa yang sudah baca = info publik di antara
 * anggota). Presence channel cocok: ada join/leave tracking + emit
 * ke semua yang sedang online.
 */
class MessagesRead implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public ChatRoom $room,
        public User $user,
        public ?int $lastReadMessageId,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('chat.presence.' . $this->room->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'messages.read';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'last_read_message_id' => $this->lastReadMessageId,
            'read_at' => now()->toIso8601String(),
        ];
    }
}
