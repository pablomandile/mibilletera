import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import primeui from 'tailwindcss-primeui';

// Los tamaños de letra se multiplican por --font-scale (ver app.css), que cambia
// según la preferencia "Tamaño de letra" de Ajustes. Se escala solo el texto y su
// interlineado, no los espaciados: así una letra más grande hace crecer las filas
// hacia abajo en vez de ensanchar las tarjetas y provocar scroll horizontal.
const scaled = (value) => `calc(${value} * var(--font-scale, 1))`;
const fontSize = Object.fromEntries(
    Object.entries({
        ...defaultTheme.fontSize,
        '2xs': ['0.6875rem', { lineHeight: '1rem' }], // 11px: deltas, fechas de alertas
    }).map(([key, [size, { lineHeight }]]) => [
        key,
        // lineHeight sin unidad (1, 1.25…) ya es relativo a la letra; los rem hay que escalarlos.
        [scaled(size), { lineHeight: /rem$/.test(lineHeight) ? scaled(lineHeight) : lineHeight }],
    ])
);

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'selector',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        fontSize,
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms, primeui],
};
