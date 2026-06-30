<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event saat pesan di-edit (lihat AGENTS.md §6b Fase 8).
 *
 * Payload: text baru + edited_at timestamp. Client pakai ini untuk
 * update bubble tanpa refetch history.
 */
class MessageEdited implements ShouldBroadcast
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
        return 'message.edited';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'room_id' => $this->message->chat_room_id,
            'text' => $this->message->message_text,
            'edited_at' => $this->message->edited_at?->toIso8601String(),
        ];
    }
}
