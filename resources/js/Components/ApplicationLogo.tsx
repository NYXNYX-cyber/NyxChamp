import { SVGAttributes } from 'react';

/**
 * Logo NyxChamp — wordmark Neo-Brutalisme.
 *
 * Layout (viewBox 240x60):
 *   - "NYX" kuning di atas
 *   - "C" pink di bawah (render oleh font Syne 800, dijamin bentuknya
 *     benar sebagai huruf C — bukan path manual)
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
            {/* "NYX" — baris atas, kuning, baseline y=26 */}
            <text
                x="14"
                y="26"
                fill="#FFEB3B"
                fontFamily="Syne, sans-serif"
                fontWeight="800"
                fontSize="22"
                letterSpacing="1"
            >
                NYX
            </text>
            {/* "C" pink — huruf pertama CHAMP, di-render oleh font
                (bukan path manual) supaya bentuknya dijamin proper */}
            <text
                x="14"
                y="52"
                fill="#FF4081"
                fontFamily="Syne, sans-serif"
                fontWeight="800"
                fontSize="22"
                letterSpacing="1"
            >
                C
            </text>
            {/* "HAMP" cream — sisa kata CHAMP, baseline y=52 (sejajar C) */}
            <text
                x="34"
                y="52"
                fill="#F5F0E6"
                fontFamily="Syne, sans-serif"
                fontWeight="800"
                fontSize="22"
                letterSpacing="1"
            >
                HAMP
            </text>
        </svg>
    );
}
