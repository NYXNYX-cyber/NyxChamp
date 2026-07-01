import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import GuestLayout from '@/Layouts/GuestLayout';
import Badge from '@/Components/Brutal/Badge';
import Heading from '@/Components/Brutal/Heading';
import { PageProps } from '@/types';

type Level = 'kabupaten' | 'provinsi' | 'nasional' | 'internasional';

const LEVEL_VARIANT: Record<Level, 'yellow' | 'pink' | 'emerald' | 'ink'> = {
    kabupaten: 'emerald',
    provinsi: 'pink',
    nasional: 'yellow',
    internasional: 'ink',
};

const LEVEL_LABEL: Record<Level, string> = {
    kabupaten: 'Tingkat Kabupaten',
    provinsi: 'Tingkat Provinsi',
    nasional: 'Tingkat Nasional',
    internasional: 'Tingkat Internasional',
};

type Props = PageProps & {
    competition: {
        id: number;
        title: string;
        slug: string;
        organizer: string;
        description: string;
        level: Level;
        registration_deadline: string | null;
        registration_fee: number;
        source_url: string;
        is_open: boolean;
        rooms_count: number;
        has_poster: boolean;
        poster_url: string | null;
    };
    auth: {
        user: { role: 'student' | 'teacher' | 'admin' } | null;
    };
};

function formatRupiah(value: number): string {
    if (value === 0) return 'Gratis';
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(value);
}

function formatDeadline(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function daysLeft(iso: string | null): number | null {
    if (!iso) return null;
    const target = new Date(iso);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const diff = target.getTime() - today.getTime();
    return Math.ceil(diff / (1000 * 60 * 60 * 24));
}

/**
 * Render deskripsi panjang. Mendukung baris kosong sebagai paragraf
 * terpisah (paragraf dipisah dengan \n\n) dan bold sederhana via **teks**.
 * Tidak pakai HTML mentah — ini adalah perlindungan XSS minimal untuk
 * teks yang berasal dari scraper (lihat AGENTS.md §3.4).
 */
function renderDescription(text: string) {
    const paragraphs = text.split(/\n\n+/).filter(Boolean);
    return paragraphs.map((para, i) => {
        const parts = para.split(/\*\*(.+?)\*\*/g);
        return (
            <p key={i} className="mb-4 leading-relaxed text-ink">
                {parts.map((part, j) =>
                    j % 2 === 1 ? (
                        <strong key={j} className="font-bold">
                            {part}
                        </strong>
                    ) : (
                        <span key={j}>{part}</span>
                    ),
                )}
            </p>
        );
    });
}

export default function Show({ auth, competition }: Props) {
    const Layout = auth.user ? AuthenticatedLayout : GuestLayout;
    const user = auth.user;
    const canCreateRoom = user?.role === 'teacher' || user?.role === 'admin';
    const days = daysLeft(competition.registration_deadline);

    return (
        <Layout>
            <Head title={competition.title} />

            <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-6">
                    <Link
                        href="/lomba"
                        className="inline-flex items-center gap-1 font-mono text-xs font-bold uppercase text-brutal-blue underline"
                    >
                        ← Kembali ke daftar lomba
                    </Link>
                </div>

                {competition.has_poster && competition.poster_url && (
                    <div className="mb-6 overflow-hidden border-4 border-ink bg-cream shadow-brutal">
                        <img
                            src={competition.poster_url}
                            alt={`Poster ${competition.title}`}
                            className="block w-full object-cover"
                            loading="lazy"
                        />
                    </div>
                )}

                <article className="brutal-box p-6 sm:p-8">
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <Badge variant={LEVEL_VARIANT[competition.level]}>
                            {LEVEL_LABEL[competition.level]}
                        </Badge>
                        {competition.is_open ? (
                            <Badge variant="emerald">Pendaftaran Buka</Badge>
                        ) : (
                            <Badge variant="ink">Pendaftaran Ditutup</Badge>
                        )}
                    </div>

                    <Heading as="h1" className="mb-3">
                        {competition.title}
                    </Heading>

                    <p className="mb-6 font-mono text-sm uppercase text-ink/70">
                        Diselenggarakan oleh {competition.organizer}
                    </p>

                    <dl className="mb-8 grid gap-3 border-y-2 border-ink/10 py-5 sm:grid-cols-3">
                        <div>
                            <dt className="font-mono text-xs uppercase text-ink/60">Tenggat</dt>
                            <dd className="font-header text-base font-bold text-ink">
                                {formatDeadline(competition.registration_deadline)}
                            </dd>
                            {competition.is_open && days !== null && (
                                <p className="mt-1 font-mono text-xs font-bold text-brutal-emerald">
                                    {days} hari lagi
                                </p>
                            )}
                        </div>
                        <div>
                            <dt className="font-mono text-xs uppercase text-ink/60">Biaya</dt>
                            <dd className="font-header text-base font-bold text-ink">
                                {formatRupiah(competition.registration_fee)}
                            </dd>
                        </div>
                        <div>
                            <dt className="font-mono text-xs uppercase text-ink/60">Grup Bimbingan</dt>
                            <dd className="font-header text-base font-bold text-ink">
                                {competition.rooms_count} grup aktif
                            </dd>
                        </div>
                    </dl>

                    <div className="prose-brutal mb-8 font-sans">
                        {renderDescription(competition.description)}
                    </div>

                    <div className="flex flex-col gap-3 border-t-2 border-ink/10 pt-6 sm:flex-row sm:items-center">
                        <a
                            href={competition.source_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="brutal-btn-yellow"
                        >
                            Buka Sumber Portal ↗
                        </a>

                        {canCreateRoom ? (
                            <button
                                type="button"
                                onClick={() =>
                                    router.post(route('competitions.groups.create', competition.slug))
                                }
                                className="brutal-btn"
                            >
                                Buat Grup Bimbingan
                            </button>
                        ) : user ? (
                            <p className="font-mono text-xs text-ink/60">
                                Hanya guru yang dapat membuat grup bimbingan.
                            </p>
                        ) : (
                            <Link
                                href={route('login')}
                                className="font-mono text-xs font-bold uppercase text-brutal-blue underline"
                            >
                                Masuk untuk mengikuti lomba ini
                            </Link>
                        )}
                    </div>
                </article>
            </div>
        </Layout>
    );
}
