import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Role = 'student' | 'teacher' | 'admin';

const ROLE_LABELS: Record<Role, string> = {
    student: 'Siswa',
    teacher: 'Guru Pembimbing',
    admin: 'Administrator',
};

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}: {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
            institution: user.institution ?? '',
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        patch(route('profile.update'));
    };

    const role: Role = (user.role ?? 'student') as Role;

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    Informasi Profil
                </h2>
                <p className="mt-1 text-sm text-gray-600">
                    Perbarui nama, email, dan institusi (sekolah / kampus) Anda.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel htmlFor="name" value="Nama Lengkap" />
                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        isFocused
                        autoComplete="name"
                    />
                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Email" />
                    <TextInput
                        id="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />
                    <InputError className="mt-2" message={errors.email} />
                </div>

                <div>
                    <InputLabel htmlFor="institution" value="Institusi (Sekolah / Kampus)" />
                    <TextInput
                        id="institution"
                        className="mt-1 block w-full"
                        value={data.institution}
                        onChange={(e) => setData('institution', e.target.value)}
                        placeholder="Contoh: SMA Negeri 1 Bandung"
                        maxLength={255}
                    />
                    <p className="mt-1 text-xs text-gray-500">
                        Opsional. Bantu guru & siswa satu sekolah saling menemukan grup bimbingan.
                    </p>
                    <InputError className="mt-2" message={errors.institution} />
                </div>

                <div>
                    <InputLabel value="Peran" />
                    <div className="mt-1 inline-flex items-center gap-2 rounded-none border-2 border-black bg-yellow-100 px-3 py-1 text-sm font-bold uppercase">
                        {ROLE_LABELS[role] ?? role}
                    </div>
                    <p className="mt-1 text-xs text-gray-500">
                        Peran hanya bisa diubah oleh administrator. Hubungi admin jika perlu perubahan.
                    </p>
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-gray-800">
                            Email Anda belum diverifikasi.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="ml-1 rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                Kirim ulang tautan verifikasi.
                            </Link>
                        </p>
                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-green-600">
                                Tautan verifikasi baru sudah dikirim.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>Simpan</PrimaryButton>
                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-gray-600">Tersimpan.</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
