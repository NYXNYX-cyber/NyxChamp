import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-cream pt-6 sm:justify-center sm:pt-0">
            <div>
                <Link href="/">
                    <ApplicationLogo className="h-16 w-auto" />
                </Link>
            </div>

            <div className="mt-6 w-full overflow-hidden border-3 border-ink bg-white shadow-brutal sm:max-w-md">
                <div className="px-6 py-6">{children}</div>
            </div>
        </div>
    );
}
