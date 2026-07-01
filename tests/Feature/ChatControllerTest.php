<?php

namespace Tests\Feature;

use App\Events\MessageSent;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\ChatRoomMember;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_chat_index(): void
    {
        $this->get(route('chat.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_sees_only_their_rooms_in_index(): void
    {
        $alice = User::factory()->student()->create();
        $bob = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();

        $aliceRoom = ChatRoom::factory()->create(['created_by' => $alice->id]);
        ChatRoomMember::create(['chat_room_id' => $aliceRoom->id, 'user_id' => $alice->id, 'joined_at' => now()]);

        $bobRoom = ChatRoom::factory()->create(['created_by' => $bob->id]);
        ChatRoomMember::create(['chat_room_id' => $bobRoom->id, 'user_id' => $bob->id, 'joined_at' => now()]);

        $response = $this->actingAs($alice)->get(route('chat.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Chat/Index')
            ->has('rooms', 1)
            ->where('rooms.0.id', $aliceRoom->id)
        );
    }

    public function test_user_sees_room_they_are_invited_to(): void
    {
        $alice = User::factory()->student()->create();
        $teacher = User::factory()->teacher()->create();

        $room = ChatRoom::factory()->create(['created_by' => $teacher->id]);
        ChatRoomMember::create(['chat_room_id' => $room->id, 'user_id' => $alice->id, 'joined_at' => now()]);

        $this->actingAs($alice)->get(route('chat.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('rooms', 1)
                ->where('rooms.0.id', $room->id)
            );
    }

    public function test_show_returns_room_with_messages(): void
    {
        $user = User::factory()->student()->create();
        $room = ChatRoom::factory()->create(['created_by' => $user->id]);
        ChatMessage::factory()->count(3)->inRoom($room)->fromUser($user)->create();

        $this->actingAs($user)->get(route('chat.show', $room))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Chat/Show')
                ->where('room.id', $room->id)
                ->where('room.is_creator', true)
                ->has('messages', 3)
                ->has('members')
            );
    }

    public function test_show_forbidden_for_non_member(): void
    {
        $user = User::factory()->student()->create();
        $otherUser = User::factory()->student()->create();
        $room = ChatRoom::factory()->create(['created_by' => $otherUser->id]);

        $this->actingAs($user)->get(route('chat.show', $room))
            ->assertForbidden();
    }

    public function test_store_message_validates_text_required(): void
    {
        $user = User::factory()->student()->create();
        $room = ChatRoom::factory()->create(['created_by' => $user->id]);

        // Fase 9: boleh kirim text kosong KALAU ada attachment.
        // Tanpa text + tanpa attachment → 422.
        $this->actingAs($user)
            ->post(route('chat.messages.store', $room), ['message_text' => ''])
            ->assertStatus(422);
    }

    public function test_store_message_validates_max_length(): void
    {
        $user = User::factory()->student()->create();
        $room = ChatRoom::factory()->create(['created_by' => $user->id]);

        $this->actingAs($user)
            ->post(route('chat.messages.store', $room), ['message_text' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('message_text');
    }

    public function test_store_message_persists_and_broadcasts(): void
    {
        Event::fake([MessageSent::class]);

        $user = User::factory()->student()->create();
        $room = ChatRoom::factory()->create(['created_by' => $user->id]);

        $response = $this->actingAs($user)
            ->postJson(route('chat.messages.store', $room), ['message_text' => 'Halo semua']);

        $response->assertCreated();
        $this->assertDatabaseHas('chat_messages', [
            'chat_room_id' => $room->id,
            'sender_id' => $user->id,
            'message_text' => 'Halo semua',
        ]);
        Event::assertDispatched(MessageSent::class);
    }

    public function test_store_message_rejects_non_member(): void
    {
        $other = User::factory()->student()->create();
        $room = ChatRoom::factory()->create(['created_by' => $other->id]);
        $intruder = User::factory()->student()->create();

        $this->actingAs($intruder)
            ->post(route('chat.messages.store', $room), ['message_text' => 'paksa masuk'])
            ->assertForbidden();

        $this->assertDatabaseMissing('chat_messages', ['message_text' => 'paksa masuk']);
    }

    public function test_create_group_bimbingan_requires_teacher(): void
    {
        $student = User::factory()->student()->create();
        $competition = Competition::factory()->create();

        $this->actingAs($student)
            ->post(route('competitions.groups.create', $competition))
            ->assertForbidden();
    }

    public function test_create_group_bimbingan_succeeds_for_teacher(): void
    {
        $teacher = User::factory()->teacher()->create();
        $competition = Competition::factory()->create();

        $response = $this->actingAs($teacher)
            ->post(route('competitions.groups.create', $competition));

        $response->assertRedirect();
        $this->assertDatabaseHas('chat_rooms', [
            'competition_id' => $competition->id,
            'created_by' => $teacher->id,
            'is_group' => true,
        ]);
        $room = ChatRoom::where('competition_id', $competition->id)->first();
        $this->assertDatabaseHas('chat_room_members', [
            'chat_room_id' => $room->id,
            'user_id' => $teacher->id,
        ]);
    }

    public function test_create_group_bimbingan_is_idempotent_for_same_teacher(): void
    {
        $teacher = User::factory()->teacher()->create();
        $competition = Competition::factory()->create();

        $this->actingAs($teacher)->post(route('competitions.groups.create', $competition))->assertRedirect();
        $this->actingAs($teacher)->post(route('competitions.groups.create', $competition))->assertRedirect();

        $count = ChatRoom::where('competition_id', $competition->id)
            ->where('created_by', $teacher->id)
            ->count();
        $this->assertSame(1, $count);
    }

    public function test_invite_member_adds_user_to_room(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $room = ChatRoom::factory()->create(['created_by' => $teacher->id]);

        $this->actingAs($teacher)
            ->post(route('chat.members.invite', $room), ['email' => $student->email])
            ->assertRedirect();

        $this->assertDatabaseHas('chat_room_members', [
            'chat_room_id' => $room->id,
            'user_id' => $student->id,
        ]);
    }

    public function test_invite_member_requires_creator_or_admin(): void
    {
        $teacher1 = User::factory()->teacher()->create();
        $teacher2 = User::factory()->teacher()->create();
        $student = User::factory()->student()->create();
        $room = ChatRoom::factory()->create(['created_by' => $teacher1->id]);

        $this->actingAs($teacher2)
            ->post(route('chat.members.invite', $room), ['email' => $student->email])
            ->assertForbidden();
    }

    public function test_invite_member_validates_email_exists(): void
    {
        $teacher = User::factory()->teacher()->create();
        $room = ChatRoom::factory()->create(['created_by' => $teacher->id]);

        $this->actingAs($teacher)
            ->post(route('chat.members.invite', $room), ['email' => 'tidak-ada@example.com'])
            ->assertSessionHasErrors('email');
    }
}
