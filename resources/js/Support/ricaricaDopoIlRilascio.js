/**
 * Dopo un rilascio i pezzi del build precedente non esistono piu': chi aveva
 * gia' la pagina aperta, al primo componente caricato su richiesta (il banner
 * dei cookie, una pagina Inertia) riceve un 404 e Vite lancia
 * "Failed to fetch dynamically imported module". Si ricarica la pagina, che
 * prende il manifest nuovo.
 *
 * Una sola ricarica ogni RIPROVA_DOPO_MS: se il pezzo manca davvero (un build
 * rotto, non uno vecchio) si lascia passare l'errore invece di ricaricare in
 * loop.
 *
 * Assorbito l'errore, Vite risolve l'import a `undefined` e chi lo aspettava
 * (Inertia: `module.default`) lancia un TypeError prima che la ricarica parta:
 * `ricaricaInCorso()` dice a Sentry di lasciarlo perdere.
 *
 * Se il pezzo mancante era la pagina di una visita Inertia in corso, si apre
 * direttamente quella: ricaricare l'indirizzo attuale riporterebbe chi ha
 * cliccato sulla pagina di partenza.
 */
export const RIPROVA_DOPO_MS = 10_000;
const CHIAVE = 'ricarica-dopo-il-rilascio';
let inCorso = false;

export function ricaricaInCorso() {
    return inCorso;
}

/**
 * @param {Window} finestra
 * @param {() => string|null} destinazione indirizzo della visita in corso
 */
export function ricaricaDopoIlRilascio(finestra = window, destinazione = () => null) {
    finestra.addEventListener('vite:preloadError', (evento) => {
        // Senza sessionStorage non c'e' modo di ricordare la ricarica gia'
        // fatta: meglio l'errore di un loop.
        try {
            const ultima = Number(finestra.sessionStorage.getItem(CHIAVE)) || 0;
            if (Date.now() - ultima < RIPROVA_DOPO_MS) return;
            finestra.sessionStorage.setItem(CHIAVE, String(Date.now()));
        } catch {
            return;
        }

        inCorso = true;
        evento.preventDefault();
        const indirizzo = destinazione();
        if (indirizzo) {
            finestra.location.assign(indirizzo);
        } else {
            finestra.location.reload();
        }
    });
}
