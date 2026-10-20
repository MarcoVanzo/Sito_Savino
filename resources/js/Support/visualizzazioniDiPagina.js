/**
 * Una visualizzazione di pagina a ogni navigazione Inertia, tranne la prima.
 *
 * Inertia manda `navigate` anche al caricamento iniziale
 * (`InitialVisit.handleDefault`), e la pagina d'ingresso la contano gia' GA4 e
 * il pixel quando il tag si carica: fino al 20 ottobre 2026 ogni ingresso
 * finiva nel dataLayer con due `page_view` identici (audit SEO/ADV).
 *
 * Il rinvio di un tick serve a leggere il titolo gia' aggiornato.
 *
 * @param {{ on: (evento: string, callback: Function) => Function }} router
 * @param {() => void} invia
 * @returns {Function} per staccare l'ascolto
 */
export function contaLeNavigazioni(router, invia, rinvia = (callback) => window.setTimeout(callback, 0)) {
    let primaVisita = true;

    return router.on('navigate', () => {
        if (primaVisita) {
            primaVisita = false;

            return;
        }

        rinvia(invia);
    });
}
