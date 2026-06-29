import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Badge from '@/Components/Brutal/Badge';
import Heading from '@/Components/Brutal/Heading';

type Room = {
    id: number;
    name: string;
    is_group: boolean;
    is_public_for_competition: boolean;
    competition: { id: number; title: string; slug: string } | null;
    member_count: number;
    messages_count: number;
    created_at: string | null;
};

type Props = {
    rooms: Room[];
};

export default function Index({ rooms }: Props) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <Heading as="h2">Ruang Chat</Heading>
                    <Badge variant="emerald">{rooms.length} room</Badge>
                </div>
            }
        >
            <Head title="Chat - NyxChamp" />

            <div className="space-y-4">
                {rooms.length === 0 ? (
                    <div className="border-3 border-ink bg-white p-8 text-center shadow-brutal">
                        <Heading as="h3" className="mb-2">
                            Belum ada ruang chat
                        </Heading>
                        <p className="font-mono text-sm text-ink/70">
                            Ruang chat publik otomatis dibuat saat ada lomba baru
                            terdeteksi. Untuk ruang bimbingan tertutup, guru dapat
                            membuatnya dari halaman detail lomba.
                        </p>
                    </div>
                ) : (
                    <div className="grid gap-4 md:grid-cols-2">
                        {rooms.map((room) => (
                            <Link
                                key={room.id}
                                href={route('chat.show', room.id)}
                                className="group block"
                            >
                                <article className="border-3 border-ink bg-white p-4 shadow-brutal transition-all group-hover:-translate-x-[3px] group-hover:-translate-y-[3px] group-hover:shadow-brutal-hover">
                                    <div className="mb-2 flex items-start justify-between gap-2">
                                        <Heading as="h4" className="line-clamp-2 flex-1">
                                            {room.name}
                                        </Heading>
                                        {room.is_public_for_competition ? (
                                            <Badge variant="emerald">Publik</Badge>
                                        ) : (
                                            <Badge variant="pink">Bimbingan</Badge>
                                        )}
                                    </div>

                                    {room.competition && (
                                        <p className="mb-3 font-mono text-xs text-ink/60">
                                            → {room.competition.title}
                                        </p>
                                    )}

                                    <div className="flex items-center gap-2 font-mono text-xs">
                                        <Badge variant="default">
                                            {room.member_count} anggota
                                        </Badge>
                                        <Badge variant="yellow">
                                            {room.messages_count} pesan
                                        </Badge>
                                    </div>
                                </article>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
