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
    created_at: string | null;
};

type Room = {
    id: number;
    name: string;
    is_group: boolean;
    competition: { id: number; title: string; slug: string } | null;
    created_by: number;
    is_member: boolean;
    is_creator: boolean;
};

type PresenceUser = { id: number; name: string; role: string };

type Props = {
    room: Room;
    messages: Message[];
    members: Sender[];
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

export default function Show({ room, messages: initialMessages, members }: Props) {
    const [messages, setMessages] = useState<Message[]>(initialMessages);
    const [presence, setPresence] = useState<Record<string, PresenceUser>>({});
    const bottomRef = useRef<HTMLDivElement | null>(null);

    const form = useForm({ message_text: '' });
    const inviteForm = useForm({ email: '' });

    // Live message broadcast dari channel private chat.room.{id}.
    useEcho<Message>(`chat.room.${room.id}`, '.message.sent', (payload) => {
        setMessages((prev) => {
            if (prev.some((m) => m.id === payload.id)) return prev;
            return [
                ...prev,
                {
                    id: payload.id,
                    sender: payload.sender,
                    text: payload.text,
                    created_at: payload.created_at,
                },
            ];
        });
    });

    // Presence channel untuk indikator online + jumlah user aktif.
    useEchoPresence<PresenceUser>(`chat.presence.${room.id}`, [
        'here',
        'joining',
        'leaving',
    ], () => {
        // Re-fetch presence list via window.echo untuk akurasi.
        // (Helper useEchoPresence callback pattern: trigger manual reload.)
        if (typeof window !== 'undefined' && (window as any).Echo) {
            const channel = (window as any).Echo.join(`chat.presence.${room.id}`);
            channel.here((users: PresenceUser[]) => {
                const map: Record<string, PresenceUser> = {};
                users.forEach((u) => { map[String(u.id)] = u; });
                setPresence(map);
            });
        }
    });

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

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

    const onlineCount = Object.keys(presence).length;

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
                            messages.map((m) => (
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
                                    </div>
                                    <div className="mt-1 border-2 border-ink bg-cream p-2 font-mono text-sm break-words">
                                        {m.text}
                                    </div>
                                </div>
                            ))
                        )}
                        <div ref={bottomRef} />
                    </div>

                    <form
                        onSubmit={submitMessage}
                        className="flex gap-2 border-t-3 border-ink bg-cream p-3"
                    >
                        <input
                            type="text"
                            value={form.data.message_text}
                            onChange={(e) => form.setData('message_text', e.target.value)}
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
                                    {presence[String(m.id)] && (
                                        <span className="ml-2 inline-block h-2 w-2 rounded-full bg-brutal-emerald" />
                                    )}
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
