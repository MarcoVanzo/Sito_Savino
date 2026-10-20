import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Ogni evento del funnel arriva a tutti e due i tag, ciascuno con il suo
 * consenso: il pixel con il marketing, GA4 con le statistiche.
 */
async function moduliNuovi() {
    vi.resetModules();
    document.head.innerHTML = '';
    delete window.dataLayer;
    delete window.gtag;
    delete window.fbq;
    delete window._fbq;
    sessionStorage.clear();

    return {
        ga4: await import('./analytics.js'),
        pixel: await import('./meta-pixel.js'),
        conversioni: await import('./conversioni.js'),
    };
}

const eventiGa4 = () => (window.dataLayer ?? []).filter((voce) => voce[0] === 'event').map((voce) => voce[1]);
const eventiPixel = () => (window.fbq?.queue ?? []).filter((voce) => voce[0] === 'track').map((voce) => voce[1]);

const ordine = { orderNumber: 'SDB-2026-0009', value: 50, items: [{ id: 3, name: 'Sciarpa', price: 25, quantity: 2 }] };

describe('conversioni', () => {
    beforeEach(() => {
        document.title = 'Pagina di prova';
    });

    it('con tutti e due i consensi manda il funnel a GA4 e al pixel', async () => {
        const { ga4, pixel, conversioni } = await moduliNuovi();

        ga4.initAnalytics('G-TEST12345', true);
        pixel.initMetaPixel('2048882385693445', { needsConsent: true, hasConsent: true });

        conversioni.prodottoVisto({ id: 3, name: 'Sciarpa', price: 25 });
        conversioni.aggiuntoAlCarrello({ id: 3, name: 'Sciarpa', price: 25, quantity: 2 });
        conversioni.checkoutIniziato({ value: 50, items: ordine.items });
        conversioni.acquistoConcluso(ordine);
        conversioni.acquistoConcluso(ordine);

        expect(eventiGa4()).toEqual(['page_view', 'view_item', 'add_to_cart', 'begin_checkout', 'purchase']);
        expect(eventiPixel()).toEqual(['PageView', 'ViewContent', 'AddToCart', 'InitiateCheckout', 'Purchase']);
    });

    it('il valore del carrello per il pixel e\' prezzo per quantita\'', async () => {
        const { pixel, conversioni } = await moduliNuovi();

        pixel.initMetaPixel('2048882385693445');
        conversioni.aggiuntoAlCarrello({ id: 3, name: 'Sciarpa', price: 25, quantity: 2 });

        const aggiunta = window.fbq.queue.find((voce) => voce[1] === 'AddToCart');
        expect(aggiunta[2]).toEqual(expect.objectContaining({ value: 50 }));
    });

    it('con il solo consenso di statistica non arriva niente al pixel', async () => {
        const { ga4, pixel, conversioni } = await moduliNuovi();

        ga4.initAnalytics('G-TEST12345', true);
        pixel.initMetaPixel('2048882385693445', { needsConsent: true, hasConsent: false });
        conversioni.acquistoConcluso(ordine);

        expect(eventiGa4()).toContain('purchase');
        expect(window.fbq).toBeUndefined();
    });

    it('senza nessun consenso non parte niente', async () => {
        const { ga4, pixel, conversioni } = await moduliNuovi();

        ga4.initAnalytics('G-TEST12345', false);
        pixel.initMetaPixel('2048882385693445', { needsConsent: true, hasConsent: false });
        conversioni.prodottoVisto({ id: 3, name: 'Sciarpa', price: 25 });
        conversioni.acquistoConcluso(ordine);

        expect(eventiGa4()).toEqual([]);
        expect(window.fbq).toBeUndefined();
        expect(document.head.querySelectorAll('script')).toHaveLength(0);
    });
});
