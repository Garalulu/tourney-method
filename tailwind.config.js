import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['var(--font-body)', ...defaultTheme.fontFamily.sans],
                display: ['var(--font-display)', ...defaultTheme.fontFamily.sans],
                tournament: ['var(--font-tournament)', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                slate: {
                    50: '#f8fafc',
                    100: '#f1f5f9',
                    200: '#e2e8f0',
                    300: '#cbd5e1',
                    400: '#94a3b8',
                    500: '#64748b',
                    600: '#475569',
                    700: '#334155',
                    800: '#1e293b',
                    900: '#0f172a',
                    950: '#020617',
                },
                'osu-pink': {
                    DEFAULT: '#ff66aa',
                    50: '#fff1f7',
                    100: '#ffe4f0',
                    200: '#ffcce3',
                    300: '#ffa0ca',
                    400: '#ff66aa',
                    500: '#ff3389',
                    600: '#ff0066',
                    700: '#dd0055',
                    800: '#b80048',
                    900: '#990040',
                },
                'osu-cyan': {
                    DEFAULT: '#00ddff',
                    50: '#e6faff',
                    100: '#ccf5ff',
                    200: '#99ebff',
                    300: '#66e0ff',
                    400: '#33d6ff',
                    500: '#00ddff',
                    600: '#00b8dd',
                    700: '#0093bb',
                    800: '#006e99',
                    900: '#004977',
                },
                'dark': {
                    DEFAULT: '#0a0e14',
                    50: '#f5f6f7',
                    100: '#e1e3e6',
                    200: '#c3c7cd',
                    300: '#9da3ad',
                    400: '#787f8d',
                    500: '#5f6573',
                    600: '#4a4f5a',
                    700: '#3d424a',
                    800: '#25282e',
                    850: '#151922',
                    900: '#0a0e14',
                },
            },
            backgroundImage: {
                'grid-pattern': 'linear-gradient(to right, rgba(255, 255, 255, 0.03) 1px, transparent 1px), linear-gradient(to bottom, rgba(255, 255, 255, 0.03) 1px, transparent 1px)',
                'rhythm-gradient': 'linear-gradient(135deg, rgba(255, 102, 170, 0.1) 0%, rgba(0, 221, 255, 0.1) 100%)',
            },
            backgroundSize: {
                'grid': '24px 24px',
            },
            animation: {
                'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                'slide-in': 'slideIn 0.3s ease-out',
                'fade-in': 'fadeIn 0.4s ease-out',
                'scale-in': 'scaleIn 0.2s ease-out',
            },
            keyframes: {
                slideIn: {
                    '0%': { transform: 'translateY(-10px)', opacity: '0' },
                    '100%': { transform: 'translateY(0)', opacity: '1' },
                },
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                scaleIn: {
                    '0%': { transform: 'scale(0.95)', opacity: '0' },
                    '100%': { transform: 'scale(1)', opacity: '1' },
                },
            },
        },
    },
    plugins: [],
};
