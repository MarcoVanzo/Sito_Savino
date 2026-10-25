/**
 * Una visita Inertia la cui richiesta cade per la rete (telefono che passa da
 * Wi-Fi a dati, galleria, schermo spento a meta' caricamento) finiva nel
 * nulla: Inertia rifiuta la promessa con `HttpNetworkError`, il clic resta
 * senza effetto e a Sentry arriva un errore che non e' un guasto del sito
 * (SITO-SAVINO-BROWSER-N, 25/10/2026: due telefoni, pagine che rispondevano).
 *
 * Per una visita GET si apre l'indirizzo con una navigazione normale: se la
 * rete e' tornata la pagina arriva, se no il browser mostra la sua pagina di
 * assenza di rete, che almeno dice cosa succede. Non c'e' rischio di loop: la
 * navigazione normale non passa piu' da Inertia.
 *
 * Gli errori dei precaricamenti (`prefetch` al passaggio sul menu) e delle
 * visite non GET restano come sono: aprire un indirizzo che nessuno ha
 * cliccato, o rifare un POST, sarebbe peggio dell'errore.
 *
 * @param {Document} documento dove Inertia lancia `inertia:networkError`
 * @param {Window} finestra
 * @param {() => string|null} destinazione indirizzo della visita GET in corso
 */
export function apriSenzaInertiaSeCadeLaRete(documento = document, finestra = window, destinazione = () => null) {
    documento.addEventListener('inertia:networkError', (evento) => {
        const indirizzo = destinazione();
        const fallito = evento.detail?.error?.url;

        if (!indirizzo || !fallito || stessoIndirizzo(fallito, indirizzo, finestra) === false) {
            return;
        }

        evento.preventDefault();
        finestra.location.assign(indirizzo);
    });
}

function stessoIndirizzo(a, b, finestra) {
    try {
        const base = finestra.location.href;

        return new URL(a, base).href === new URL(b, base).href;
    } catch {
        return false;
    }
}
