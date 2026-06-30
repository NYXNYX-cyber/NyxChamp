<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event saat pesan di-soft-delete (lihat AGENTS.md §6b Fase 8).
 *
 * Payload HANYA berisi id + room_id. Text asli TIDAK di-broadcast
 * untuk mencegah bocor via WebSocket kalau ada unauthorized listener
 * (meskipun sudah di-block oleh channel auth, defense in depth).
 */
class MessageDeleted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public ChatMessage $message)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.room.' . $this->message->chat_room_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.deleted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'room_id' => $this->message->chat_room_id,
        ];
    }
}
