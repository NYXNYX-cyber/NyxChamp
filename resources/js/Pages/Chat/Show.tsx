import { FormEvent, useEffect, useRef, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEcho, useEchoPresence } from '@laravel/echo-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Badge from '@/Components/Brutal/Badge';
import Button from '@/Components/Brutal/Button';
import Heading from '@/Components/Brutal/Heading';

type Sender = { id: number; name: string; role: 'student' | 'teacher' | 'admin' };
type Message = {
    id: number;
    sender: Sender;
    text: string;
    display_text: string;
    is_edited: boolean;
    is_deleted: boolean;
    created_at: string | null;
    edited_at: string | null;
};
type Read = { last_read_message_id: number | null; read_at: string | null };

type Room = {
    id: number;
    name: string;
    is_group: boolean;
    competition: { id: number; title: string; slug: string } | null;
    created_by: number;
    is_member: boolean;
    is_creator: boolean;
    current_user_id: number;
    current_user_role: 'student' | 'teacher' | 'admin';
    current_user_is_admin: boolean;
};

type PresenceUser = { id: number; name: string; role: string };

type Props = {
    room: Room;
    messages: Message[];
    members: Sender[];
    reads: Record<number, Read>;
};

const ROLE_LABELS: Record<Sender['role'], string> = {
    student: 'Siswa',
    teacher: 'Guru',
    admin: 'Admin',
};

const ROLE_VARIANT: Record<Sender['role'], 'emerald' | 'pink' | 'ink'> = {
    student: 'emerald',
    teacher: 'pink',
    admin: 'ink',
};

