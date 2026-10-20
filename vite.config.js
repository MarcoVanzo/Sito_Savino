import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    // `VUE_HYDRATION_DETAILS=1 npm run build`: i mismatch di idratazione fra
    // l'HTML dell'SSR e il browser compaiono in console anche nella build di
    // produzione (per le verifiche, non per il rilascio).
    define: {
        __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: process.env.VUE_HYDRATION_DETAILS === '1',
    },
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/css/filament/admin/theme.css'
            ],
            // Il bundle del server SSR (bootstrap/ssr), con `vite build --ssr`.
            ssr: 'resources/js/ssr.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    // Nel bundle SSR entra tutto tranne jsdom, che non si impacchetta (carica
    // file a runtime) e resta in node_modules: per questo e' fra le
    // `dependencies`, che il buildpack non toglie dopo la build.
    ssr: {
        noExternal: true,
        external: ['jsdom'],
    },
    build: {
        rollupOptions: {
            output: {
                // Le librerie comuni in un chunk stabile fra un rilascio e
                // l'altro (resta in cache). Sentry no: lo carica
                // `diagnostica.js` solo quando serve, e dentro `vendor` lo
                // scaricava ogni pagina (37 KB compressi, audit del 20/10/2026).
                manualChunks(id) {
                    if (id.includes('node_modules') && !id.includes('@sentry')) {
                        return 'vendor';
                    }
                },
            },
        },
    },
});
