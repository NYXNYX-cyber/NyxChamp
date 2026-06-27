import { HTMLAttributes } from 'react';

type Props = HTMLAttributes<HTMLHeadingElement> & {
    as?: 'h1' | 'h2' | 'h3' | 'h4';
};

/**
 * Heading Neo-Brutalisme. Default pakai font-display (Syne).
 * Untuk sub-heading/card title, set `as="h2"` atau `"h3"`.
 */
export default function Heading({ as = 'h1', className = '', children, ...rest }: Props) {
    const Tag = as;
    const sizeClass: Record<NonNullable<Props['as']>, string> = {
        h1: 'text-5xl md:text-6xl font-display font-extrabold',
        h2: 'text-3xl md:text-4xl font-header font-bold',
        h3: 'text-xl md:text-2xl font-header font-bold',
        h4: 'text-lg font-header font-semibold',
    };
    return (
        <Tag {...rest} className={sizeClass[as] + ' ' + className}>
            {children}
        </Tag>
    );
}
