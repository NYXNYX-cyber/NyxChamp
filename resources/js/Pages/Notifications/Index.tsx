import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import PrimaryButton from '@/Components/PrimaryButton';

interface NotificationData {
    type: 'new_competition' | 'chat_invitation';
    competition_id?: number;
    title?: string;
    slug?: string;
    organizer?: string;
    level?: string;
    chat_room_id?: number;
    chat_room_name?: string;
    inviter_id?: number;
    inviter_name?: string;
    competition_title?: string;
    message: string;
}

interface Notification {
    id: string;
    type: string;
    data: NotificationData;
    read_at: string | null;
    created_at: string;
}

interface PaginatedNotifications {
    data: Notification[];
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
}

interface Props {
    notifications: PaginatedNotifications;
}

export default function Index({ notifications }: Props) {
    const handleMarkAsRead = (id: string) => {
        router.post(route('notifications.read', id), {}, {
            preserveScroll: true,
        });
    };

    const handleMarkAllAsRead = () => {
        router.post(route('notifications.read-all'), {}, {
            preserveScroll: true,
        });
    };

    const formatDate = (dateStr: string) => {
        const date = new Date(dateStr);
        return date.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const hasUnread = notifications.data.some((n) => n.read_at === null);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex justify-between items-center">
                    <h2 className="text-xl font-bold text-black font-header leading-tight">
                        Notifikasi Saya
                    </h2>
                    {hasUnread && (
                        <button
                            onClick={handleMarkAllAsRead}
                            className="px-4 py-2 border-3 border-black text-sm font-header font-bold text-black bg-yellow-brutal hover:bg-yellow-brutal-hover transition-all shadow-brutal-sm active:translate-x-[2px] active:translate-y-[2px] active:shadow-none"
                        >
                            Tandai Semua Dibaca
                        </button>
                    )}
                </div>
            }
        >
            <Head title="Notifikasi" />

            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8 space-y-6">
                    {notifications.data.length === 0 ? (
                        <div className="bg-white p-8 border-4 border-black shadow-brutal text-center">
                            <p className="text-gray-500 font-mono">Belum ada notifikasi masuk.</p>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {notifications.data.map((notification) => {
                                const isUnread = notification.read_at === null;
                                const data = notification.data;

                                return (
                                    <div
                                        key={notification.id}
                                        className={`p-6 border-4 border-black shadow-brutal transition-all flex flex-col md:flex-row justify-between items-start md:items-center gap-4 ${
                                            isUnread ? 'bg-amber-50' : 'bg-white'
                                        }`}
                                    >
                                        <div className="space-y-2 flex-1">
                                            <div className="flex items-center gap-2">
                                                {/* Type Badge */}
                                                {data.type === 'new_competition' ? (
                                                    <span className="px-2 py-0.5 border-2 border-black bg-pink-brutal text-black text-xs font-mono font-bold">
                                                        Lomba Baru
                                                    </span>
                                                ) : (
                                                    <span className="px-2 py-0.5 border-2 border-black bg-emerald-400 text-black text-xs font-mono font-bold">
                                                        Undangan Chat
                                                    </span>
                                                )}

                                                {/* Date */}
                                                <span className="text-xs text-gray-500 font-mono">
                                                    {formatDate(notification.created_at)}
                                                </span>

                                                {isUnread && (
                                                    <span className="px-1.5 py-0.5 bg-red-500 border border-black text-white text-[10px] font-bold font-mono">
                                                        BARU
                                                    </span>
                                                )}
                                            </div>

                                            <p className="text-base text-black font-header font-bold">
                                                {data.message}
                                            </p>

                                            {/* Context metadata */}
                                            {data.type === 'new_competition' && (
                                                <div className="flex flex-wrap gap-2 text-xs font-mono text-gray-600">
                                                    <span>Tingkat: <strong className="text-black capitalize">{data.level}</strong></span>
                                                    <span>•</span>
                                                    <span>Penyelenggara: <strong className="text-black">{data.organizer}</strong></span>
                                                </div>
                                            )}

                                            {data.type === 'chat_invitation' && (
                                                <div className="flex flex-wrap gap-2 text-xs font-mono text-gray-600">
                                                    <span>Pengundang: <strong className="text-black">{data.inviter_name}</strong></span>
                                                    {data.competition_title && (
                                                        <>
                                                            <span>•</span>
                                                            <span>Kompetisi: <strong className="text-black">{data.competition_title}</strong></span>
                                                        </>
                                                    )}
                                                </div>
                                            )}
                                        </div>

                                        <div className="flex items-center gap-3 w-full md:w-auto justify-end">
                                            {/* Action Button */}
                                            {data.type === 'new_competition' && data.slug && (
                                                <Link
                                                    href={route('competitions.show', data.slug)}
                                                    className="px-4 py-2 border-3 border-black text-xs font-header font-bold text-black bg-white hover:bg-beige-light transition-all shadow-brutal-sm"
                                                >
                                                    Lihat Lomba
                                                </Link>
                                            )}

                                            {data.type === 'chat_invitation' && data.chat_room_id && (
                                                <Link
                                                    href={route('chat.show', data.chat_room_id)}
                                                    className="px-4 py-2 border-3 border-black text-xs font-header font-bold text-white bg-blue-600 hover:bg-blue-700 transition-all shadow-brutal-sm"
                                                >
                                                    Buka Chat
                                                </Link>
                                            )}

                                            {isUnread && (
                                                <button
                                                    onClick={() => handleMarkAsRead(notification.id)}
                                                    className="px-4 py-2 border-3 border-black text-xs font-header font-bold text-black bg-yellow-brutal hover:bg-yellow-brutal-hover transition-all shadow-brutal-sm active:translate-x-[2px] active:translate-y-[2px] active:shadow-none"
                                                >
                                                    Tandai Dibaca
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}

                            {/* Pagination */}
                            {notifications.links.length > 3 && (
                                <div className="mt-8 flex justify-center gap-2">
                                    {notifications.links.map((link, idx) => (
                                        <Link
                                            key={idx}
                                            href={link.url || '#'}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                            className={`px-4 py-2 border-3 border-black text-sm font-header font-bold transition-all shadow-brutal-sm ${
                                                link.active
                                                    ? 'bg-yellow-brutal text-black shadow-none translate-x-[2px] translate-y-[2px]'
                                                    : 'bg-white text-black hover:bg-beige-light hover:translate-x-[-2px] hover:translate-y-[-2px] hover:shadow-brutal'
                                            } ${!link.url ? 'opacity-50 cursor-not-allowed' : ''}`}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
