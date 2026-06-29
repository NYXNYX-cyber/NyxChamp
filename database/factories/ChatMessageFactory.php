<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    protected $model = ChatMessage::class;

    public function definition(): array
    {
        return [
            'chat_room_id' => ChatRoom::factory(),
            'sender_id' => User::factory(),
            'message_text' => fake()->sentence(),
        ];
    }

    public function inRoom(ChatRoom $room): static
    {
        return $this->state(fn () => ['chat_room_id' => $room->id]);
    }

    public function fromUser(User $user): static
    {
        return $this->state(fn () => ['sender_id' => $user->id]);
    }
}
