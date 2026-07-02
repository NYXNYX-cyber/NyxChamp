import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import { Transition } from '@headlessui/react';
import { useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function UpdateNotificationPreferencesForm({ className = '' }: { className?: string }) {
    const user = usePage().props.auth.user;

    const defaults = {
        email_enabled: true,
        web_enabled: true,
        levels: ['kabupaten', 'provinsi', 'nasional', 'internasional'],
    };

    // Safe merge with database preference or default fallback
    const prefs = user.notification_preferences ? {
        email_enabled: user.notification_preferences.email_enabled ?? defaults.email_enabled,
        web_enabled: user.notification_preferences.web_enabled ?? defaults.web_enabled,
        levels: user.notification_preferences.levels ?? defaults.levels,
    } : defaults;

    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm<{
        email_enabled: boolean;
        web_enabled: boolean;
        levels: string[];
    }>({
        email_enabled: prefs.email_enabled,
        web_enabled: prefs.web_enabled,
        levels: prefs.levels,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('notifications.preferences.update'), {
            preserveScroll: true,
        });
    };

    const handleLevelChange = (level: string, checked: boolean) => {
        if (checked) {
            setData('levels', [...data.levels, level]);
        } else {
            setData('levels', data.levels.filter((l: string) => l !== level));
        }
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-black font-header">Pengaturan Notifikasi</h2>
                <p className="mt-1 text-sm text-gray-600 font-mono">
                    Sesuaikan bagaimana Anda ingin menerima notifikasi kompetisi baru dan undangan bimbingan.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                {/* Channels */}
                <div className="space-y-4">
                    <h3 className="text-md font-semibold text-black font-header">Saluran Notifikasi</h3>

                    <div className="flex items-start">
                        <div className="flex items-center h-5">
                            <Checkbox
                                id="web_enabled"
                                name="web_enabled"
                                checked={data.web_enabled}
                                onChange={(e) => setData('web_enabled', e.target.checked)}
                            />
                        </div>
                        <div className="ml-3 text-sm">
                            <label htmlFor="web_enabled" className="font-medium text-black font-header">Notifikasi Web / Real-time</label>
                            <p className="text-gray-500 font-mono text-xs">Terima notifikasi instan di dalam portal saat Anda sedang online.</p>
                            <InputError className="mt-2" message={errors.web_enabled} />
                        </div>
                    </div>

                    <div className="flex items-start">
                        <div className="flex items-center h-5">
                            <Checkbox
                                id="email_enabled"
                                name="email_enabled"
                                checked={data.email_enabled}
                                onChange={(e) => setData('email_enabled', e.target.checked)}
                            />
                        </div>
                        <div className="ml-3 text-sm">
                            <label htmlFor="email_enabled" className="font-medium text-black font-header">Notifikasi Email</label>
                            <p className="text-gray-500 font-mono text-xs">Terima email pemberitahuan ketika Anda sedang offline.</p>
                            <InputError className="mt-2" message={errors.email_enabled} />
                        </div>
                    </div>
                </div>

                {/* Levels */}
                <div className="space-y-4 pt-4 border-t border-black border-dashed">
                    <h3 className="text-md font-semibold text-black font-header">Preferensi Tingkat Kompetisi</h3>
                    <p className="text-xs text-gray-500 font-mono">Pilih tingkat kompetisi yang ingin Anda ikuti notifikasinya (berlaku untuk Email & Web):</p>

                    <div className="grid grid-cols-2 gap-4">
                        {['kabupaten', 'provinsi', 'nasional', 'internasional'].map((level) => (
                            <div key={level} className="flex items-center">
                                <Checkbox
                                    id={`level-${level}`}
                                    checked={data.levels.includes(level)}
                                    onChange={(e) => handleLevelChange(level, e.target.checked)}
                                />
                                <label htmlFor={`level-${level}`} className="ml-2 text-sm text-black font-header capitalize">
                                    Tingkat {level}
                                </label>
                            </div>
                        ))}
                    </div>
                    <InputError className="mt-2" message={errors.levels} />
                </div>

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>Simpan Preferensi</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-green-600 font-mono">Berhasil disimpan.</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
