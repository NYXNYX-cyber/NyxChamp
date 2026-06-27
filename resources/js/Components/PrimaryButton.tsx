import { ButtonHTMLAttributes } from 'react';

/** Tombol primer (pink) — lihat AGENTS.md §3.6 micro-interactions. */
export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...props}
            disabled={disabled}
            className={
                'inline-flex items-center justify-center border-3 border-ink bg-brutal-pink text-ink ' +
                'px-4 py-2 font-header font-bold uppercase tracking-wide ' +
                'shadow-brutal transition-all ' +
                'hover:-translate-x-[3px] hover:-translate-y-[3px] hover:shadow-brutal-hover ' +
                'active:translate-x-[2px] active:translate-y-[2px] active:shadow-brutal-press ' +
                (disabled ? 'opacity-50 cursor-not-allowed ' : '') +
                className
            }
        >
            {children}
        </button>
    );
}
