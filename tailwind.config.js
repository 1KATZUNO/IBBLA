import defaultTheme from 'tailwindcss/defaultTheme';
import colors from 'tailwindcss/colors';
import forms from '@tailwindcss/forms';
import scrollbar from 'tailwind-scrollbar';

// Escala del color de marca: sigue el tema de cada iglesia (Tenant::colors). El layout escribe
// --t-50 ... --t-900 y sus canales RGB (--t-600-rgb) para que funcione la opacidad (bg-brand-600/50).
const brand = Object.fromEntries(
    [50, 100, 200, 300, 400, 500, 600, 700, 800, 900].map((n) => [n, `rgb(var(--t-${n}-rgb) / <alpha-value>)`]),
);

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        // El manual de usuario se escribe en Markdown y puede traer clases de Tailwind en HTML en linea
        './resources/views/manual/**/*.md',
        // La App de Clases (React) esta escrita con clases de Tailwind
        './resources/js/clase-app/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Marca de la iglesia (tema del tenant). Botones, enlaces, foco y estado activo.
                brand,
                // Colores con significado fijo (ver docs/UI.md): nunca decorativos
                success: colors.green,
                warning: colors.amber,
                danger: colors.red,
                info: colors.blue,
                // Compatibilidad
                primary: {
                    50: '#eff6ff',
                    100: '#dbeafe',
                    200: '#bfdbfe',
                    300: '#93c5fd',
                    400: '#60a5fa',
                    500: '#3b82f6',
                    600: '#2563eb',
                    700: '#1d4ed8',
                    800: '#1e40af',
                    900: '#1e3a8a',
                },
            },
            animation: {
                'fade-in': 'fadeIn 0.4s ease-out',
                'fade-in-up': 'fadeInUp 0.4s ease-out',
                'slide-in-right': 'slideInRight 0.3s ease-out',
                'spin-slow': 'spin 1.5s linear infinite',
                'pop-in': 'popIn 0.12s ease-out',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                fadeInUp: {
                    '0%': { opacity: '0', transform: 'translateY(20px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                slideInRight: {
                    '0%': { opacity: '0', transform: 'translateX(-20px)' },
                    '100%': { opacity: '1', transform: 'translateX(0)' },
                },
                popIn: {
                    '0%': { opacity: '0', transform: 'translateY(-4px) scale(0.98)' },
                    '100%': { opacity: '1', transform: 'translateY(0) scale(1)' },
                },
            },
            boxShadow: {
                // Tarjetas: borde fino + sombra casi imperceptible (HubSpot / Salesforce)
                'card': '0 1px 2px rgba(16, 24, 40, 0.05), 0 1px 3px rgba(16, 24, 40, 0.08)',
                'card-hover': '0 4px 12px -2px rgba(16, 24, 40, 0.10), 0 2px 6px -2px rgba(16, 24, 40, 0.06)',
                // Menus desplegables, buscador y popovers
                'popover': '0 12px 32px -8px rgba(16, 24, 40, 0.28), 0 4px 12px -4px rgba(16, 24, 40, 0.12)',
                // Barra superior
                'upbar': '0 1px 0 rgba(0, 0, 0, 0.18), 0 2px 8px -2px rgba(15, 23, 42, 0.25)',
            },
            // Alto de la barra superior fija (lo usan el menu en modo cajon y el buscador movil)
            spacing: {
                'upbar': '3.5rem',
            },
        },
    },

    plugins: [
        forms,
        scrollbar({ nocompatible: true }),
    ],
};
