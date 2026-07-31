import js from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';
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
