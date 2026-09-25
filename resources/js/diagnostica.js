/**
 * Errori JavaScript del sito verso Sentry.
 *
 * Il server è già sorvegliato, ma il sito è una SPA: un errore in un
 * componente Vue — il pulsante "Paga" che non risponde, una pagina bianca —
 * lascia il server a rispondere 200 e nessun controllo se ne accorge.
 *
 * Tre scelte, che sono anche ciò che l'informativa promette:
 *
 * - **Passa dal nostro server** (`tunnel`, SentryTunnelController): il browser
 *   non apre connessioni verso Sentry, quindi Sentry non riceve l'indirizzo IP
 *   del visitatore. Per lo stesso motivo non serve toccare la CSP.
 * - **Niente dati personali, niente sessioni**: `sendDefaultPii` spento e
 *   senza l'integrazione BrowserSession, che manderebbe un segnale a ogni
 *   visita. Non scrive niente nel browser (né cookie né storage), per questo
 *   non dipende dal consenso sui cookie.
 * - **Solo i nostri script** (`allowUrls`): gli errori di estensioni del
 *   browser, di GA4 o del Pixel non sono guasti del sito, e ogni issue nuova
 *   diventa un'email in `allarmi@`.
 *
 * Si carica dopo il resto (import dinamico): pesa qualche decina di KB che la
 * prima pagina non deve aspettare. Gli errori del primissimo disegno si
 * perdono; quelli che contano — un'azione che fallisce — arrivano dopo.
 */

export const TUNNEL = '/api/diagnostica';

/**
 * Filtri condivisi con i test.
 */
export function opzioniDiSentry({ app, dsn, environment, origine }) {
    return {
        app,
        dsn,
        environment,
        tunnel: TUNNEL,
        sendDefaultPii: false,
        allowUrls: [origine],
        ignoreErrors: [
            // Rumore noto dei browser, non guasti: il ridimensionamento che
            // non fa in tempo a notificare, e le richieste interrotte da chi
            // cambia pagina mentre una chiamata è in volo.
            'ResizeObserver loop',
            'Non-Error promise rejection captured',
            /^AbortError/,
        ],
        integrations: (predefinite) => predefinite.filter((i) => i.name !== 'BrowserSession'),
    };
}

/**
 * @param {import('vue').App} app
 * @param {{dsn?: string|null, environment?: string|null}|undefined} configurazione
 */
export function avviaLaDiagnostica(app, configurazione) {
    if (!configurazione?.dsn || typeof window === 'undefined') {
        return Promise.resolve(false);
    }

    return (
        import('@sentry/vue')
            .then((Sentry) => {
                Sentry.init(
                    opzioniDiSentry({
                        app,
                        dsn: configurazione.dsn,
                        environment: configurazione.environment ?? undefined,
                        origine: window.location.origin,
                    }),
                );

                return true;
            })
            // Un blocco pubblicitario che ferma il pezzo di codice non deve
            // diventare un errore del sito.
            .catch(() => false)
    );
}
