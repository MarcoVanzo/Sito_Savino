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

    it('la revoca cancella i cookie _ga e _ga_<id>, e lascia gli altri', async () => {
        const { initAnalytics, updateAnalyticsConsent } = await freshModule();
        document.cookie = '_ga=GA1.1.123.456; path=/';
        document.cookie = '_ga_TEST12345=GS1.1.789; path=/';
        document.cookie = 'altro=1; path=/';

        initAnalytics('G-TEST12345', true);
        updateAnalyticsConsent(false);

        expect(document.cookie).not.toContain('_ga');
        expect(document.cookie).toContain('altro=1');
        document.cookie = 'altro=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
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

describe('analytics: e-commerce', () => {
    function eventi(nome) {
        return [...window.dataLayer].filter((voce) => voce[0] === 'event' && voce[1] === nome).map((voce) => voce[2]);
    }

    beforeEach(() => {
        sessionStorage.clear();
    });

    it('manda view_item, add_to_cart e begin_checkout nello schema di GA4', async () => {
        const { initAnalytics, trackViewItem, trackAddToCart, trackBeginCheckout } = await freshModule();

        initAnalytics('G-TEST12345', true);
        trackViewItem({ id: 12, name: 'Maglia gara', price: 79.9 });
        trackAddToCart({ id: 12, name: 'Maglia gara', price: 79.9, quantity: 2 });
        trackBeginCheckout({ value: 159.8, items: [{ id: 12, name: 'Maglia gara', price: 79.9, quantity: 2 }] });

        expect(eventi('view_item')[0]).toEqual({
            currency: 'EUR',
            value: 79.9,
            items: [{ item_id: '12', item_name: 'Maglia gara', price: 79.9, quantity: 1 }],
        });
        expect(eventi('add_to_cart')[0]).toEqual(expect.objectContaining({ value: 159.8, currency: 'EUR' }));
        expect(eventi('add_to_cart')[0].items[0].quantity).toBe(2);
        expect(eventi('begin_checkout')[0]).toEqual(expect.objectContaining({ value: 159.8, currency: 'EUR' }));
    });

    it('conta un acquisto una volta sola per ordine, con transaction_id', async () => {
        const { initAnalytics, trackPurchase } = await freshModule();
        const ordine = { orderNumber: 'SDB-2026-0001', value: 159.8, items: [{ id: 12, name: 'Maglia', price: 79.9, quantity: 2 }] };

        initAnalytics('G-TEST12345', true);
        trackPurchase(ordine);
        trackPurchase(ordine);

        expect(eventi('purchase')).toHaveLength(1);
        expect(eventi('purchase')[0]).toEqual(expect.objectContaining({ transaction_id: 'SDB-2026-0001', value: 159.8, currency: 'EUR' }));
    });

    it('senza consenso non manda eventi e non consuma l\'acquisto', async () => {
        const { initAnalytics, updateAnalyticsConsent, trackViewItem, trackPurchase } = await freshModule();
        const ordine = { orderNumber: 'SDB-2026-0002', value: 10 };

        initAnalytics('G-TEST12345', false);
        trackViewItem({ id: 1, name: 'Sciarpa', price: 10 });
        trackPurchase(ordine);

        expect(window.dataLayer.filter((voce) => voce[0] === 'event')).toHaveLength(0);
        expect(sessionStorage.getItem('ga4-purchase-SDB-2026-0002')).toBeNull();

        updateAnalyticsConsent(true);
        trackPurchase(ordine);

        expect(eventi('purchase')).toHaveLength(1);
    });
});

describe('analytics: Consent Mode e Google Ads', () => {
    const aggiornamenti = () => window.dataLayer.filter((voce) => voce[0] === 'consent' && voce[1] === 'update').map((voce) => voce[2]);
    const config = () => window.dataLayer.filter((voce) => voce[0] === 'config').map((voce) => voce[1]);

    beforeEach(() => {
        sessionStorage.clear();
    });

    it('senza ID Ads il marketing aggiorna i tre segnali pubblicitari e non carica Ads', async () => {
        const { initAnalytics, updateAdsConsent } = await freshModule();

        initAnalytics('G-TEST12345', true);
        updateAdsConsent(true);

        expect(aggiornamenti().at(-1)).toEqual({ ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted' });
        expect(config()).toEqual(['G-TEST12345']);

        updateAdsConsent(false);
        expect(aggiornamenti().at(-1)).toEqual({ ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });
    });

    it('con l\'ID Ads si configura solo con il consenso di marketing', async () => {
        const { initAnalytics, updateAdsConsent } = await freshModule();

        initAnalytics('G-TEST12345', true, { adsId: 'AW-123456789', adsPurchaseLabel: 'abcDEF' });
        expect(config()).toEqual(['G-TEST12345']);

        updateAdsConsent(true);
        expect(config()).toEqual(['G-TEST12345', 'AW-123456789']);
        expect(gtagScripts()).toHaveLength(1);
    });

    it('con solo Ads e il marketing, gtag.js si carica con l\'ID Ads e GA4 resta spento', async () => {
        const { initAnalytics } = await freshModule();

        initAnalytics(null, false, { adsId: 'AW-123456789', hasMarketingConsent: true });

        expect(gtagScripts()[0].src).toContain('id=AW-123456789');
        expect(config()).toEqual(['AW-123456789']);
        expect(window.dataLayer.filter((voce) => voce[0] === 'event')).toEqual([]);
    });

    it('la conversione d\'acquisto di Ads parte una volta, solo con il marketing', async () => {
        const { initAnalytics, updateAdsConsent, trackPurchase } = await freshModule();
        const ordine = { orderNumber: 'SDB-2026-0100', value: 40 };
        const conversioni = () => window.dataLayer.filter((voce) => voce[0] === 'event' && voce[1] === 'conversion').map((voce) => voce[2]);

        initAnalytics('G-TEST12345', true, { adsId: 'AW-123456789', adsPurchaseLabel: 'abcDEF' });
        trackPurchase(ordine);
        expect(conversioni()).toEqual([]);

        updateAdsConsent(true);
        trackPurchase(ordine);
        trackPurchase(ordine);

        expect(conversioni()).toEqual([{ send_to: 'AW-123456789/abcDEF', value: 40, currency: 'EUR', transaction_id: 'SDB-2026-0100' }]);
    });

    it('senza etichetta d\'acquisto Ads non manda conversioni', async () => {
        const { initAnalytics, trackPurchase } = await freshModule();

        initAnalytics('G-TEST12345', true, { adsId: 'AW-123456789', hasMarketingConsent: true });
        trackPurchase({ orderNumber: 'SDB-2026-0101', value: 40 });

        expect(window.dataLayer.some((voce) => voce[1] === 'conversion')).toBe(false);
    });

    it('la revoca del marketing cancella i cookie _gcl_ di Ads', async () => {
        const { initAnalytics, updateAdsConsent } = await freshModule();
        document.cookie = '_gcl_au=1.1.123; path=/';

        initAnalytics('G-TEST12345', true, { adsId: 'AW-123456789', hasMarketingConsent: true });
        updateAdsConsent(false);

        expect(document.cookie).not.toContain('_gcl_au');
    });
});
