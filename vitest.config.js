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
            // I .vue sono qui dentro dal 22/09/2026, da quando il banner dei
            // cookie ha dei test propri. Tenerli fuori non li faceva contare
            // come coperti: li lasciava senza dati, e Sonar — che analizza
            // tutto `resources` — li conta comunque a zero. Fuori dal report
            // un componente coperto non si distingue da uno che non lo è.
            include: ['resources/js/**/*.js', 'resources/js/**/*.vue'],
            exclude: ['resources/js/**/*.test.js', 'resources/js/bootstrap.js'],
        },
    },
});
