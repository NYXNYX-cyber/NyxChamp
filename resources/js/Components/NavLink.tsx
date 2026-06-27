import { InertiaLinkProps, Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}: InertiaLinkProps & { active: boolean }) {
    return (
        <Link
            {...props}
            className={
                'inline-flex items-center px-3 py-1.5 font-header font-bold uppercase text-sm tracking-wide border-2 ' +
                (active
                    ? 'border-ink bg-brutal-yellow text-ink shadow-brutal-sm'
                    : 'border-transparent text-ink hover:border-ink hover:bg-cream') +
                ' transition-all no-underline ' +
                className
            }
        >
            {children}
        </Link>
    );
}
