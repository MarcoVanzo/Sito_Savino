/**
 * Un componente caricato su richiesta (il menu del telefono, il banner dei
 * cookie) che non arriva per la rete veniva dato per perso al primo errore.
 * Su iPhone con la rete che va e viene (biglietteria, palazzetto) Safari dice
 * "Importing a module script failed": la ricarica di ricaricaDopoIlRilascio.js
 * scatta, ma la pagina nuova trova la stessa rete e l'errore arrivava a Sentry
 * (SITO-SAVINO-BROWSER-5: 26 eventi, 12 visitatori, a raffica dallo stesso
 * telefono anche dopo la ricarica e senza rilasci di mezzo).
 *
 * Prima di arrendersi l'import si riprova TENTATIVI volte, con un'attesa che
 * cresce: un pezzo di build davvero mancante fallisce lo stesso, e l'errore
 * resta visibile.
 */
export const TENTATIVI = 3;
export const ATTESA_MS = 1000;

/**
 * Le opzioni per `defineAsyncComponent`.
 *
 * @param {() => Promise<unknown>} caricatore
 * @param {(fn: () => void, ms: number) => unknown} [attendi]
 */
export function componenteConRiprova(caricatore, attendi = setTimeout) {
    return {
        loader: caricatore,
        onError(errore, riprova, arrenditi, tentativi) {
            if (tentativi > TENTATIVI) {
                arrenditi();
                return;
            }

            attendi(riprova, ATTESA_MS * tentativi);
        },
    };
}
