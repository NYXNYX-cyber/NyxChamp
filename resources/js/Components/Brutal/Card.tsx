import { HTMLAttributes } from 'react';

type Props = HTMLAttributes<HTMLDivElement> & {
    tone?: 'white' | 'cream' | 'yellow' | 'pink' | 'emerald';
    hoverable?: boolean;
};

const TONE_CLASS: Record<NonNullable<Props['tone']>, string> = {
    white: 'bg-white',
    cream: 'bg-cream',
    yellow: 'bg-brutal-yellow',
    pink: 'bg-brutal-pink',
    emerald: 'bg-brutal-emerald text-white',
};

/**
 * Card Neo-Brutalisme: border-3 hitam, shadow hard offset, tanpa blur.
 * `hoverable` true → interaksi hover geser 3px (micro-interaction tombol,
 * lihat AGENTS.md §3.6).
 */
export default function Card({
    tone = 'white',
    hoverable = false,
    className = '',
    children,
    ...rest
}: Props) {
    return (
        <div
            {...rest}
            className={
                'border-3 border-ink ' +
                TONE_CLASS[tone] +
                ' shadow-brutal p-5 ' +
                (hoverable
                    ? 'transition-all hover:-translate-x-[3px] hover:-translate-y-[3px] hover:shadow-brutal-hover '
                    : '') +
                className
            }
        >
            {children}
        </div>
    );
}
