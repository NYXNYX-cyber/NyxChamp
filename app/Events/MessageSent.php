<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event saat ada pesan baru di chat room.
 *
 * Spec (lihat AGENTS.md §3.5 + Rancangan §4):
 * - Private channel `chat.room.{id}` — otorisasi via session Laravel.
 * - Hanya anggota room (lihat pivot `chat_room_members`) yang boleh listen.
 * - Payload JSON kecil (id + sender + text + timestamp) — client
 *   fetch detail tambahan kalau perlu.
 */
class MessageSent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public ChatMessage $message)
    {
    }

    /**
     * Channel tempat event ini disiarkan.
     * Private karena akses chat room harus divalidasi.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.room.' . $this->message->chat_room_id),
        ];
    }

    /**
     * Nama event di client side (`useEcho('chat.room.{id}', '.message.sent', ...)`).
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Payload yang dikirim ke client. Hindari ngirim model utuh —
     * pilih field eksplisit + relasi minimal.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->message->loadMissing('sender:id,name,role');

        return [
            'id' => $this->message->id,
            'room_id' => $this->message->chat_room_id,
            'sender' => [
                'id' => $this->message->sender->id,
                'name' => $this->message->sender->name,
                'role' => $this->message->sender->role,
            ],
            'text' => $this->message->message_text,
            'created_at' => $this->message->created_at?->toIso8601String(),
        ];
    }
}
