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
            // I componenti .vue restano fuori dal report, e non è una svista.
            //
            // Ce li abbiamo messi il 22/09/2026 credendo che Sonar li contasse
            // a zero comunque: non è vero. Senza dati nel report lcov Sonar non
            // li misura affatto — nello storico di main non hanno mai avuto un
            // valore di copertura — e mettercelo ha fatto entrare nel calcolo
            // i 105 che un test non ce l'hanno. La copertura del codice nuovo
            // di main è scesa da 85,4% a 79,7%, sotto la soglia, e quella
            // complessiva da 69,7% a 56,4%: senza che una riga di codice fosse
            // cambiata.
            //
            // Includerli è una scelta che si può fare, ma va fatta sapendo che
            // da lì in avanti ogni pagina Vue che si tocca si porta dietro dei
            // test, o il quality gate si ferma. Sono 284 righe scoperte da
            // recuperare: le più pesanti stanno in Ticketing (69), Affiliazioni
            // (44), Convenzioni (40) e ClubRace (35).
            include: ['resources/js/**/*.js'],
            exclude: ['resources/js/**/*.test.js', 'resources/js/bootstrap.js'],
        },
    },
});
