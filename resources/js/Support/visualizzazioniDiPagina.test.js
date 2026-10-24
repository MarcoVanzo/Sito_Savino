import { beforeEach, describe, expect, it, vi } from 'vitest';
import { contaLeNavigazioni, titoloDellaPaginaNuova } from './visualizzazioniDiPagina.js';

/**
 * Un router finto che si comporta come quello di Inertia 2: `navigate` parte
 * anche alla visita iniziale, prima di ogni navigazione vera.
 */
function routerFinto() {
    const ascolti = [];

    return {
        on(evento, callback) {
            if (evento === 'navigate') ascolti.push(callback);

            return () => ascolti.splice(ascolti.indexOf(callback), 1);
        },
        naviga(props = {}) {
            ascolti.forEach((callback) => callback({ detail: { page: { props } } }));
        },
    };
}

// Il titolo arriva subito: quello del server, o il documento com'e'.
const subito = (dalServer) => Promise.resolve(dalServer ?? document.title);
const unGiro = () => new Promise((risolvi) => setTimeout(risolvi, 0));

async function analyticsNuovo() {
    vi.resetModules();
    document.head.innerHTML = '';
    delete window.dataLayer;
    delete window.gtag;

    return import('../analytics.js');
}

function pageView() {
    return [...window.dataLayer].filter((voce) => voce[0] === 'event' && voce[1] === 'page_view');
}

describe('visualizzazioni di pagina', () => {
    beforeEach(() => {
        document.title = 'Pagina di prova';
    });

    it('non conta la visita iniziale, che il tag ha gia\' mandato', () => {
        const router = routerFinto();
        const invia = vi.fn();

        contaLeNavigazioni(router, invia, subito);
        router.naviga();

        expect(invia).not.toHaveBeenCalled();
    });

    it('conta una visualizzazione per ogni navigazione successiva', async () => {
        const router = routerFinto();
        const invia = vi.fn();

        contaLeNavigazioni(router, invia, subito);
        router.naviga();
        router.naviga();
        router.naviga();
        await unGiro();

        expect(invia).toHaveBeenCalledTimes(2);
    });

    it('con GA4 e il consenso: un page_view all\'ingresso, poi uno per pagina', async () => {
        const { initAnalytics, trackPageView } = await analyticsNuovo();
        const router = routerFinto();

        initAnalytics('G-TEST12345', true);
        contaLeNavigazioni(router, trackPageView, subito);
        router.naviga();

        expect(pageView()).toHaveLength(1);

        window.history.pushState({}, '', '/news');
        router.naviga();
        window.history.pushState({}, '', '/shop');
        router.naviga();
        await unGiro();

        expect(pageView()).toHaveLength(3);
        expect(pageView().slice(1).map((voce) => voce[2].page_path)).toEqual(['/news', '/shop']);
    });

    it('il page_view porta il titolo della pagina nuova, non della precedente', async () => {
        const { initAnalytics, trackPageView } = await analyticsNuovo();
        const router = routerFinto();

        initAnalytics('G-TEST12345', true);
        contaLeNavigazioni(router, trackPageView);
        router.naviga();

        // Titolo dal server.
        router.naviga({ seo: { title: 'Shop Ufficiale | Savino Del Bene Volley' } });
        await unGiro();
        expect(pageView().at(-1)[2].page_title).toBe('Shop Ufficiale | Savino Del Bene Volley');

        // Senza titolo del server: quello che il <Head> scrive dopo la navigazione.
        document.title = 'Shop Ufficiale | Savino Del Bene Volley';
        router.naviga();
        setTimeout(() => { document.title = 'Checkout | Savino Del Bene Volley'; }, 20);
        await new Promise((risolvi) => setTimeout(risolvi, 150));
        expect(pageView().at(-1)[2].page_title).toBe('Checkout | Savino Del Bene Volley');
    });

    it('se il titolo non cambia, dopo l\'attesa massima manda quello che c\'e\'', async () => {
        document.title = 'Stesso titolo';

        await expect(titoloDellaPaginaNuova(null, 'Stesso titolo', 60, 10)).resolves.toBe('Stesso titolo');
    });
});
