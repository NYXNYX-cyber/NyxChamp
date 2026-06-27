import { ButtonHTMLAttributes, forwardRef } from 'react';

type Variant = 'pink' | 'yellow' | 'emerald' | 'ink';

const VARIANT_CLASS: Record<Variant, string> = {
    pink: 'bg-brutal-pink',
    yellow: 'bg-brutal-yellow',
    emerald: 'bg-brutal-emerald text-white',
    ink: 'bg-ink text-cream',
};

/**
 * Tombol Neo-Brutalisme. Selalu:
 * - border-3 hitam
 * - shadow hard offset (no blur)
 * - hover shift 3px ke kiri-atas
 * - active shift 2px ke kanan-bawah (efek saklar fisik)
 *
 * Lihat AGENTS.md §3.6 (micro-interactions) + §3.6 tabel (warna).
 */
type Props = ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: Variant;
};

const Button = forwardRef<HTMLButtonElement, Props>(function Button(
    { variant = 'pink', className = '', children, ...rest },
    ref,
) {
    return (
        <button
            ref={ref}
            {...rest}
            className={
                'inline-flex items-center justify-center border-3 border-ink ' +
                VARIANT_CLASS[variant] +
                ' px-4 py-2 font-header font-bold uppercase tracking-wide ' +
                'shadow-brutal transition-all ' +
                'hover:-translate-x-[3px] hover:-translate-y-[3px] hover:shadow-brutal-hover ' +
                'active:translate-x-[2px] active:translate-y-[2px] active:shadow-brutal-press ' +
                'disabled:opacity-50 disabled:cursor-not-allowed ' +
                'disabled:hover:translate-x-0 disabled:hover:translate-y-0 disabled:hover:shadow-brutal ' +
                className
            }
        >
            {children}
        </button>
    );
});

export default Button;
