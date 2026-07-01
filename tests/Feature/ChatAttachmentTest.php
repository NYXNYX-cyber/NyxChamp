<?php

namespace Tests\Feature;

use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\ChatRoomMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fase 9 — File attachment di chat (lihat AGENTS.md §6b).
 *
 * Aturan:
 * - Max 5 attachment per message
 * - Image max 5MB, doc max 10MB
 * - MIME whitelist: jpeg/png/gif/webp/pdf/doc/docx/xls/xlsx (SVG DITOLAK)
 * - Akses hanya anggota room (private disk)
 * - Soft-delete message: attachment tetap di storage tapi sembunyi di UI
 * - Hard-delete message: cascade hapus attachment
 */
class ChatAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Pakai disk fake 'chat' di storage/framework/testing-disk-chat.
        // Reload disk config di test (default 'local' tidak konflik).
        Storage::fake('chat');
    }

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

    private function fakeJpg(string $name = 'photo.jpg', int $kb = 500): UploadedFile
    {
        // 1×1 pixel JPEG, padded dengan data dummy sampai ukuran tertentu.
        $base = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAr/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAA/AKpgP//Z');
        $bytes = str_repeat("\x00", max(0, $kb * 1024 - strlen($base)));
        return UploadedFile::fake()->createWithContent($name, $base.$bytes);
    }

    private function fakePdf(string $name = 'proposal.pdf', int $kb = 1000): UploadedFile
    {
        $content = "%PDF-1.4\n%".str_repeat("\x00", max(0, $kb * 1024 - 10));
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function fakeDocx(string $name = 'cv.docx'): UploadedFile
    {
        // DOCX is zip-based. Buat dummy zip-ish.
        $content = "PK\x03\x04".str_repeat("\x00", 1000);
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    // ===== UPLOAD =====

    public function test_student_can_upload_image_attachment_with_text(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $file = $this->fakeJpg();

        $response = $this->actingAs($user)
            ->post(route('chat.messages.store', $room), [
                'message_text' => 'Lihat poster',
                'attachments' => [$file],
            ]);

        $response->assertRedirect();
        $msg = ChatMessage::where('chat_room_id', $room->id)->first();
        $this->assertNotNull($msg);
        $this->assertSame('Lihat poster', $msg->message_text);
        $this->assertCount(1, $msg->attachments);
        $att = $msg->attachments->first();
        $this->assertSame('photo.jpg', $att->original_name);
        Storage::disk('chat')->assertExists($att->file_path);
    }

    public function test_can_upload_attachments_only_no_text(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);

        $this->actingAs($user)
            ->post(route('chat.messages.store', $room), [
                'attachments' => [$this->fakeJpg()],
            ])
            ->assertRedirect();

        $msg = ChatMessage::where('chat_room_id', $room->id)->first();
        $this->assertNotNull($msg);
        $this->assertSame('', $msg->message_text);
        $this->assertCount(1, $msg->attachments);
    }

    public function test_rejects_empty_message_no_text_no_files(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);

        $this->actingAs($user)
            ->post(route('chat.messages.store', $room), [])
            ->assertStatus(422);
    }

    public function test_rejects_invalid_mime_svg_xss(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $svg = UploadedFile::fake()->createWithContent('x.svg', '<svg onload="alert(1)"></svg>');

        $this->actingAs($user)
            ->post(route('chat.messages.store', $room), [
                'message_text' => 'hack',
                'attachments' => [$svg],
            ])
            ->assertSessionHasErrors('attachments.0');
    }

    public function test_rejects_exe_renamed_to_jpg(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $exe = UploadedFile::fake()->create('malware.jpg', 100, 'application/x-msdownload');

        $this->actingAs($user)
            ->post(route('chat.messages.store', $room), [
                'message_text' => 'virus',
                'attachments' => [$exe],
            ])
            ->assertSessionHasErrors('attachments.0');
    }

    public function test_rejects_oversize_image_over_5mb(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $huge = $this->fakeJpg('huge.jpg', 6_000); // 6 MB

        $this->actingAs($user)
            ->post(route('chat.messages.store', $room), [
                'attachments' => [$huge],
            ])
            ->assertStatus(422);
    }

    public function test_rejects_oversize_pdf_over_10mb(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $huge = $this->fakePdf('huge.pdf', 11_000); // 11 MB

        $this->actingAs($user)
            ->post(route('chat.messages.store', $room), [
                'attachments' => [$huge],
            ])
            ->assertStatus(422);
    }

    public function test_rejects_more_than_5_attachments(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $files = collect(range(1, 6))->map(fn ($i) => $this->fakeJpg("p{$i}.jpg", 100))->all();

        $this->actingAs($user)
            ->post(route('chat.messages.store', $room), [
                'attachments' => $files,
            ])
            ->assertSessionHasErrors('attachments');
    }

    public function test_non_member_cannot_upload(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $room = $this->makeMemberRoom($student);

        $this->actingAs($other)
            ->post(route('chat.messages.store', $room), [
                'message_text' => 'intrusi',
                'attachments' => [$this->fakeJpg()],
            ])
            ->assertForbidden();
    }

    public function test_pdf_and_docx_allowed(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);

        $this->actingAs($user)
            ->post(route('chat.messages.store', $room), [
                'message_text' => 'Lampiran',
                'attachments' => [$this->fakePdf(), $this->fakeDocx()],
            ])
            ->assertRedirect();

        $msg = ChatMessage::where('chat_room_id', $room->id)->first();
        $this->assertCount(2, $msg->attachments);
        $this->assertEqualsCanonicalizing(
            ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            $msg->attachments->pluck('mime_type')->all(),
        );
    }

    // ===== DOWNLOAD =====

    public function test_member_can_download_attachment(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($user)->create();
        $att = ChatAttachment::factory()->inMessage($msg)->fromUser($user)->create();
        Storage::disk('chat')->put($att->file_path, 'CONTENT');

        $response = $this->actingAs($user)
            ->get(route('chat.attachments.download', ['room' => $room, 'attachment' => $att]));

        $response->assertOk();
        $response->assertHeader('Content-Disposition');
    }

    public function test_non_member_cannot_download(): void
    {
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $room = $this->makeMemberRoom($student);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($student)->create();
        $att = ChatAttachment::factory()->inMessage($msg)->fromUser($student)->create();
        Storage::disk('chat')->put($att->file_path, 'SECRET');

        $this->actingAs($other)
            ->get(route('chat.attachments.download', ['room' => $room, 'attachment' => $att]))
            ->assertForbidden();
    }

    public function test_download_404_if_file_missing_from_disk(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($user)->create();
        $att = ChatAttachment::factory()->inMessage($msg)->fromUser($user)->create();
        // Tidak put file

        $this->actingAs($user)
            ->get(route('chat.attachments.download', ['room' => $room, 'attachment' => $att]))
            ->assertNotFound();
    }

    // ===== SOFT DELETE / HARD DELETE =====

    public function test_soft_deleted_message_hides_attachments_in_show(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($user)->create([
            'message_text' => 'rahasia',
        ]);
        $att = ChatAttachment::factory()->inMessage($msg)->fromUser($user)->create();
        Storage::disk('chat')->put($att->file_path, 'x');

        // Hapus pesan (soft)
        $this->actingAs($user)
            ->delete(route('chat.messages.delete', ['room' => $room, 'message' => $msg]))
            ->assertRedirect();

        $msg->refresh()->load('attachments');

        $this->actingAs($user)
            ->get(route('chat.show', $room))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('messages.0.is_deleted', true)
                ->where('messages.0.attachments', [])
            );
    }

    public function test_hard_delete_message_cascades_attachments(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($user)->create();
        $att = ChatAttachment::factory()->inMessage($msg)->fromUser($user)->create();
        Storage::disk('chat')->put($att->file_path, 'x');

        $msgId = $msg->id;
        $attId = $att->id;
        $path = $att->file_path;

        // Hard delete
        $msg->forceDelete();

        $this->assertDatabaseMissing('chat_attachments', ['id' => $attId]);
        $this->assertDatabaseMissing('chat_messages', ['id' => $msgId]);
        // File di disk masih ada (tidak auto-clean) — biarkan cron cleanup
        Storage::disk('chat')->assertExists($path);
    }

    // ===== SHOW PAYLOAD =====

    public function test_show_includes_attachments_payload(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($user)->create([
            'message_text' => 'dengan lampiran',
        ]);
        ChatAttachment::factory()->inMessage($msg)->fromUser($user)->png()->create();
        Storage::disk('chat')->put('x', 'x'); // Placeholder

        $this->actingAs($user)
            ->get(route('chat.show', $room))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('messages.0.attachments.0.is_image', true)
                ->where('messages.0.attachments.0.is_pdf', false)
                ->where('messages.0.attachments.0.is_document', false)
                ->has('messages.0.attachments.0.download_url')
            );
    }

    // ===== UPLOAD ATTACHMENT ADDITIVELY =====

    public function test_sender_can_add_attachment_after_text(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($user)->create([
            'message_text' => 'awal',
        ]);

        $this->actingAs($user)
            ->post(route('chat.messages.attachments.store', ['room' => $room, 'message' => $msg]), [
                'attachments' => [$this->fakeJpg()],
            ])
            ->assertRedirect();

        $this->assertCount(1, $msg->fresh()->attachments);
    }

    public function test_non_sender_cannot_add_attachment(): void
    {
        $sender = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $room = $this->makeMemberRoom($sender);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($sender)->create();
        ChatRoomMember::create(['chat_room_id' => $room->id, 'user_id' => $other->id, 'joined_at' => now()]);

        $this->actingAs($other)
            ->post(route('chat.messages.attachments.store', ['room' => $room, 'message' => $msg]), [
                'attachments' => [$this->fakeJpg()],
            ])
            ->assertForbidden();
    }

    public function test_cannot_add_attachment_to_deleted_message(): void
    {
        $user = User::factory()->student()->create();
        $room = $this->makeMemberRoom($user);
        $msg = ChatMessage::factory()->inRoom($room)->fromUser($user)->create([
            'deleted_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('chat.messages.attachments.store', ['room' => $room, 'message' => $msg]), [
                'attachments' => [$this->fakeJpg()],
            ])
            ->assertForbidden();
    }
}
