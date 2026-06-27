import { HTMLAttributes } from 'react';

type Variant = 'default' | 'yellow' | 'pink' | 'emerald' | 'ink';

const VARIANT_CLASS: Record<Variant, string> = {
    default: 'bg-white text-ink',
    yellow: 'bg-brutal-yellow text-ink',
    pink: 'bg-brutal-pink text-ink',
    emerald: 'bg-brutal-emerald text-white',
    ink: 'bg-ink text-cream',
};

type Props = HTMLAttributes<HTMLSpanElement> & {
    variant?: Variant;
};

/**
 * Badge kecil untuk label metadata: tingkat kompetisi, status, kategori.
 * Pakai JetBrains Mono (font mono) sesuai AGENTS.md §3.6.
 */
export default function Badge({ variant = 'default', className = '', children, ...rest }: Props) {
    return (
        <span
            {...rest}
            className={
                'inline-flex items-center border-2 border-ink ' +
                VARIANT_CLASS[variant] +
                ' px-2 py-0.5 font-mono text-xs font-bold uppercase ' +
                className
            }
        >
            {children}
        </span>
    );
}
