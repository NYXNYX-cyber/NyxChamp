import { SVGAttributes } from 'react';

/**
 * Logo NyxChamp — wordmark Neo-Brutalisme.
 *
 * Layout (viewBox 240x64):
 *   - "NYX" kuning di atas
 *   - "HAMP" cream di bawah, dengan "C" diganti kotak pink sebagai brand mark
 *   - Pink square jadi "C" huruf pertama, sekaligus accent color
 *
 * Pakai di navbar / hero / footer.
 */
export default function ApplicationLogo(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 240 64"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="NyxChamp"
        >
            {/* Background black box */}
            <rect
                x="2"
                y="2"
                width="236"
                height="60"
                fill="#000000"
                stroke="#000000"
                strokeWidth="4"
            />
            {/* "NYX" — baris atas, kuning */}
            <text
                x="14"
                y="30"
                fill="#FFEB3B"
                fontFamily="Syne, sans-serif"
                fontWeight="800"
                fontSize="20"
                letterSpacing="1"
            >
                NYX
            </text>
            {/* "C" pink — jadi huruf pertama CHAMP */}
            <rect
                x="14"
                y="38"
                width="18"
                height="20"
                fill="#FF4081"
                stroke="#F5F0E6"
                strokeWidth="2"
            />
            {/* "HAMP" cream — sisa kata CHAMP, geser kanan karena "C" jadi pink square */}
            <text
                x="38"
                y="54"
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