function formatTime(iso: string | null): string {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function Show({ room, messages: initialMessages, members, reads: initialReads }: Props) {
    const [messages, setMessages] = useState<Message[]>(initialMessages);
    const [reads, setReads] = useState<Record<number, Read>>(initialReads ?? {});
    const [presence, setPresence] = useState<Record<string, PresenceUser>>({});
    const [typingUsers, setTypingUsers] = useState<Set<number>>(new Set());
    const [editingId, setEditingId] = useState<number | null>(null);
    const bottomRef = useRef<HTMLDivElement | null>(null);
    const typingTimeoutRef = useRef<Record<number, ReturnType<typeof setTimeout>>>({});

    const form = useForm({ message_text: '' });
    const editForm = useForm({ message_text: '' });
    const inviteForm = useForm({ email: '' });
    const isAdmin = room.current_user_is_admin;

    // Live message broadcast dari channel private chat.room.{id}
    useEcho<Message>(`chat.room.${room.id}`, '.message.sent', (payload) => {
        setMessages((prev) => {
            if (prev.some((m) => m.id === payload.id)) return prev;
            return [
                ...prev,
                {
                    id: payload.id,
                    sender: payload.sender,
                    text: payload.text,
                    display_text: payload.text,
                    is_edited: false,
                    is_deleted: false,
                    created_at: payload.created_at,
                    edited_at: null,
                },
            ];
        });
        // Auto mark-read karena user lihat message baru
        markAsRead(payload.id);
    });

    // Edit broadcast: update text in-place
    useEcho<{ id: number; text: string; edited_at: string }>(
        `chat.room.${room.id}`,
        '.message.edited',
        (payload) => {
            setMessages((prev) =>
                prev.map((m) =>
                    m.id === payload.id
                        ? { ...m, text: payload.text, display_text: payload.text, is_edited: true, edited_at: payload.edited_at }
                        : m,
                ),
            );
        },
    );

    // Delete broadcast: replace dengan placeholder
    useEcho<{ id: number }>(`chat.room.${room.id}`, '.message.deleted', (payload) => {
        setMessages((prev) =>
            prev.map((m) =>
                m.id === payload.id
                    ? { ...m, is_deleted: true, display_text: '[Pesan dihapus]' }
                    : m,
            ),
        );
    });

    // Read receipt broadcast dari presence channel
    useEcho<{ user_id: number; user_name: string; last_read_message_id: number }>(
        `chat.presence.${room.id}`,
        '.messages.read',
        (payload) => {
            setReads((prev) => ({
                ...prev,
                [payload.user_id]: {
                    last_read_message_id: payload.last_read_message_id,
                    read_at: new Date().toISOString(),
                },
            }));
        },
    );

    // Presence channel untuk online + typing indicator
    useEchoPresence<PresenceUser>(`chat.presence.${room.id}`, [
        'here',
        'joining',
        'leaving',
    ], () => {
        if (typeof window !== 'undefined' && (window as any).Echo) {
            const channel = (window as any).Echo.join(`chat.presence.${room.id}`);
            channel.here((users: PresenceUser[]) => {
                const map: Record<string, PresenceUser> = {};
                users.forEach((u) => { map[String(u.id)] = u; });
                setPresence(map);
            });
            // Typing indicator via whisper (client-to-client event, no server roundtrip)
            channel.listenForWhisper('typing', (e: { userId: number }) => {
                if (e.userId === room.current_user_id) return;
                setTypingUsers((prev) => new Set(prev).add(e.userId));
                if (typingTimeoutRef.current[e.userId]) {
                    clearTimeout(typingTimeoutRef.current[e.userId]);
                }
                typingTimeoutRef.current[e.userId] = setTimeout(() => {
                    setTypingUsers((prev) => {
                        const next = new Set(prev);
                        next.delete(e.userId);
                        return next;
                    });
                }, 2500);
            });
        }
    });

    // Auto-scroll ke message terbaru
    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    // Auto mark-read saat mount dan setiap ada message baru (handled in MessageSent listener)
    useEffect(() => {
        if (messages.length > 0) {
            const lastId = messages[messages.length - 1].id;
            markAsRead(lastId);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const markAsRead = (messageId: number) => {
        // Hanya mark kalau read state masih lebih lama
        const current = reads[room.current_user_id]?.last_read_message_id ?? 0;
        if (messageId <= current) return;
        router.post(
            route('chat.messages.read', room.id),
            { last_message_id: messageId },
            { preserveScroll: true, preserveState: true },
        );
    };

    const submitMessage = (e: FormEvent) => {
        e.preventDefault();
        if (form.data.message_text.trim() === '') return;
        form.post(route('chat.messages.store', room.id), {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    const submitInvite = (e: FormEvent) => {
        e.preventDefault();
        inviteForm.post(route('chat.members.invite', room.id), {
            preserveScroll: true,
            onSuccess: () => inviteForm.reset(),
        });
    };

    const startEdit = (m: Message) => {
        setEditingId(m.id);
        editForm.setData('message_text', m.text);
    };

    const cancelEdit = () => {
        setEditingId(null);
        editForm.reset();
    };

    const submitEdit = (e: FormEvent, messageId: number) => {
        e.preventDefault();
        if (editForm.data.message_text.trim() === '') return;
        editForm.patch(route('chat.messages.update', [room.id, messageId]), {
            preserveScroll: true,
            onSuccess: () => {
                setEditingId(null);
                editForm.reset();
            },
        });
    };

    const deleteMessage = (messageId: number) => {
        if (!confirm('Hapus pesan ini? Pesan akan tetap ada di database tapi text-nya akan disembunyikan.')) {
            return;
        }
        router.delete(route('chat.messages.delete', [room.id, messageId]), {
            preserveScroll: true,
        });
    };

    // Emit typing event ke presence channel (debounced)
    const lastTypingEmitRef = useRef<number>(0);
    const emitTyping = () => {
        if (typeof window === 'undefined' || !(window as any).Echo) return;
        const now = Date.now();
        if (now - lastTypingEmitRef.current < 1000) return; // throttle 1s
        lastTypingEmitRef.current = now;
        const channel = (window as any).Echo.join(`chat.presence.${room.id}`);
        channel.whisper('typing', { userId: room.current_user_id });
    };

    const onlineCount = Object.keys(presence).length;
    const typingNames = members
        .filter((m) => typingUsers.has(m.id) && m.id !== room.current_user_id)
        .map((m) => m.name);

    // Compute read receipts per message: siapa yang sudah baca message ini
    const readersOf = (messageId: number): string[] => {
        return Object.entries(reads)
            .filter(([userId, r]) =>
                Number(userId) !== room.current_user_id
                && r.last_read_message_id !== null
                && r.last_read_message_id >= messageId
            )
            .map(([userId]) => {
                const m = members.find((mb) => mb.id === Number(userId));
                return m?.name ?? '';
            })
            .filter(Boolean);
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-3">
                    <div className="min-w-0 flex-1">
                        <Heading as="h2" className="line-clamp-1">
                            {room.name}
                        </Heading>
                        {room.competition && (
                            <Link
                                href={route('competitions.show', room.competition.slug)}
                                className="font-mono text-xs text-brutal-blue underline"
                            >
                                → {room.competition.title}
                            </Link>
                        )}
                    </div>
                    <div className="flex items-center gap-2">
                        {onlineCount > 0 && (
                            <Badge variant="emerald">{onlineCount} online</Badge>
                        )}
                        {room.is_group ? (
                            <Badge variant="yellow">Grup</Badge>
                        ) : (
                            <Badge variant="pink">1-on-1</Badge>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={`${room.name} - Chat NyxChamp`} />

            <div className="grid gap-4 lg:grid-cols-[1fr_280px]">
                {/* Kolom utama: chat messages + input */}
                <div className="flex flex-col border-3 border-ink bg-white shadow-brutal">
                    <div className="flex-1 space-y-3 overflow-y-auto p-4" style={{ maxHeight: '60vh' }}>
                        {messages.length === 0 ? (
                            <p className="py-8 text-center font-mono text-sm text-ink/50">
                                Belum ada pesan. Mulai diskusi!
                            </p>
                        ) : (
                            messages.map((m) => {
                                const isMine = m.sender.id === room.current_user_id;
                                const isEditing = editingId === m.id;
                                const readers = readersOf(m.id);
                                const canEdit = isMine && !m.is_deleted
                                    && m.created_at
                                    && new Date(m.created_at).getTime() > Date.now() - 15 * 60 * 1000;
                                const canDelete = (isMine || isAdmin) && !m.is_deleted;

                                return (
                                    <div key={m.id} className="flex flex-col">
                                        <div className="flex items-baseline gap-2">
                                            <span className="font-header text-sm font-bold">
                                                {m.sender.name}
                                            </span>
                                            <Badge variant={ROLE_VARIANT[m.sender.role]}>
                                                {ROLE_LABELS[m.sender.role]}
                                            </Badge>
                                            <span className="font-mono text-xs text-ink/50">
                                                {formatTime(m.created_at)}
                                            </span>
                                            {m.is_edited && (
                                                <span className="font-mono text-xs text-ink/40 italic">
                                                    (diedit)
                                                </span>
                                            )}
                                        </div>

                                        {isEditing ? (
                                            <form
                                                onSubmit={(e) => submitEdit(e, m.id)}
                                                className="mt-1 flex gap-2"
                                            >
                                                <input
                                                    type="text"
                                                    value={editForm.data.message_text}
                                                    onChange={(e) => {
                                                        editForm.setData('message_text', e.target.value);
                                                        emitTyping();
                                                    }}
                                                    maxLength={5000}
                                                    autoFocus
                                                    className="flex-1 border-3 border-ink bg-white px-2 py-1 font-mono text-sm shadow-brutal-sm focus:outline-none focus:ring-2 focus:ring-brutal-pink"
                                                />
                                                <Button type="submit" variant="emerald" disabled={editForm.processing}>
                                                    Simpan
                                                </Button>
                                                <button
                                                    type="button"
                                                    onClick={cancelEdit}
                                                    className="border-3 border-ink bg-cream px-3 font-header font-bold uppercase text-xs shadow-brutal-sm"
                                                >
                                                    Batal
                                                </button>
                                            </form>
                                        ) : (
                                            <div
                                                className={
                                                    'mt-1 border-2 border-ink p-2 font-mono text-sm break-words ' +
                                                    (m.is_deleted ? 'bg-cream text-ink/40 italic' : 'bg-cream text-ink')
                                                }
                                            >
                                                {m.display_text}
                                            </div>
                                        )}

                                        {!isEditing && (canEdit || canDelete) && !m.is_deleted && (
                                            <div className="mt-1 flex items-center gap-2 font-mono text-xs">
                                                {canEdit && (
                                                    <button
                                                        type="button"
                                                        onClick={() => startEdit(m)}
                                                        title="Edit pesan"
                                                        className="text-brutal-blue underline"
                                                    >
                                                        Edit
                                                    </button>
                                                )}
                                                {canEdit && canDelete && <span className="text-ink/30">·</span>}
                                                {canDelete && (
                                                    <button
                                                        type="button"
                                                        onClick={() => deleteMessage(m.id)}
                                                        title={isMine ? 'Hapus pesan' : 'Hapus pesan (moderasi admin)'}
                                                        className="text-brutal-pink underline"
                                                    >
                                                        Hapus
                                                    </button>
                                                )}
                                                {readers.length > 0 && (
                                                    <span className="ml-auto text-ink/50">
                                                        ✓ Dibaca {readers.length > 1 ? `oleh ${readers.length} orang` : ''}
                                                    </span>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                );
                            })
                        )}
                        <div ref={bottomRef} />
                    </div>

                    {/* Typing indicator */}
                    {typingNames.length > 0 && (
                        <div className="border-t-2 border-ink/10 bg-cream/50 px-3 py-1 font-mono text-xs italic text-ink/60">
                            {typingNames.join(', ')} sedang mengetik…
                        </div>
                    )}

                    <form
                        onSubmit={submitMessage}
                        className="flex gap-2 border-t-3 border-ink bg-cream p-3"
                    >
                        <input
                            type="text"
                            value={form.data.message_text}
                            onChange={(e) => {
                                form.setData('message_text', e.target.value);
                                emitTyping();
                            }}
                            placeholder="Ketik pesan…"
                            maxLength={5000}
                            disabled={form.processing}
                            className="flex-1 border-3 border-ink bg-white px-3 py-2 font-mono text-sm shadow-brutal-sm focus:outline-none focus:ring-2 focus:ring-brutal-pink"
                        />
                        <Button
                            type="submit"
                            variant="pink"
                            disabled={form.processing || form.data.message_text.trim() === ''}
                        >
                            Kirim
                        </Button>
                    </form>
                    {form.errors.message_text && (
                        <p className="border-t-2 border-ink bg-brutal-pink/20 px-3 py-2 font-mono text-xs">
                            {form.errors.message_text}
                        </p>
                    )}
                </div>

                {/* Sidebar: anggota + invite */}
                <aside className="space-y-4">
                    <div className="border-3 border-ink bg-white p-3 shadow-brutal">
                        <Heading as="h4" className="mb-2">Anggota ({members.length})</Heading>
                        <ul className="space-y-1 font-mono text-sm">
                            {members.map((m) => (
                                <li key={m.id} className="flex items-center justify-between">
                                    <span className="truncate">{m.name}</span>
                                    <span className="flex items-center gap-1">
                                        {typingUsers.has(m.id) && m.id !== room.current_user_id && (
                                            <span className="text-xs italic text-ink/50">mengetik…</span>
                                        )}
                                        {presence[String(m.id)] && (
                                            <span className="ml-1 inline-block h-2 w-2 rounded-full bg-brutal-emerald" />
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>

                    {room.is_creator && (
                        <form
                            onSubmit={submitInvite}
                            className="space-y-2 border-3 border-ink bg-white p-3 shadow-brutal"
                        >
                            <Heading as="h4">Undang Anggota</Heading>
                            <input
                                type="email"
                                value={inviteForm.data.email}
                                onChange={(e) => inviteForm.setData('email', e.target.value)}
                                placeholder="email@sekolah.id"
                                className="w-full border-3 border-ink bg-white px-2 py-1 font-mono text-sm shadow-brutal-sm focus:outline-none"
                            />
                            <Button
                                type="submit"
                                variant="yellow"
                                disabled={inviteForm.processing}
                                className="w-full"
                            >
                                Undang
                            </Button>
                            {inviteForm.errors.email && (
                                <p className="font-mono text-xs text-brutal-pink">
                                    {inviteForm.errors.email}
                                </p>
                            )}
                        </form>
                    )}
                </aside>
            </div>
        </AuthenticatedLayout>
    );
}
