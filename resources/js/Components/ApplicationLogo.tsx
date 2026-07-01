import { SVGAttributes } from 'react';

/**
 * Logo NyxChamp — wordmark Neo-Brutalisme.
 *
 * Layout (viewBox 240x60):
 *   - "NYX" kuning di atas
 *   - Pink "C" sebagai huruf pertama "CHAMP" — pink C yang
 *     beneran kelihatan seperti huruf (pakai path C-shaped),
 *     bukan square
 *   - "HAMP" cream melanjutkan wordmark
 *
 * Pakai di navbar / hero / footer.
 */
export default function ApplicationLogo(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 240 60"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="NyxChamp"
        >
            {/* Background black box */}
            <rect
                x="2"
                y="2"
                width="236"
                height="56"
                fill="#000000"
                stroke="#000000"
                strokeWidth="4"
            />
            {/* "NYX" — baris atas, kuning, baseline di y=26 */}
            <text
                x="14"
                y="26"
                fill="#FFEB3B"
                fontFamily="Syne, sans-serif"
                fontWeight="800"
                fontSize="20"
                letterSpacing="1"
            >
                NYX
            </text>
            {/* "C" pink — huruf pertama CHAMP, pakai path C-shape
                (bukan rectangle) supaya kelihatan seperti huruf.
                Stroke kuning tipis untuk kontras dengan background. */}
            <path
                d="M 24 40
                   L 24 38
                   A 6 6 0 1 0 24 50
                   L 24 48"
                fill="none"
                stroke="#FF4081"
                strokeWidth="6"
                strokeLinecap="square"
            />
            {/* "HAMP" cream — sisa kata CHAMP, baseline di y=52 */}
            <text
                x="36"
                y="52"
                fill="#F5F0E6"
                fontFamily="Syne, sans-serif"
                fontWeight="800"
                fontSize="20"
                letterSpacing="1"
            >
                HAMP
            </text>
        </svg>
    );
}
