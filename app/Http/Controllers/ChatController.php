<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\ChatRoomMember;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Chat real-time controller (lihat Rancangan §4 + AGENTS.md §3.5).
 *
 * Skema URL:
 *   GET  /chat                                    list room user
 *   GET  /chat/{room}                             view room + history
 *   POST /chat/{room}/messages                    kirim pesan
 *   POST /lomba/{competition}/grup-bimbingan      guru buat grup (competition-scoped)
 *   POST /chat/{room}/members                     undang anggota (teacher/admin)
 *
 * Channel Reverb:
 *   - private chat.room.{id}: live message broadcast
 *   - presence chat.presence.{id}: online/typing indicator
 *   - public competitions.{id}: auto-broadcast kompetisi baru
 */
class ChatController extends Controller
{
    /**
     * List semua room yang user ikuti (room yang dibuat + yang di-invite).
     */
    public function index(Request $request): InertiaResponse
    {
        $user = $request->user();

        $rooms = ChatRoom::query()
            ->where(function ($q) use ($user) {
                $q->whereHas('members', fn ($mq) => $mq->where('users.id', $user->id))
                    ->orWhere('created_by', $user->id);
            })
            ->with([
                'competition:id,title,slug,registration_deadline',
                'members:id,name,role',
            ])
            ->withCount('messages')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ChatRoom $room) => [
                'id' => $room->id,
                'name' => $room->name,
                'is_group' => $room->is_group,
                'is_public_for_competition' => $room->competition_id !== null,
                'competition' => $room->competition ? [
                    'id' => $room->competition->id,
                    'title' => $room->competition->title,
                    'slug' => $room->competition->slug,
                ] : null,
                'member_count' => $room->members->count(),
                'messages_count' => $room->messages_count,
                'created_at' => $room->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Chat/Index', [
            'rooms' => $rooms,
        ]);
    }

    /**
     * Tampilkan room + history pesan (default 50 terakhir, atau ?limit=).
     * 403 kalau user bukan anggota.
     */
    public function show(Request $request, ChatRoom $room): InertiaResponse
    {
        $this->authorizeAccess($request->user(), $room);

        $messages = $room->messages()
            ->with('sender:id,name,role')
            ->orderBy('created_at')
            ->limit($request->integer('limit', 50))
            ->get()
            ->map(fn (ChatMessage $m) => [
                'id' => $m->id,
                'sender' => [
                    'id' => $m->sender->id,
                    'name' => $m->sender->name,
                    'role' => $m->sender->role,
                ],
                'text' => $m->message_text,
                'display_text' => $m->displayText(),
                'is_edited' => $m->isEdited(),
                'is_deleted' => $m->isDeleted(),
                'created_at' => $m->created_at?->toIso8601String(),
                'edited_at' => $m->edited_at?->toIso8601String(),
            ]);

        $members = $room->members()
            ->select('users.id', 'users.name', 'users.role')
            ->orderBy('users.name')
            ->get();

        // Read state: siapa yang sudah baca sampai message mana
        $reads = \App\Models\ChatRoomRead::where('chat_room_id', $room->id)
            ->get(['user_id', 'last_read_message_id', 'read_at'])
            ->mapWithKeys(fn ($r) => [
                $r->user_id => [
                    'last_read_message_id' => $r->last_read_message_id,
                    'read_at' => $r->read_at?->toIso8601String(),
                ],
            ]);

        return Inertia::render('Chat/Show', [
            'room' => [
                'id' => $room->id,
                'name' => $room->name,
                'is_group' => $room->is_group,
                'competition' => $room->competition_id ? [
                    'id' => $room->competition->id,
                    'title' => $room->competition->title,
                    'slug' => $room->competition->slug,
                ] : null,
                'created_by' => $room->created_by,
                'is_member' => $this->isMember($request->user(), $room),
                'is_creator' => (int) $room->created_by === (int) $request->user()->id,
                'current_user_id' => $request->user()->id,
            ],
            'messages' => $messages,
            'members' => $members,
            'reads' => $reads,
        ]);
    }

    /**
     * Edit pesan milik sendiri. Hanya dalam window 15 menit (lihat
     * ChatMessage::EDIT_WINDOW_MINUTES). Broadcast event MessageEdited.
     */
    public function updateMessage(Request $request, ChatRoom $room, ChatMessage $message): RedirectResponse|JsonResponse
    {
        $this->authorizeAccess($request->user(), $room);

        abort_unless((int) $message->chat_room_id === (int) $room->id, 404);
        abort_unless((int) $message->sender_id === (int) $request->user()->id, 403, 'Anda hanya bisa mengedit pesan sendiri.');
        abort_if($message->isDeleted(), 403, 'Pesan yang dihapus tidak bisa diedit.');
        abort_unless($message->isEditable(), 403, 'Batas waktu edit (' . ChatMessage::EDIT_WINDOW_MINUTES . ' menit) sudah lewat.');

        $data = $request->validate([
            'message_text' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $message->update([
            'message_text' => trim($data['message_text']),
            'edited_at' => now(),
        ]);

        broadcast(new \App\Events\MessageEdited($message))->toOthers();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => [
                    'id' => $message->id,
                    'text' => $message->message_text,
                    'edited_at' => $message->edited_at?->toIso8601String(),
                ],
            ]);
        }

        return back(303);
    }

    /**
     * Soft-delete pesan. Sender boleh hapus miliknya sendiri, admin
     * boleh hapus pesan siapa saja (moderasi spam). Broadcast event
     * MessageDeleted dengan payload minimal (id saja).
     */
    public function deleteMessage(Request $request, ChatRoom $room, ChatMessage $message): RedirectResponse|JsonResponse
    {
        $this->authorizeAccess($request->user(), $room);

        abort_unless((int) $message->chat_room_id === (int) $room->id, 404);

        $user = $request->user();
        $isOwner = (int) $message->sender_id === (int) $user->id;
        abort_unless($isOwner || $user->isAdmin(), 403, 'Hanya pengirim atau admin yang bisa menghapus pesan.');

        abort_if($message->isDeleted(), 403, 'Pesan sudah dihapus.');

        $message->update([
            'deleted_at' => now(),
            'deleted_by' => $user->id,
        ]);

        broadcast(new \App\Events\MessageDeleted($message))->toOthers();

        if ($request->wantsJson()) {
            return response()->json(['deleted' => true, 'id' => $message->id]);
        }

        return back(303);
    }

    /**
     * Tandai room "sudah dibaca" sampai message X. Dipanggil client
     * saat user pertama buka Show + saat message baru masuk ke viewport.
     * Dispatch event MessagesRead ke presence channel.
     */
    public function markRead(Request $request, ChatRoom $room): RedirectResponse|JsonResponse
    {
        $this->authorizeAccess($request->user(), $room);

        $data = $request->validate([
            'last_message_id' => ['required', 'integer', 'exists:chat_messages,id'],
        ]);

        // Validasi: message harus milik room ini
        $message = \App\Models\ChatMessage::where('id', $data['last_message_id'])
            ->where('chat_room_id', $room->id)
            ->firstOrFail();

        $read = \App\Models\ChatRoomRead::updateOrCreate(
            [
                'chat_room_id' => $room->id,
                'user_id' => $request->user()->id,
            ],
            [
                'last_read_message_id' => $message->id,
                'read_at' => now(),
            ],
        );

        broadcast(new \App\Events\MessagesRead($room, $request->user(), $message->id))->toOthers();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'last_read_message_id' => $read->last_read_message_id]);
        }

        return back(303);
    }

    /**
     * Kirim pesan ke room. Validasi: user harus anggota, text tidak kosong.
     * Dispatch event MessageSent (broadcast ke private channel).
     */
    public function storeMessage(Request $request, ChatRoom $room): RedirectResponse|JsonResponse
    {
        $this->authorizeAccess($request->user(), $room);

        $data = $request->validate([
            'message_text' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $message = ChatMessage::create([
            'chat_room_id' => $room->id,
            'sender_id' => $request->user()->id,
            'message_text' => trim($data['message_text']),
        ]);

        broadcast(new MessageSent($message))->toOthers();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => $message->only(['id', 'message_text', 'created_at']),
            ], 201);
        }

        return back(303);
    }

    /**
     * Guru/admin buat grup bimbingan untuk kompetisi tertentu.
     * Idempotent: kalau sudah ada grup untuk kompetisi ini oleh guru
     * yang sama, return existing.
     */
    public function createGroupBimbingan(Request $request, Competition $competition): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isTeacher() || $user->isAdmin(), 403, 'Hanya guru/admin yang bisa membuat grup bimbingan.');

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        $existing = ChatRoom::where('competition_id', $competition->id)
            ->where('is_group', true)
            ->where('created_by', $user->id)
            ->first();

        if ($existing) {
            return redirect()->route('chat.show', $existing);
        }

        $room = ChatRoom::create([
            'competition_id' => $competition->id,
            'name' => $data['name'] ?? ('Bimbingan: ' . $competition->title),
            'is_group' => true,
            'created_by' => $user->id,
        ]);

        // Creator otomatis jadi member.
        ChatRoomMember::firstOrCreate(
            ['chat_room_id' => $room->id, 'user_id' => $user->id],
            ['joined_at' => now()],
        );

        return redirect()->route('chat.show', $room);
    }

    /**
     * Undang user jadi anggota room (teacher/admin only, dan harus creator/admin).
     */
    public function inviteMember(Request $request, ChatRoom $room): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || (int) $room->created_by === (int) $user->id, 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

        $invitee = User::where('email', $data['email'])->firstOrFail();

        ChatRoomMember::firstOrCreate(
            ['chat_room_id' => $room->id, 'user_id' => $invitee->id],
            ['joined_at' => now()],
        );

        return back(303)->with('status', $invitee->name . ' berhasil diundang.');
    }

    /**
     * Throws 403 kalau user tidak punya akses ke room.
     */
    private function authorizeAccess(User $user, ChatRoom $room): void
    {
        if ($this->isMember($user, $room)) {
            return;
        }
        abort(403, 'Anda bukan anggota room ini.');
    }

    private function isMember(User $user, ChatRoom $room): bool
    {
        if ((int) $room->created_by === (int) $user->id) {
            return true;
        }
        return DB::table('chat_room_members')
            ->where('chat_room_id', $room->id)
            ->where('user_id', $user->id)
            ->exists();
    }
}
