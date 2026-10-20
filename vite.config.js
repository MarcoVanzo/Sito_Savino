import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/css/filament/admin/theme.css'
            ],
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
