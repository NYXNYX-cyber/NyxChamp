import { SVGAttributes } from 'react';

/**
 * Logo NyxChamp — kotak hitam pekat dengan teks display "NYX" di atas
 * "CHAMP", gaya Neo-Brutalisme. Pakai di navbar / hero / footer.
 */
export default function ApplicationLogo(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 220 60"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="NyxChamp"
        >
            <rect
                x="2"
                y="2"
                width="216"
                height="56"
                fill="#000000"
                stroke="#000000"
                strokeWidth="4"
            />
            <text
                x="14"
                y="30"
                fill="#FFEB3B"
                fontFamily="Syne, sans-serif"
                fontWeight="800"
                fontSize="22"
                letterSpacing="0.5"
            >
                NYX
            </text>
            <text
                x="14"
                y="50"
                fill="#F5F0E6"
                fontFamily="Syne, sans-serif"
                fontWeight="800"
                fontSize="22"
                letterSpacing="0.5"
            >
                CHAMP
            </text>
            <rect
                x="148"
                y="14"
                width="56"
                height="32"
                fill="#FF4081"
                stroke="#F5F0E6"
                strokeWidth="3"
            />
        </svg>
    );
}
