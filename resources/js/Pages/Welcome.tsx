import ApplicationLogo from '@/Components/ApplicationLogo';
import Card from '@/Components/Brutal/Card';
import Badge from '@/Components/Brutal/Badge';
import BrutalLink from '@/Components/Brutal/Link';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';

const FEATURES = [
    {
        title: 'Agregator Mingguan',
        body: 'Data lomba dari 6 portal Indonesia dikumpulkan otomatis tiap minggu. Tidak ada lagi info tenggat yang terlewat.',
        tone: 'pink' as const,
    },
    {
        title: 'Filter Tingkat & Tenggat',
        body: 'Cari lomba kabupaten, provinsi, nasional, atau internasional. Filter tenggat biar fokus ke yang masih buka.',
        tone: 'yellow' as const,
    },
    {
        title: 'Grup Bimbingan Real-time',
        body: 'Guru bikin grup bimbingan privat untuk diskusi materi & koordinasi pendaftaran. Chat real-time via Reverb.',
        tone: 'emerald' as const,
    },
];

export default function Welcome({ auth }: PageProps) {
    return (
        <>
            <Head title="NyxChamp — Agregator Kompetisi Pelajar Indonesia" />

            <div className="min-h-screen bg-cream">
                {/* Nav */}
                <nav className="border-b-3 border-ink bg-white">
                    <div className="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
                        <Link href="/">
                            <ApplicationLogo className="h-12 w-auto" />
                        </Link>
                        <div className="flex items-center gap-3">
                            {auth.user ? (
                                <BrutalLink
                                    href={route('dashboard')}
                                    variant="ink"
                                >
                                    Dasbor
                                </BrutalLink>
                            ) : (
                                <>
                                    <BrutalLink
                                        href={route('login')}
                                        variant="yellow"
                                    >
                                        Masuk
                                    </BrutalLink>
                                    <BrutalLink
                                        href={route('register')}
                                        variant="pink"
                                    >
                                        Daftar
                                    </BrutalLink>
                                </>
                            )}
                        </div>
                    </div>
                </nav>

                {/* Hero */}
                <section className="border-b-3 border-ink bg-cream">
                    <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
                        <div className="grid items-center gap-10 lg:grid-cols-2">
                            <div>
                                <Badge variant="yellow" className="mb-4">
                                    Fase 0 — Fondasi
                                </Badge>
                                <h1 className="font-display text-5xl font-extrabold leading-[0.95] text-ink sm:text-6xl lg:text-7xl">
                                    Cari lomba.
                                    <br />
                                    Siap daftar.
                                    <br />
                                    <span className="bg-brutal-pink px-2">
                                        Bareng tim.
                                    </span>
                                </h1>
                                <p className="mt-6 max-w-xl font-mono text-base text-ink/80 sm:text-lg">
                                    NyxChamp agregasi info kompetisi pelajar
                                    dari 6 portal Indonesia, lalu kasih
                                    tempat buat guru & siswa koordinir
                                    pendaftaran via chat real-time.
                                </p>
                                <div className="mt-8 flex flex-wrap gap-4">
                                    <BrutalLink
                                        href={route('competitions.index')}
                                        variant="pink"
                                    >
                                        Jelajahi Lomba
                                    </BrutalLink>
                                    {auth.user ? (
                                        <BrutalLink
                                            href={route('dashboard')}
                                            variant="ink"
                                        >
                                            Dasbor Saya
                                        </BrutalLink>
                                    ) : (
                                        <>
                                            <BrutalLink
                                                href={route('register')}
                                                variant="ink"
                                            >
                                                Daftar Gratis
                                            </BrutalLink>
                                            <BrutalLink
                                                href={route('login')}
                                                variant="yellow"
                                            >
                                                Masuk
                                            </BrutalLink>
                                        </>
                                    )}
                                </div>
                            </div>

                            <div className="relative">
                                <Card tone="white">
                                    <div className="space-y-3 font-mono">
                                        <div className="flex items-baseline gap-2">
                                            <span className="text-xs text-ink/60">
                                                sumber
                                            </span>
                                            <Badge variant="default">
                                                lombahub.com
                                            </Badge>
                                        </div>
                                        <h3 className="font-header text-2xl font-bold text-ink">
                                            Lomba Cipta Puisi Nasional 2026
                                        </h3>
                                        <p className="text-sm text-ink/80">
                                            Lomba menulis puisi tingkat
                                            nasional untuk pelajar SMA &
                                            mahasiswa. Pendaftaran hingga
                                            15 Agustus 2026.
                                        </p>
                                        <div className="flex flex-wrap gap-2 pt-2">
                                            <Badge variant="yellow">
                                                Nasional
                                            </Badge>
                                            <Badge variant="emerald">
                                                Masih Buka
                                            </Badge>
                                            <Badge variant="default">
                                                Rp 50.000
                                            </Badge>
                                        </div>
                                        <div className="border-t-2 border-ink pt-3 text-xs text-ink/60">
                                            Tenggat: 2026-08-15
                                        </div>
                                    </div>
                                </Card>
                                <div className="absolute -right-3 -top-3">
                                    <Badge variant="pink" className="text-sm">
                                        PREVIEW
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Features */}
                <section className="border-b-3 border-ink bg-white">
                    <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
                        <h2 className="font-display text-4xl font-extrabold text-ink sm:text-5xl">
                            Kenapa NyxChamp?
                        </h2>
                        <p className="mt-3 max-w-2xl font-mono text-ink/80">
                            Tujuh portal kompetisi Indonesia, satu dasbor.
                            Plus ruang kolaborasi guru–siswa.
                        </p>
                        <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            {FEATURES.map((f) => (
                                <Card key={f.title} tone={f.tone} hoverable>
                                    <h3 className="font-header text-xl font-bold text-ink">
                                        {f.title}
                                    </h3>
                                    <p className="mt-3 text-sm text-ink/80">
                                        {f.body}
                                    </p>
                                </Card>
                            ))}
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="bg-ink">
                    <div className="mx-auto max-w-7xl px-4 py-8 text-cream sm:px-6 lg:px-8">
                        <div className="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                            <div className="font-mono text-sm">
                                © 2026 NyxChamp — agregator kompetisi
                                Indonesia.
                            </div>
                            <div className="font-mono text-xs text-cream/60">
                                Laravel · Reverb · Inertia · React · MySQL ·
                                Python FastAPI
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
