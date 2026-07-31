import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        // jsdom e non l'ambiente node: DOMPurify sanifica costruendo un
        // documento vero, e senza DOM restituirebbe l'input intatto — i test
        // sulla protezione XSS passerebbero senza provare nulla.
        environment: 'jsdom',
        include: ['resources/js/**/*.test.js'],
        globals: true,
    },
});
