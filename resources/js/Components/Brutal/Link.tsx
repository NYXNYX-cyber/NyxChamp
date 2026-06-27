import { Link, InertiaLinkProps } from '@inertiajs/react';
import { ComponentProps } from 'react';

type Variant = 'pink' | 'yellow' | 'ink' | 'blue';

const VARIANT_CLASS: Record<Variant, string> = {
    pink: 'bg-brutal-pink text-ink',
    yellow: 'bg-brutal-yellow text-ink',
    ink: 'bg-ink text-cream',
    blue: 'bg-white text-brutal-blue border-brutal-blue',
};

type Props = InertiaLinkProps &
    Omit<ComponentProps<'a'>, keyof InertiaLinkProps> & {
        variant?: Variant;
    };

/**
 * Link Neo-Brutalisme — sama micro-interaction seperti Button, tapi
 * render sebagai <a> via Inertia. Untuk navigasi internal.
 */
export default function BrutalLink({
    variant = 'pink',
    className = '',
    children,
    ...rest
}: Props) {
    return (
        <Link
            {...rest}
            className={
                'inline-flex items-center justify-center border-3 border-ink ' +
                VARIANT_CLASS[variant] +
                ' px-4 py-2 font-header font-bold uppercase tracking-wide ' +
                'shadow-brutal transition-all no-underline ' +
                'hover:-translate-x-[3px] hover:-translate-y-[3px] hover:shadow-brutal-hover ' +
                'active:translate-x-[2px] active:translate-y-[2px] active:shadow-brutal-press ' +
                className
            }
        >
            {children}
        </Link>
    );
}
