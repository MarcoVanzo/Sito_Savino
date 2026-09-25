import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            // Fucsia, rosa e rosso sono le tinte della Brand & Digital Style
            // Guide scurite quanto basta per il contrasto delle WCAG 2.1 AA
            // (4,5:1 per il testo normale), che lo shop deve rispettare per
            // l'European Accessibility Act. Le tonalita' ufficiali
            // (#F8269C, #ED028C, #DF338F) davano 3,6-4,2 sia come testo su
            // bianco sia sotto un testo bianco: su 25 pagine axe ne contava
            // piu' di 300. Restano disponibili come `*-brand` per gli usi
            // puramente decorativi (fasce, sfondi senza testo sopra).
            // `savino-fucsia-chiaro` e' la variante per il testo fucsia su
            // fondo blu o grigio scuro, dove il fucsia scurito non basterebbe.
            colors: {
                'savino-blue': '#003063',
                'savino-red': '#C91F7A',
                'savino-fucsia': '#D00778',
                'savino-pink': '#D0027B',
                'savino-fucsia-chiaro': '#FA5FB6',
                'savino-red-brand': '#DF338F',
                'savino-fucsia-brand': '#F8269C',
                'savino-pink-brand': '#ED028C',
            },
            fontFamily: {
                sans: ['Montserrat', ...defaultTheme.fontFamily.sans],
                serif: ['Playfair Display', ...defaultTheme.fontFamily.serif],
            },
        },
    },

    plugins: [forms, typography],
};
