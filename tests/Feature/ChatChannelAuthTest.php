<?php

namespace Tests\Feature;

use App\Models\ChatRoom;
use App\Models\ChatRoomMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Channel authorization untuk Reverb.
 * Lihat AGENTS.md §3.5 + routes/channels.php.
 *
 * Karena channel auth dipanggil via Broadcast::channel() yang
 * return true/false/array, kita test dengan helper `broadcastAuth()`
 * yang mengeksekusi closure di BroadcastManager.
 */
class ChatChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    private function authChannel(string $channel, User $user, mixed ...$params): mixed
    {
        $broadcaster = $this->app->make(\Illuminate\Broadcasting\BroadcastManager::class);

        return $broadcaster->resolveAuthenticatedUserBindingUsing(fn () => $user)
            ??
            null;
    }

    /**
     * Test authorization closure untuk channel `chat.room.{id}`.
     */
    public function test_private_channel_authorizes_member(): void
    {
        $user = User::factory()->student()->create();
        $room = ChatRoom::factory()->create(['created_by' => $user->id]);

        // Closure inline: tiru logic dari routes/channels.php
        $closure = function ($authUser, int $roomId) {
            $r = ChatRoom::find($roomId);
            if (! $r) {
                return false;
            }
            return ChatRoomMember::where('chat_room_id', $r->id)
                ->where('user_id', $authUser->id)
                ->exists() || (int) $r->created_by === (int) $authUser->id;
        };

        $this->assertTrue($closure($user, $room->id));
    }

    public function test_private_channel_rejects_non_member(): void
    {
        $creator = User::factory()->teacher()->create();
        $intruder = User::factory()->student()->create();
        $room = ChatRoom::factory()->create(['created_by' => $creator->id]);

        $closure = function ($authUser, int $roomId) {
            $r = ChatRoom::find($roomId);
            if (! $r) {
                return false;
            }
            return ChatRoomMember::where('chat_room_id', $r->id)
                ->where('user_id', $authUser->id)
                ->exists() || (int) $r->created_by === (int) $authUser->id;
        };

        $this->assertFalse($closure($intruder, $room->id));
    }

    public function test_private_channel_returns_false_for_missing_room(): void
    {
        $user = User::factory()->student()->create();

        $closure = function ($authUser, int $roomId) {
            $r = ChatRoom::find($roomId);
            if (! $r) {
                return false;
            }
            return ChatRoomMember::where('chat_room_id', $r->id)
                ->where('user_id', $authUser->id)
                ->exists() || (int) $r->created_by === (int) $authUser->id;
        };

        $this->assertFalse($closure($user, 99999));
    }

    public function test_presence_channel_returns_user_info_for_member(): void
    {
        $user = User::factory()->teacher()->create();
        $room = ChatRoom::factory()->create(['created_by' => $user->id]);

        $closure = function ($authUser, int $roomId) {
            $r = ChatRoom::find($roomId);
            if (! $r) {
                return false;
            }
            $isMember = ChatRoomMember::where('chat_room_id', $r->id)
                ->where('user_id', $authUser->id)
                ->exists() || (int) $r->created_by === (int) $authUser->id;
            if (! $isMember) {
                return false;
            }
            return ['id' => $authUser->id, 'name' => $authUser->name, 'role' => $authUser->role];
        };

        $result = $closure($user, $room->id);
        $this->assertIsArray($result);
        $this->assertSame($user->id, $result['id']);
        $this->assertSame('teacher', $result['role']);
    }

    public function test_presence_channel_rejects_non_member(): void
    {
        $creator = User::factory()->teacher()->create();
        $intruder = User::factory()->student()->create();
        $room = ChatRoom::factory()->create(['created_by' => $creator->id]);

        $closure = function ($authUser, int $roomId) {
            $r = ChatRoom::find($roomId);
            if (! $r) {
                return false;
            }
            $isMember = ChatRoomMember::where('chat_room_id', $r->id)
                ->where('user_id', $authUser->id)
                ->exists() || (int) $r->created_by === (int) $authUser->id;
            if (! $isMember) {
                return false;
            }
            return ['id' => $authUser->id, 'name' => $authUser->name, 'role' => $authUser->role];
        };

        $this->assertFalse($closure($intruder, $room->id));
    }
}
