import js from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';
import pluginVueA11y from 'eslint-plugin-vuejs-accessibility';
import globals from 'globals';
import prettier from 'eslint-config-prettier';

export default [
    {
        ignores: [
            'public/**',
            'vendor/**',
            'node_modules/**',
            'bootstrap/**',
            'storage/**',
        ],
    },
    js.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    ...pluginVueA11y.configs['flat/recommended'],
    prettier,
    {
        files: ['resources/js/**/*.{js,vue}'],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: {
                ...globals.browser,
                // Ziggy espone route() come globale: è iniettata dalla direttiva
                // @routes nel layout Blade, non importata nei moduli.
                route: 'readonly',
            },
        },
        rules: {
            // Il progetto usa molti componenti a parola singola (Home, Gallery,
            // Checkout): è la convenzione delle pagine Inertia, dove il nome del
            // file corrisponde alla rotta.
            'vue/multi-word-component-names': 'off',

            // Una variabile inutilizzata è quasi sempre un residuo di
            // refactoring; il prefisso _ resta la via d'uscita esplicita.
            'no-unused-vars': ['error', {
                argsIgnorePattern: '^_',
                varsIgnorePattern: '^_',
                caughtErrors: 'none',
            }],

            // Errori veri, non stile.
            'no-console': ['warn', { allow: ['warn', 'error'] }],
            'no-debugger': 'error',
            eqeqeq: ['error', 'smart'],

            // Accessibilita' (European Accessibility Act, WCAG 2.1 AA): le
            // regole del plugin sono errori, cosi' la CI si ferma. Un'etichetta
            // puo' contenere il campo o puntarlo con for/id: basta una delle
            // due, non servono entrambe.
            'vuejs-accessibility/label-has-for': ['error', {
                required: { some: ['nesting', 'id'] },
            }],

            // Avvisi e non errori, perche' oggi danno soprattutto falsi
            // positivi: il clic sullo sfondo di una finestra (che si chiude
            // anche con Esc), i <Link> di Inertia presi per elementi non
            // interattivi, l'autofocus del login. Restano visibili nel lint:
            // chi tocca quei file li guarda.
            'vuejs-accessibility/no-static-element-interactions': 'warn',
            'vuejs-accessibility/click-events-have-key-events': 'warn',
            'vuejs-accessibility/aria-unsupported-elements': 'warn',
            'vuejs-accessibility/interactive-supports-focus': 'warn',
            'vuejs-accessibility/mouse-events-have-key-events': 'warn',
            'vuejs-accessibility/no-autofocus': 'warn',
        },
    },
    {
        files: ['resources/js/**/*.test.js'],
        languageOptions: {
            globals: {
                ...globals.browser,
                ...globals.node,
            },
        },
    },
];
