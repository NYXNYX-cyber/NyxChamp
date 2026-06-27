import { Link } from '@inertiajs/react';
import Badge from './Badge';

type Level = 'kabupaten' | 'provinsi' | 'nasional' | 'internasional';

export type CompetitionCardData = {
    id: number;
    title: string;
    slug: string;
    organizer: string;
    level: Level;
    registration_deadline: string | null;
    registration_fee: number;
    is_open: boolean;
};

const LEVEL_VARIANT: Record<Level, 'yellow' | 'pink' | 'emerald' | 'ink'> = {
    kabupaten: 'emerald',
    provinsi: 'pink',
    nasional: 'yellow',
    internasional: 'ink',
};

const LEVEL_LABEL: Record<Level, string> = {
    kabupaten: 'Kabupaten',
    provinsi: 'Provinsi',
    nasional: 'Nasional',
    internasional: 'Internasional',
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
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

/**
 * Kartu kompetisi untuk halaman index. Tapak Brutal:
 * border-3, shadow 6/6, hover shift -3px, kontainer level + status
 * di atas judul.
 */
export default function CompetitionCard({ competition }: { competition: CompetitionCardData }) {
    return (
        <Link
            href={`/lomba/${competition.slug}`}
            className="group block"
            prefetch
        >
            <article className="relative border-3 border-ink bg-white p-5 shadow-brutal transition-transform duration-150 group-hover:-translate-x-[3px] group-hover:-translate-y-[3px] group-hover:shadow-brutal-lg group-active:translate-x-[2px] group-active:translate-y-[2px] group-active:shadow-brutal-sm">
                <div className="mb-3 flex flex-wrap items-center gap-2">
                    <Badge variant={LEVEL_VARIANT[competition.level]}>
                        {LEVEL_LABEL[competition.level]}
                    </Badge>
                    {competition.is_open ? (
                        <Badge variant="emerald">Pendaftaran Buka</Badge>
                    ) : (
                        <Badge variant="ink">Ditutup</Badge>
                    )}
                </div>

                <h3 className="mb-2 font-display text-xl font-bold leading-tight text-ink">
                    {competition.title}
                </h3>

                <p className="mb-4 font-mono text-xs uppercase text-ink/70">
                    {competition.organizer}
                </p>

                <div className="space-y-1 border-t-2 border-ink/10 pt-3 font-mono text-xs text-ink">
                    <div className="flex justify-between">
                        <span className="opacity-60">Tenggat</span>
                        <span className="font-bold">
                            {formatDeadline(competition.registration_deadline)}
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span className="opacity-60">Biaya</span>
                        <span className="font-bold">
                            {formatRupiah(competition.registration_fee)}
                        </span>
                    </div>
                </div>
            </article>
        </Link>
    );
}
