import { InertiaLinkProps, Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}: InertiaLinkProps & { active?: boolean }) {
    return (
        <Link
            {...props}
            className={
                'flex w-full items-start border-l-4 py-2 pe-4 ps-3 ' +
                (active
                    ? 'border-ink bg-brutal-yellow text-ink font-bold'
                    : 'border-transparent text-ink hover:border-ink hover:bg-cream') +
                ' text-base font-medium transition duration-150 ease-in-out focus:outline-none no-underline ' +
                className
            }
        >
            {children}
        </Link>
    );
}
