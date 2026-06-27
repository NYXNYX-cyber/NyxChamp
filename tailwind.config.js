import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Konfigurasi Tailwind untuk estetika Neo-Brutalisme.
 *
 * Aturan HARUS sinkron dengan AGENTS.md §3.6 (keputusan terkunci).
 * Mengubah salah satu token di sini = melanggar spec; diskusikan dulu.
 */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    theme: {
        extend: {
            // Palette HEX terkunci (AGENTS.md §3.6). Tidak ada warna lain.
            colors: {
                cream: '#F5F0E6',
                ink: '#000000',
                'brutal-pink': '#FF4081',
                'brutal-yellow': '#FFEB3B',
                'brutal-blue': '#0000EE',
                'brutal-emerald': '#4CAF50',
            },

            // Tipografi terkunci (AGENTS.md §3.6).
            // Syne untuk display, Space Grotesk untuk header, JetBrains Mono untuk metadata.
            fontFamily: {
                display: ['"Syne"', 'sans-serif'],
                header: ['"Space Grotesk"', 'sans-serif'],
                mono: ['"JetBrains Mono"', 'monospace'],
                sans: ['"Space Grotesk"', ...defaultTheme.fontFamily.sans],
            },

            // Border default 3px hitam (AGENTS.md §3.6).
            borderWidth: {
                DEFAULT: '3px',
                '0': '0',
                '2': '2px',
                '3': '3px',
                '4': '4px',
                '6': '6px',
                '8': '8px',
            },

            // Shadow keras tanpa blur (AGENTS.md §3.6).
            boxShadow: {
                brutal: '4px 4px 0 0 #000000',
                'brutal-sm': '2px 2px 0 0 #000000',
                'brutal-lg': '6px 6px 0 0 #000000',
                'brutal-xl': '8px 8px 0 0 #000000',
                'brutal-hover': '6px 6px 0 0 #000000',
                'brutal-press': '2px 2px 0 0 #000000',
            },

            borderRadius: {
                none: '0',
                sm: '2px',
                DEFAULT: '4px',
            },
        },
    },

    plugins: [forms],
};
