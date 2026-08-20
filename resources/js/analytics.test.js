import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Il banner dei cookie chiede un consenso che deve avere un effetto reale.
 * Questi test fissano il punto: senza consenso non parte nessuna richiesta a
 * Google, e il tag si carica una volta sola quando il consenso arriva.
 *
 * Il modulo tiene stato interno (id, script già caricato), quindi si reimporta
 * a ogni test: senza, il secondo test erediterebbe il tag caricato dal primo.
 */
async function freshModule() {
    vi.resetModules();
    document.head.innerHTML = '';
    delete window.dataLayer;
    delete window.gtag;

    return import('./analytics.js');
}

function gtagScripts() {
    return [...document.head.querySelectorAll('script')]
        .filter((script) => script.src.includes('googletagmanager.com'));
}

describe('analytics', () => {
    beforeEach(() => {
        document.title = 'Pagina di prova';
    });

    it('senza Measurement ID non fa assolutamente nulla', async () => {
        const { initAnalytics, updateAnalyticsConsent } = await freshModule();

        initAnalytics(null, true);
        updateAnalyticsConsent(true);

        expect(window.dataLayer).toBeUndefined();
        expect(gtagScripts()).toHaveLength(0);
    });

    it('senza consenso dichiara il rifiuto e non carica il tag', async () => {
        const { initAnalytics } = await freshModule();

        initAnalytics('G-TEST12345', false);

        // Il default negato va dichiarato comunque: è ciò che rende corretto il
        // comportamento di gtag.js se un domani si attivassero i segnali anonimi.
        const consent = [...window.dataLayer].find((entry) => entry[0] === 'consent');
        expect(consent[1]).toBe('default');
        expect(consent[2].analytics_storage).toBe('denied');

        expect(gtagScripts()).toHaveLength(0);
    });

    it('carica il tag quando arriva il consenso, una volta sola', async () => {
        const { initAnalytics, updateAnalyticsConsent } = await freshModule();

        initAnalytics('G-TEST12345', false);
        updateAnalyticsConsent(true);
        updateAnalyticsConsent(true);

        expect(gtagScripts()).toHaveLength(1);

        const updates = [...window.dataLayer].filter((entry) => entry[0] === 'consent' && entry[1] === 'update');
        expect(updates[0][2].analytics_storage).toBe('granted');
    });

    it('con il consenso già dato parte subito', async () => {
        const { initAnalytics } = await freshModule();

        initAnalytics('G-TEST12345', true);

        expect(gtagScripts()).toHaveLength(1);
    });

    it('la revoca dichiara il rifiuto senza rimuovere il tag già caricato', async () => {
        const { initAnalytics, updateAnalyticsConsent } = await freshModule();

        initAnalytics('G-TEST12345', true);
        updateAnalyticsConsent(false);

        const updates = [...window.dataLayer].filter((entry) => entry[0] === 'consent' && entry[1] === 'update');
        expect(updates.at(-1)[2].analytics_storage).toBe('denied');
    });

    it('dopo la revoca non manda piu visualizzazioni', async () => {
        const { initAnalytics, updateAnalyticsConsent, trackPageView } = await freshModule();

        initAnalytics('G-TEST12345', true);
        const prima = [...window.dataLayer].filter((entry) => entry[0] === 'event' && entry[1] === 'page_view').length;

        updateAnalyticsConsent(false);
        trackPageView();

        const dopo = [...window.dataLayer].filter((entry) => entry[0] === 'event' && entry[1] === 'page_view').length;
        expect(dopo).toBe(prima);
    });

    it('invia una visualizzazione per ogni navigazione, con il percorso corrente', async () => {
        const { initAnalytics, trackPageView } = await freshModule();

        initAnalytics('G-TEST12345', true);
        window.history.pushState({}, '', '/squadra/roster');
        document.title = 'Roster';
        trackPageView();

        const views = [...window.dataLayer].filter((entry) => entry[0] === 'event' && entry[1] === 'page_view');

        // Una al caricamento del tag e una alla navigazione: senza la seconda,
        // in una SPA tutto il traffico risulterebbe sulla pagina d'ingresso.
        expect(views).toHaveLength(2);
        expect(views.at(-1)[2].page_path).toBe('/squadra/roster');
        expect(views.at(-1)[2].page_title).toBe('Roster');
    });

    it('non invia visualizzazioni finché il tag non è caricato', async () => {
        const { initAnalytics, trackPageView } = await freshModule();

        initAnalytics('G-TEST12345', false);
        trackPageView();

        const views = [...window.dataLayer].filter((entry) => entry[0] === 'event');
        expect(views).toHaveLength(0);
    });
});
