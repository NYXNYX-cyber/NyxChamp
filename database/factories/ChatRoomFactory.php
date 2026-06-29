<?php

namespace Database\Factories;

use App\Models\ChatRoom;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatRoom>
 */
class ChatRoomFactory extends Factory
{
    protected $model = ChatRoom::class;

    public function definition(): array
    {
        return [
            'competition_id' => null,
            'name' => 'Diskusi: ' . fake()->sentence(3),
            'is_group' => true,
            'created_by' => User::factory()->admin(),
        ];
    }

    /**
     * Grup bimbingan yang terikat kompetisi tertentu.
     */
    public function forCompetition(Competition $competition): static
    {
        return $this->state(fn () => [
            'competition_id' => $competition->id,
            'name' => 'Bimbingan: ' . $competition->title,
        ]);
    }

    /**
     * Grup diskusi internal (non-kompetisi).
     */
    public function standalone(): static
    {
        return $this->state(fn () => [
            'competition_id' => null,
            'name' => 'Diskusi Internal: ' . fake()->word(),
        ]);
    }
}
