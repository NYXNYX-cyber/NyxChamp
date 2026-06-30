<?php

namespace Tests\Feature;

use App\Events\MessageDeleted;
use App\Events\MessageEdited;
use App\Events\MessagesRead;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\ChatRoomMember;
use App\Models\ChatRoomRead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Fase 8 — Chat polish (lihat AGENTS.md §6b):
 * - Edit message dalam window 15 menit
 * - Soft-delete message (sender atau admin)
 * - Read receipts per user-room
 * - Typing indicator (frontend-only, presence whisper)
 */
class ChatMessageEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function makeMemberRoom(User $creator): ChatRoom
    {
        $room = ChatRoom::factory()->create(['created_by' => $creator->id]);
        ChatRoomMember::create([
            'chat_room_id' => $room->id,
            'user_id' => $creator->id,
            'joined_at' => now(),
        ]);
        return $room;
    }

    // ====== EDIT ======

    public function test_user_can_edit_own_message_within_window(): void
    {
        Event::fake([MessageEdited::class]);
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($user)->create([
            'message_text' => 'halo',
        ]);

        $this->actingAs($user)
            ->patchJson(route('chat.messages.update', [$room, $msg]), ['message_text' => 'halo (revisi)'])
            ->assertOk();

        $msg->refresh();
        $this->assertSame('halo (revisi)', $msg->message_text);
        $this->assertNotNull($msg->edited_at);
        Event::assertDispatched(MessageEdited::class);
    }

    public function test_user_cannot_edit_others_message(): void
    {
        $sender = User::factory()->student()->create();
        $intruder = User::factory()->student()->create();
        $room = $this->makeMemberRoom($sender);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($sender)->create();

        $this->actingAs($intruder)
            ->patchJson(route('chat.messages.update', [$room, $msg]), ['message_text' => 'hack'])
            ->assertForbidden();

        $msg->refresh();
        $this->assertNull($msg->edited_at);
    }

    public function test_user_cannot_edit_message_outside_window(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        // Bikin message created 16 menit lalu
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($user)->create([
            'message_text' => 'lama',
            'created_at' => now()->subMinutes(16),
        ]);

        $this->actingAs($user)
            ->patchJson(route('chat.messages.update', [$room, $msg]), ['message_text' => 'baru'])
            ->assertForbidden();

        $msg->refresh();
        $this->assertSame('lama', $msg->message_text);
    }

    public function test_user_cannot_edit_deleted_message(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($user)->create([
            'deleted_at' => now(),
            'deleted_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->patchJson(route('chat.messages.update', [$room, $msg]), ['message_text' => 'restore'])
            ->assertForbidden();
    }

    public function test_edit_validates_text_required(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($user)->create();

        $this->actingAs($user)
            ->patch(route('chat.messages.update', [$room, $msg]), ['message_text' => ''])
            ->assertSessionHasErrors('message_text');
    }

    // ====== DELETE ======

    public function test_sender_can_delete_own_message(): void
    {
        Event::fake([MessageDeleted::class]);
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($user)->create();

        $this->actingAs($user)
            ->deleteJson(route('chat.messages.delete', [$room, $msg]))
            ->assertOk();

        $msg->refresh();
        $this->assertNotNull($msg->deleted_at);
        $this->assertSame($user->id, $msg->deleted_by);
        $this->assertSame('[Pesan dihapus]', $msg->displayText());
        Event::assertDispatched(MessageDeleted::class);
    }

    public function test_admin_can_delete_others_message(): void
    {
        $sender = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $room = $this->makeMemberRoom($sender);
        ChatRoomMember::create(['chat_room_id' => $room->id, 'user_id' => $admin->id, 'joined_at' => now()]);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($sender)->create();

        $this->actingAs($admin)
            ->deleteJson(route('chat.messages.delete', [$room, $msg]))
            ->assertOk();

        $msg->refresh();
        $this->assertNotNull($msg->deleted_at);
        $this->assertSame($admin->id, $msg->deleted_by);
    }

    public function test_teacher_cannot_delete_others_message(): void
    {
        $sender = User::factory()->student()->create();
        $teacher = User::factory()->teacher()->create();
        $room = $this->makeMemberRoom($teacher);
        ChatRoomMember::create(['chat_room_id' => $room->id, 'user_id' => $sender->id, 'joined_at' => now()]);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($sender)->create();

        $this->actingAs($teacher)
            ->deleteJson(route('chat.messages.delete', [$room, $msg]))
            ->assertForbidden();
    }

    public function test_double_delete_returns_403(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($user)->create([
            'deleted_at' => now(),
            'deleted_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('chat.messages.delete', [$room, $msg]))
            ->assertForbidden();
    }

    public function test_non_member_cannot_delete_message(): void
    {
        $sender = User::factory()->student()->create();
        $otherRoom = ChatRoom::factory()->create(['created_by' => $sender->id]);
        $msg = ChatMessage::factory()->inRoom($otherRoom)->fromUser($sender)->create();
        $intruder = User::factory()->student()->create();

        $this->actingAs($intruder)
            ->deleteJson(route('chat.messages.delete', [$otherRoom, $msg]))
            ->assertForbidden();
    }

    public function test_delete_payload_broadcasts_only_id(): void
    {
        Event::fake([MessageDeleted::class]);
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($user)->create();

        $this->actingAs($user)
            ->deleteJson(route('chat.messages.delete', [$room, $msg]))
            ->assertOk();

        Event::assertDispatched(MessageDeleted::class, function ($event) use ($msg, $room) {
            $payload = $event->broadcastWith();
            return $payload['id'] === $msg->id
                && $payload['room_id'] === $room->id
                && ! array_key_exists('text', $payload);
        });
    }

    // ====== READ RECEIPTS ======

    public function test_user_can_mark_room_as_read(): void
    {
        Event::fake([MessagesRead::class]);
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($user)->create();

        $this->actingAs($user)
            ->postJson(route('chat.messages.read', $room), ['last_message_id' => $msg->id])
            ->assertOk();

        $this->assertDatabaseHas('chat_room_reads', [
            'chat_room_id' => $room->id,
            'user_id' => $user->id,
            'last_read_message_id' => $msg->id,
        ]);
        Event::assertDispatched(MessagesRead::class);
    }

    public function test_mark_read_upserts_existing_record(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $msg1 = ChatMessage::factory()->inRoom($room)->fromUser($user)->create();
        $msg2 = ChatMessage::factory()->inRoom($room)->fromUser($user)->create();

        ChatRoomRead::create([
            'chat_room_id' => $room->id,
            'user_id' => $user->id,
            'last_read_message_id' => $msg1->id,
            'read_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson(route('chat.messages.read', $room), ['last_message_id' => $msg2->id])
            ->assertOk();

        $count = ChatRoomRead::where('chat_room_id', $room->id)
            ->where('user_id', $user->id)
            ->count();
        $this->assertSame(1, $count);
        $this->assertSame($msg2->id, ChatRoomRead::where('chat_room_id', $room->id)->where('user_id', $user->id)->first()->last_read_message_id);
    }

    public function test_mark_read_validates_message_belongs_to_room(): void
    {
        $user = User::factory()->student()->create();
        $room1 = $this->makeMemberRoom($user);
        $otherRoom = ChatRoom::factory()->create();
        $msgInOtherRoom = ChatMessage::factory()->inRoom($otherRoom)->fromUser($user)->create();

        // Message exists tapi di room lain — firstOrFail() throw 404
        $this->actingAs($user)
            ->post(route('chat.messages.read', $room1), ['last_message_id' => $msgInOtherRoom->id])
            ->assertNotFound();
    }

    public function test_mark_read_rejects_non_member(): void
    {
        $user = User::factory()->student()->create();
        $otherRoom = ChatRoom::factory()->create();
        $msg = ChatMessage::factory()->inRoom($otherRoom)->fromUser($user)->create();

        $this->actingAs($user)
            ->postJson(route('chat.messages.read', $otherRoom), ['last_message_id' => $msg->id])
            ->assertForbidden();
    }

    public function test_messages_read_broadcasts_to_presence_channel(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($user)->create();

        $broadcasted = null;
        \Illuminate\Support\Facades\Event::listen(MessagesRead::class, function ($event) use (&$broadcasted) {
            $broadcasted = $event;
        });

        $this->actingAs($user)
            ->post(route('chat.messages.read', $room), ['last_message_id' => $msg->id])
            ->assertRedirect();

        $this->assertNotNull($broadcasted, 'MessagesRead event was not dispatched');
        // Laravel auto-prefix PresenceChannel dengan 'presence-'
        $this->assertSame('presence-chat.presence.' . $room->id, $broadcasted->broadcastOn()[0]->name);
        $payload = $broadcasted->broadcastWith();
        $this->assertSame($user->id, $payload['user_id']);
        $this->assertSame($msg->id, $payload['last_read_message_id']);
    }
}
