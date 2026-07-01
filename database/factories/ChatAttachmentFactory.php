<?php

namespace Database\Factories;

use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ChatAttachment>
 */
class ChatAttachmentFactory extends Factory
{
    protected $model = ChatAttachment::class;

    public function definition(): array
    {
        $name = fake()->word().'.jpg';

        return [
            'chat_message_id' => ChatMessage::factory(),
            'uploaded_by' => User::factory(),
            'disk' => 'chat',
            'file_path' => 'room-1/'.date('Y/m').'/'.Str::ulid().'-'.$name,
            'original_name' => $name,
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(50_000, 2_000_000),
        ];
    }

    public function inMessage(ChatMessage $msg): static
    {
        return $this->state(fn () => ['chat_message_id' => $msg->id]);
    }

    public function fromUser(User $user): static
    {
        return $this->state(fn () => ['uploaded_by' => $user->id]);
    }

    public function pdf(): static
    {
        return $this->state(fn () => [
            'original_name' => 'proposal.pdf',
            'mime_type' => 'application/pdf',
            'file_path' => 'room-1/'.date('Y/m').'/'.Str::ulid().'-proposal.pdf',
        ]);
    }

    public function png(): static
    {
        return $this->state(fn () => [
            'original_name' => 'poster.png',
            'mime_type' => 'image/png',
            'file_path' => 'room-1/'.date('Y/m').'/'.Str::ulid().'-poster.png',
        ]);
    }
}
