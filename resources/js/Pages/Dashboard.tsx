import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Card from '@/Components/Brutal/Card';
import Badge from '@/Components/Brutal/Badge';
import Heading from '@/Components/Brutal/Heading';
import { Head } from '@inertiajs/react';
import { PageProps } from '@/types';

const ROLE_GREETING: Record<string, string> = {
    student: 'Mulai cari lomba & ikuti event dari sekolahmu.',
    teacher: 'Buat grup bimbingan untuk siswa-siswamu.',
    admin: 'Kelola agregator & moderasi kompetisi.',
};

export default function Dashboard({ auth }: PageProps) {
    const user = auth.user;
    const role = user.role ?? 'student';
    const greeting = ROLE_GREETING[role] ?? '';

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="font-display text-3xl font-extrabold text-ink">
                        Halo, {user.name}!
                    </h1>
                    <p className="mt-1 font-mono text-sm text-ink/70">
                        Anda masuk sebagai <Badge variant="yellow">{role}</Badge>
                    </p>
                </div>
            }
        >
            <Head title="Dasbor" />

            <div className="py-10">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <p className="font-mono text-ink/80">{greeting}</p>

                    <div className="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <Card tone="white" hoverable>
                            <Badge variant="default">Placeholder</Badge>
                            <Heading as="h3" className="mt-3">
                                Kompetisi Dibuka
                            </Heading>
                            <p className="mt-2 font-mono text-sm text-ink/70">
                                Modul kompetisi read-only menyusul di Fase 4.
                            </p>
                        </Card>
                        <Card tone="yellow" hoverable>
                            <Badge variant="default">Placeholder</Badge>
                            <Heading as="h3" className="mt-3">
                                Grup Bimbingan
                            </Heading>
                            <p className="mt-2 font-mono text-sm text-ink/80">
                                Modul grup bimbingan (Fase 11) +
                                Reverb (Fase 8).
                            </p>
                        </Card>
                        <Card tone="pink" hoverable>
                            <Badge variant="default">Segera</Badge>
                            <Heading as="h3" className="mt-3">
                                Scraping Mingguan
                            </Heading>
                            <p className="mt-2 font-mono text-sm text-ink/80">
                                Cron mingguan (Fase 7) dari 6 portal target.
                            </p>
                        </Card>
                    </div>

                    <div className="mt-10 border-3 border-ink bg-cream p-5 shadow-brutal">
                        <Heading as="h3" className="text-2xl">
                            Status Implementasi
                        </Heading>
                        <ul className="mt-3 space-y-1 font-mono text-sm">
                            <li>
                                <Badge variant="emerald">✓</Badge> Fase 0 —
                                Fondasi Laravel + Inertia + Reverb
                            </li>
                            <li>
                                <Badge variant="emerald">✓</Badge> Fase 1 —
                                Skema DB 3NF (5 tabel)
                            </li>
                            <li>
                                <Badge variant="emerald">✓</Badge> Fase 2 —
                                Auth + RBAC (role middleware)
                            </li>
                            <li>
                                <Badge variant="emerald">✓</Badge> Fase 3 —
                                UI Neo-Brutalisme (tokens + komponen)
                            </li>
                            <li>
                                <Badge variant="default">·</Badge> Fase 4 —
                                Modul Kompetisi (read-only)
                            </li>
                            <li>
                                <Badge variant="default">·</Badge> Fase 5-12 —
                                lihat roadmap di AGENTS.md
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
