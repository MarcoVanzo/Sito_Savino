import { beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Il comportamento che vale la pena bloccare è la deduplica di `Purchase`: la
 * pagina di conferma si ricarica da sola finché il webhook del pagamento non
 * arriva, e senza il blocco lo stesso ordine verrebbe contato una decina di
 * volte. Nessuno se ne accorgerebbe guardando il sito — solo il ritorno delle
 * campagne risulterebbe il triplo di quello vero.
 */
async function freshModule() {
    vi.resetModules();
    document.head.innerHTML = '';
    delete window.fbq;
    delete window._fbq;
    sessionStorage.clear();

    return import('./meta-pixel.js');
}

function calls() {
    return window.fbq?.queue ? [...window.fbq.queue].map((args) => [...args]) : [];
}

function pixelScripts() {
    return [...document.head.querySelectorAll('script')]
        .filter((script) => script.src.includes('connect.facebook.net'));
}

describe('meta pixel', () => {
    beforeEach(() => {
        document.title = 'Pagina di prova';
    });

    it('senza ID non carica niente', async () => {
        const { initMetaPixel, trackPageView } = await freshModule();

        initMetaPixel(null);
        trackPageView();

        expect(window.fbq).toBeUndefined();
        expect(pixelScripts()).toHaveLength(0);
    });

    it('si carica subito quando il consenso non è richiesto', async () => {
        const { initMetaPixel } = await freshModule();

        initMetaPixel('2048882385693445');

        expect(pixelScripts()).toHaveLength(1);
        expect(calls()[0]).toEqual(['init', '2048882385693445']);
        expect(calls()[1]).toEqual(['track', 'PageView']);
    });

    it('con il consenso richiesto aspetta il consenso', async () => {
        const { initMetaPixel, updateMarketingConsent } = await freshModule();

        initMetaPixel('2048882385693445', { needsConsent: true, hasConsent: false });
        expect(pixelScripts()).toHaveLength(0);

        updateMarketingConsent(true);
        expect(pixelScripts()).toHaveLength(1);
    });

    it('invia gli eventi del funnel con valore e valuta', async () => {
        const { initMetaPixel, trackViewContent, trackAddToCart, trackInitiateCheckout } = await freshModule();

        initMetaPixel('2048882385693445');
        trackViewContent({ id: 12, name: 'Maglia gara', value: 79.9 });
        trackAddToCart({ id: 12, name: 'Maglia gara', value: 159.8, quantity: 2 });
        trackInitiateCheckout({ value: 159.8, numItems: 2 });

        const events = calls().filter((call) => call[0] === 'track');

        expect(events[1]).toEqual(['track', 'ViewContent', expect.objectContaining({
            content_ids: ['12'], value: 79.9, currency: 'EUR',
        })]);
        expect(events[2][2].contents).toEqual([{ id: '12', quantity: 2 }]);
        expect(events[3][2]).toEqual(expect.objectContaining({ value: 159.8, num_items: 2 }));
    });

    it('conta un ordine una volta sola anche se la pagina si ricarica', async () => {
        const { initMetaPixel, trackPurchase } = await freshModule();

        initMetaPixel('2048882385693445');

        const ordine = {
            orderNumber: 'SDB-2026-0001',
            value: 159.8,
            items: [{ id: 12, quantity: 2 }],
        };

        trackPurchase(ordine);
        trackPurchase(ordine);
        trackPurchase(ordine);

        const purchases = calls().filter((call) => call[1] === 'Purchase');

        expect(purchases).toHaveLength(1);
        expect(purchases[0][2]).toEqual(expect.objectContaining({
            value: 159.8,
            num_items: 2,
            content_ids: ['12'],
        }));
    });

    it('ordini diversi restano conversioni diverse', async () => {
        const { initMetaPixel, trackPurchase } = await freshModule();

        initMetaPixel('2048882385693445');
        trackPurchase({ orderNumber: 'SDB-2026-0001', value: 10 });
        trackPurchase({ orderNumber: 'SDB-2026-0002', value: 20 });

        expect(calls().filter((call) => call[1] === 'Purchase')).toHaveLength(2);
    });

    it('senza numero d_ordine non inventa una conversione', async () => {
        const { initMetaPixel, trackPurchase } = await freshModule();

        initMetaPixel('2048882385693445');
        trackPurchase({ orderNumber: null, value: 10 });

        expect(calls().filter((call) => call[1] === 'Purchase')).toHaveLength(0);
    });
});
