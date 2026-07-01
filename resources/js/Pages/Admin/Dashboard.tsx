import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Badge from '@/Components/Brutal/Badge';
import Button from '@/Components/Brutal/Button';
import Card from '@/Components/Brutal/Card';
import Heading from '@/Components/Brutal/Heading';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { PageProps } from '@/types';

type Competition = {
    id: number;
    title: string;
    source_url: string;
    registration_deadline: string;
    level: 'kabupaten' | 'provinsi' | 'nasional' | 'internasional';
};

type Stats = {
    total: number;
    open: number;
    closed: number;
    by_level: Record<string, number>;
    latest: Competition[];
};

type FlashStatus = { status?: string };
type FlashHealth = { health?: { ok: boolean; message: string } };
type FlashErrors = { trigger?: string };
type Props = PageProps & { stats: Stats; errors?: FlashErrors };

const LEVEL_VARIANT: Record<Competition['level'], 'emerald' | 'pink' | 'yellow' | 'ink'> = {
    kabupaten: 'emerald',
    provinsi: 'yellow',
    nasional: 'pink',
    internasional: 'ink',
};

const LEVEL_LABELS: Record<Competition['level'], string> = {
    kabupaten: 'Kabupaten',
    provinsi: 'Provinsi',
    nasional: 'Nasional',
    internasional: 'Internasional',
};

export default function AdminDashboard({ auth, stats }: Props) {
    const { props } = usePage<Props & { flash?: FlashStatus & FlashHealth }>();
    const flashStatus = props.flash?.status;
    const flashHealth = props.flash?.health;
    const triggerError = props.errors?.trigger;

    const [triggering, setTriggering] = useState(false);
    const [healthChecking, setHealthChecking] = useState(false);

    const handleTrigger = () => {
        if (triggering) return;
        if (
            !confirm(
                'Jalankan scraping sekarang? Akan dispatch 6 job ke queue (1 per portal). Cooldown 5 menit antar trigger.',
            )
        ) {
            return;
        }
        setTriggering(true);
        router.post(
            route('admin.scrape.trigger'),
            { max_pages: 5 },
            {
                preserveScroll: true,
                onFinish: () => setTriggering(false),
            },
        );
    };

    const handleHealth = () => {
        if (healthChecking) return;
        setHealthChecking(true);
        router.post(
            route('admin.scrape.health'),
            {},
            {
                preserveScroll: true,
                onFinish: () => setHealthChecking(false),
            },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <Heading as="h2">Dasbor Admin</Heading>
                    <Badge variant="ink">{auth.user.role?.toUpperCase()}</Badge>
                </div>
            }
        >
            <Head title="Admin — NyxChamp" />

            <div className="space-y-6">
                {/* Welcome + role badge */}
                <Card>
                    <p className="font-mono text-sm">
                        Halo <strong>{auth.user.name}</strong> — Anda masuk sebagai administrator.
                    </p>
                    <p className="mt-1 font-mono text-xs text-ink/60">
                        Halaman ini hanya untuk role <code>admin</code>. Gunakan kontrol di bawah untuk
                        trigger scraping manual.
                    </p>
                </Card>

                {/* Stats ringkas */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <StatBox label="Total" value={stats.total} variant="ink" />
                    <StatBox label="Pendaftaran Buka" value={stats.open} variant="emerald" />
                    <StatBox label="Sudah Tutup" value={stats.closed} variant="pink" />
                    <StatBox
                        label="Nasional+"
                        value={
                            (stats.by_level['nasional'] ?? 0) +
                            (stats.by_level['internasional'] ?? 0)
                        }
                        variant="yellow"
                    />
                </div>

                {/* Manual trigger + health check */}
                <Card title="Scraper — Trigger Manual">
                    <p className="mb-3 font-mono text-xs text-ink/70">
                        Jadwal otomatis: Senin 05:00 + Jumat 15:00 WIB. Gunakan ini untuk emergency
                        (info lomba urgent mid-week, recovery dari gagal auto, dsb).
                    </p>

                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            type="button"
                            variant="pink"
                            disabled={triggering}
                            onClick={handleTrigger}
                            data-test="trigger-scrape"
                        >
                            {triggering ? '⏳ Dispatching…' : '▶ Jalankan Scraping Sekarang'}
                        </Button>
                        <Button
                            type="button"
                            variant="yellow"
                            disabled={healthChecking}
                            onClick={handleHealth}
                            data-test="check-health"
                        >
                            {healthChecking ? '⏳ Cek…' : '🔍 Cek Status Scraper'}
                        </Button>
                    </div>

                    {flashStatus && (
                        <div className="mt-3 border-2 border-ink bg-brutal-emerald/20 p-2 font-mono text-xs">
                            ✓ {flashStatus}
                        </div>
                    )}

                    {flashHealth && (
                        <div
                            className={
                                'mt-3 border-2 border-ink p-2 font-mono text-xs ' +
                                (flashHealth.ok ? 'bg-brutal-emerald/20' : 'bg-brutal-pink/20')
                            }
                        >
                            {flashHealth.ok ? '✓' : '✗'} {flashHealth.message}
                        </div>
                    )}

                    {triggerError && (
                        <div className="mt-3 border-2 border-ink bg-brutal-pink/20 p-2 font-mono text-xs">
                            ✗ {triggerError}
                        </div>
                    )}

                    <p className="mt-3 font-mono text-xs text-ink/60">
                        ℹ Cooldown 5 menit antar trigger. Cek progress di{' '}
                        <code>storage/logs/laravel.log</code> atau{' '}
                        <code>php artisan queue:failed</code> kalau ada job gagal.
                    </p>
                </Card>

                {/* Latest competitions */}
                <Card title="Kompetisi Terbaru (5 terakhir)">
                    {stats.latest.length === 0 ? (
                        <p className="font-mono text-xs text-ink/60">Belum ada kompetisi.</p>
                    ) : (
                        <ul className="space-y-2">
                            {stats.latest.map((c) => (
                                <li
                                    key={c.id}
                                    className="flex items-center justify-between gap-3 border-b-2 border-ink/10 pb-2 font-mono text-xs last:border-b-0"
                                >
                                    <a
                                        href={c.source_url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="truncate text-brutal-blue underline"
                                    >
                                        {c.title}
                                    </a>
                                    <div className="flex shrink-0 items-center gap-2">
                                        <Badge variant={LEVEL_VARIANT[c.level]}>
                                            {LEVEL_LABELS[c.level]}
                                        </Badge>
                                        <span className="text-ink/50">{c.registration_deadline}</span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

function StatBox({
    label,
    value,
    variant,
}: {
    label: string;
    value: number;
    variant: 'emerald' | 'pink' | 'yellow' | 'ink';
}) {
    return (
        <div
            className={
                'border-3 border-ink p-3 shadow-brutal-sm ' +
                (variant === 'emerald'
                    ? 'bg-brutal-emerald/20'
                    : variant === 'pink'
                    ? 'bg-brutal-pink/20'
                    : variant === 'yellow'
                    ? 'bg-brutal-yellow/30'
                    : 'bg-white')
            }
        >
            <p className="font-header text-3xl font-extrabold leading-none">{value}</p>
            <p className="mt-1 font-mono text-xs uppercase tracking-wide text-ink/60">{label}</p>
        </div>
    );
}
