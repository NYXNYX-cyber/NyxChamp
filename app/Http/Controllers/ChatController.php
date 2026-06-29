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
                'created_at' => $m->created_at?->toIso8601String(),
            ]);

        $members = $room->members()
            ->select('users.id', 'users.name', 'users.role')
            ->orderBy('users.name')
            ->get();

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
            ],
            'messages' => $messages,
            'members' => $members,
        ]);
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
