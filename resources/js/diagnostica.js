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
 * Alcuni indirizzi del sito sono credenziali: il token del reset della
 * password, quello dell'ordine (apre indirizzi e articoli), quello del
 * checkout dell'asta, il link di verifica dell'email. La query porta email,
 * firme e il token di PayPal. Sentry li riceverebbe con l'URL della pagina e
 * con i breadcrumb di navigazione e di rete: l'informativa dice di no.
 */
const SEGMENTI_SEGRETI =
    /\/(reset-password|asta|conferma|confirmed|annullato|cancelled|ordine|order|verify-email)\/[^/?#]+(\/[0-9a-f]{20,})?/gi;

export function ripulisciIndirizzo(indirizzo) {
    if (typeof indirizzo !== 'string' || indirizzo === '') {
        return indirizzo;
    }

    return indirizzo
        .replace(/[?#].*$/, '')
        .replace(SEGMENTI_SEGRETI, (_, segmento) => `/${segmento}/[nascosto]`);
}

function ripulisciEvento(evento) {
    // Le props del componente in errore possono contenere dati del cliente
    // (indirizzo, email, righe dell'ordine): `attachProps: false` le spegne
    // alla fonte, questa riga le toglie se un'altra strada le rimette.
    if (evento.contexts?.vue) {
        delete evento.contexts.vue.propsData;
    }

    if (evento.request) {
        evento.request.url = ripulisciIndirizzo(evento.request.url);
        delete evento.request.query_string;
        delete evento.request.cookies;
        if (evento.request.headers) {
            delete evento.request.headers.Referer;
            delete evento.request.headers.referer;
        }
    }

    return evento;
}

function ripulisciBreadcrumb(breadcrumb) {
    if (breadcrumb.data) {
        for (const campo of ['url', 'from', 'to']) {
            if (campo in breadcrumb.data) {
                breadcrumb.data[campo] = ripulisciIndirizzo(breadcrumb.data[campo]);
            }
        }
    }

    return breadcrumb;
}

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
        // @sentry/vue allega di default le props del componente (`vm.$props`)
        // a ogni errore: nel checkout sono indirizzo, email e carrello.
        attachProps: false,
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
        beforeSend: ripulisciEvento,
        beforeSendTransaction: ripulisciEvento,
        beforeBreadcrumb: ripulisciBreadcrumb,
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
