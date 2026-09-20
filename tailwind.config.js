import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            colors: {
                navy: {
                    DEFAULT: '#061B3A',
                    dark: '#031229',
                },
                card: '#0B2852',
                blue: {
                    DEFAULT: '#123B70',
                },
                gold: {
                    DEFAULT: '#FFD23F',
                    bright: '#FFC107',
                    dark: '#D99A00',
                },
                app: {
                    text: '#DCE7F5',
                    muted: '#8FA8C7',
                },
                success: '#20C997',
                danger: '#FF4D5A',
                brand: {
                    DEFAULT: '#061B3A',
                    dark: '#031229',
                    light: '#123B70',
                },
            },
            fontFamily: {
                sans: ['DM Sans', ...defaultTheme.fontFamily.sans],
                display: ['Syne', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
