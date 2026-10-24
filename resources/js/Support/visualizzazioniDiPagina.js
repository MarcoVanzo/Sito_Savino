/**
 * Una visualizzazione di pagina a ogni navigazione Inertia, tranne la prima.
 *
 * Inertia manda `navigate` anche al caricamento iniziale
 * (`InitialVisit.handleDefault`), e la pagina d'ingresso la contano gia' GA4 e
 * il pixel quando il tag si carica: fino al 20 ottobre 2026 ogni ingresso
 * finiva nel dataLayer con due `page_view` identici (audit SEO/ADV).
 *
 * Il titolo: quello del server (prop `seo`) quando c'e', altrimenti si
 * aspetta che il `<Head>` della pagina nuova lo cambi. Con il solo rinvio di
 * un tick ogni `page_view` portava il titolo della pagina precedente
 * (DebugView del 21/10/2026).
 *
 * @param {{ on: (evento: string, callback: Function) => Function }} router
 * @param {(titolo?: string, indirizzo?: string) => void} invia
 * @returns {Function} per staccare l'ascolto
 */
export function contaLeNavigazioni(router, invia, aspettaIlTitolo = titoloDellaPaginaNuova) {
    let primaVisita = true;

    return router.on('navigate', (evento) => {
        if (primaVisita) {
            primaVisita = false;

            return;
        }

        // Indirizzo e titolo di partenza si fissano adesso: l'evento parte
        // dopo, e intanto il visitatore puo' essere gia' andato avanti.
        const indirizzo = window.location.href;
        const precedente = document.title;
        const dalServer = evento?.detail?.page?.props?.seo?.title;

        aspettaIlTitolo(typeof dalServer === 'string' && dalServer !== '' ? dalServer : null, precedente)
            .then(
                (titolo) => invia(titolo, indirizzo),
                // Un'attesa andata storta non costa la visualizzazione.
                () => invia(document.title, indirizzo),
            );
    });
}

/**
 * Il titolo della pagina appena aperta: quello del server, oppure il primo
 * `document.title` diverso dal precedente (al massimo un secondo, poi quello
 * che c'e': una pagina con lo stesso titolo della precedente esiste).
 *
 * @returns {Promise<string>}
 */
export function titoloDellaPaginaNuova(dalServer, precedente, attesaMassima = 1000, passo = 50) {
    if (dalServer) return Promise.resolve(dalServer);

    return new Promise((risolvi) => {
        const inizio = Date.now();
        const controlla = () => {
            if (document.title !== precedente || Date.now() - inizio >= attesaMassima) {
                risolvi(document.title);

                return;
            }

            window.setTimeout(controlla, passo);
        };

        window.setTimeout(controlla, 0);
    });
}
