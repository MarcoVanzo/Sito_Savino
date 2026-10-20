import { beforeEach, describe, expect, it, vi } from 'vitest';
import { contaLeNavigazioni } from './visualizzazioniDiPagina.js';

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
        naviga() {
            ascolti.forEach((callback) => callback({ detail: { page: {} } }));
        },
    };
}

const subito = (callback) => callback();

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

    it('conta una visualizzazione per ogni navigazione successiva', () => {
        const router = routerFinto();
        const invia = vi.fn();

        contaLeNavigazioni(router, invia, subito);
        router.naviga();
        router.naviga();
        router.naviga();

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

        expect(pageView()).toHaveLength(3);
        expect(pageView().slice(1).map((voce) => voce[2].page_path)).toEqual(['/news', '/shop']);
    });
});
