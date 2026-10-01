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
 */
export const RIPROVA_DOPO_MS = 10_000;
const CHIAVE = 'ricarica-dopo-il-rilascio';

/**
 * @param {Window} finestra
 */
export function ricaricaDopoIlRilascio(finestra = window) {
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

        evento.preventDefault();
        finestra.location.reload();
    });
}
