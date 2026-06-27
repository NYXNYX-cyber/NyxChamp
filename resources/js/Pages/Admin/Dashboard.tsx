import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { PageProps } from '@/types';

export default function AdminDashboard({ auth }: PageProps) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Admin Dashboard
                </h2>
            }
        >
            <Head title="Admin" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            Halo <strong>{auth.user.name}</strong> — Anda masuk
                            sebagai administrator. (Smoke test RBAC — halaman
                            ini hanya bisa diakses oleh role <code>admin</code>.)
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
