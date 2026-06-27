import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import GuestLayout from '@/Layouts/GuestLayout';
import CompetitionCard, { CompetitionCardData } from '@/Components/Brutal/CompetitionCard';
import Button from '@/Components/Brutal/Button';
import Heading from '@/Components/Brutal/Heading';
import { PageProps } from '@/types';

type Filters = {
    level: string | null;
    status: string;
    q: string | null;
};

type Props = PageProps & {
    competitions: {
        data: CompetitionCardData[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: Filters;
    levels: string[];
};

const STATUS_LABEL: Record<string, string> = {
    open: 'Pendaftaran Buka',
    closed: 'Sudah Ditutup',
    all: 'Semua',
};

const STATUS_BUTTON: Record<string, 'emerald' | 'ink' | 'default'> = {
    open: 'emerald',
    closed: 'ink',
    all: 'default',
};

export default function Index({ auth, competitions, filters, levels }: Props) {
    const [q, setQ] = useState(filters.q ?? '');
    const Layout = auth.user ? AuthenticatedLayout : GuestLayout;

    const applyFilter = (next: Partial<Filters>) => {
        router.get(
            '/lomba',
            { ...filters, ...next, q: next.q !== undefined ? next.q : filters.q },
            { preserveState: true, replace: true },
        );
    };

    const onSearch = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilter({ q: q.trim() || null });
    };

    const clearFilters = () => {
        setQ('');
        router.get('/lomba', {}, { preserveState: true, replace: true });
    };

    const hasActiveFilter = filters.level || filters.status !== 'open' || filters.q;

    return (
        <Layout>
            <Head title="Lomba" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <Heading as="h1" className="mb-1">
                            Daftar Lomba
                        </Heading>
                        <p className="font-mono text-sm text-ink/70">
                            Agregasi mingguan dari 6 portal lomba Indonesia.
                        </p>
                    </div>

                    <form onSubmit={onSearch} className="flex w-full gap-2 sm:w-auto">
                        <input
                            type="search"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Cari judul / penyelengara..."
                            className="brutal-input flex-1 sm:w-72"
                        />
                        <Button type="submit" variant="ink">Cari</Button>
                    </form>
                </div>

                <div className="grid gap-8 lg:grid-cols-[16rem_1fr]">
                    <aside className="space-y-6">
                        <div className="brutal-box p-4">
                            <h3 className="mb-3 font-header text-sm font-bold uppercase tracking-wider text-ink">
                                Status
                            </h3>
                            <div className="flex flex-col gap-2">
                                {Object.entries(STATUS_LABEL).map(([value, label]) => {
                                    const active = (filters.status ?? 'open') === value;
                                    return (
                                        <button
                                            key={value}
                                            type="button"
                                            onClick={() => applyFilter({ status: value })}
                                            className={
                                                'border-3 border-ink px-3 py-1.5 text-left font-mono text-xs font-bold uppercase transition-transform ' +
                                                (active
                                                    ? 'translate-x-[2px] translate-y-[2px] bg-ink text-cream shadow-brutal-sm'
                                                    : 'bg-white text-ink shadow-brutal hover:-translate-x-[2px] hover:-translate-y-[2px] hover:shadow-brutal-lg')
                                            }
                                        >
                                            {label}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        <div className="brutal-box p-4">
                            <h3 className="mb-3 font-header text-sm font-bold uppercase tracking-wider text-ink">
                                Tingkat
                            </h3>
                            <div className="flex flex-col gap-2">
                                <button
                                    type="button"
                                    onClick={() => applyFilter({ level: null })}
                                    className={
                                        'border-3 border-ink px-3 py-1.5 text-left font-mono text-xs font-bold uppercase transition-transform ' +
                                        (!filters.level
                                            ? 'translate-x-[2px] translate-y-[2px] bg-ink text-cream shadow-brutal-sm'
                                            : 'bg-white text-ink shadow-brutal hover:-translate-x-[2px] hover:-translate-y-[2px] hover:shadow-brutal-lg')
                                    }
                                >
                                    Semua Tingkat
                                </button>
                                {levels.map((level) => {
                                    const active = filters.level === level;
                                    return (
                                        <button
                                            key={level}
                                            type="button"
                                            onClick={() => applyFilter({ level })}
                                            className={
                                                'border-3 border-ink px-3 py-1.5 text-left font-mono text-xs font-bold uppercase transition-transform ' +
                                                (active
                                                    ? 'translate-x-[2px] translate-y-[2px] bg-ink text-cream shadow-brutal-sm'
                                                    : 'bg-white text-ink shadow-brutal hover:-translate-x-[2px] hover:-translate-y-[2px] hover:shadow-brutal-lg')
                                            }
                                        >
                                            {level}
                                        </button>
                                    );
                                })}
                            </div>
                        </div>

                        {hasActiveFilter && (
                            <button
                                type="button"
                                onClick={clearFilters}
                                className="font-mono text-xs font-bold uppercase text-brutal-blue underline"
                            >
                                Reset semua filter
                            </button>
                        )}
                    </aside>

                    <section>
                        {competitions.data.length === 0 ? (
                            <div className="brutal-box border-dashed p-12 text-center">
                                <p className="mb-2 font-display text-2xl font-bold text-ink">
                                    Belum ada lomba yang cocok
                                </p>
                                <p className="mb-4 font-mono text-sm text-ink/70">
                                    Coba ubah filter atau kata kunci pencarian.
                                </p>
                                <Button type="button" variant="pink" onClick={clearFilters}>
                                    Reset Filter
                                </Button>
                            </div>
                        ) : (
                            <>
                                <p className="mb-4 font-mono text-xs text-ink/60">
                                    Menampilkan {competitions.from}–{competitions.to} dari {competitions.total} lomba
                                </p>

                                <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                                    {competitions.data.map((c) => (
                                        <CompetitionCard key={c.id} competition={c} />
                                    ))}
                                </div>

                                {competitions.last_page > 1 && (
                                    <nav className="mt-8 flex flex-wrap items-center justify-center gap-2">
                                        {competitions.links.map((link, i) => (
                                            <Link
                                                key={i}
                                                href={link.url ?? '#'}
                                                preserveState
                                                className={
                                                    'border-3 border-ink px-3 py-1.5 font-mono text-xs font-bold uppercase ' +
                                                    (link.active
                                                        ? 'translate-x-[2px] translate-y-[2px] bg-ink text-cream shadow-brutal-sm'
                                                        : 'bg-white text-ink shadow-brutal hover:-translate-x-[2px] hover:-translate-y-[2px] hover:shadow-brutal-lg') +
                                                    (link.url ? '' : ' pointer-events-none opacity-40')
                                                }
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ))}
                                    </nav>
                                )}
                            </>
                        )}
                    </section>
                </div>
            </div>
        </Layout>
    );
}
