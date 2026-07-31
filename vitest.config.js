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
        coverage: {
            provider: 'v8',
            // lcov per SonarCloud, text per leggere il risultato in locale.
            // Senza questo report tutto il codice JS contava come non coperto
            // nel quality gate: la CI caricava solo la copertura PHP, mentre
            // `sonar.sources` include `resources`.
            reporter: ['text-summary', 'lcov'],
            reportsDirectory: 'coverage-js',
            include: ['resources/js/**/*.js'],
            // I componenti .vue restano fuori: non hanno test propri e
            // includerli darebbe una percentuale che parla di codice che
            // nessuno ha scelto di coprire, invece che dei composable.
            exclude: ['resources/js/**/*.test.js', 'resources/js/bootstrap.js'],
        },
    },
});
