import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { leggiTutto, opzioniGlobali, pagina } from '@/testing/paginaDiProva.js';

/**
 * I percorsi critici dello shop letti da uno screen reader virtuale
 * (@guidepup/virtual-screen-reader): percorre l'albero di accessibilita' del
 * DOM come la lettura continua di VoiceOver o NVDA e registra cosa direbbe.
 *
 * Non sostituisce la prova con uno screen reader vero (protocollo in
 * docs/ACCESSIBILITA.md), ma blocca le regressioni che quella prova ha
 * trovato: icone lette come "immagine", la taglia scelta indistinguibile dalle
 * altre, errori non legati al campo, voci del menu con la descrizione
 * appiccicata al nome, righe del carrello con i pulsanti senza il prodotto.
 */

vi.mock('@inertiajs/vue3', async () => (await import('@/testing/paginaDiProva.js')).inertiaFinto());
vi.mock('@/meta-pixel.js', () => ({ trackInitiateCheckout: () => {}, trackViewContent: () => {}, trackAddToCart: () => {} }));

import MegaMenu from '@/Components/MegaMenu.vue';
import ProductDetail from './ProductDetail.vue';
import Cart from './Cart.vue';
import Checkout from './Checkout.vue';
import Recesso from './Recesso.vue';

async function leggi(componente, props, prima) {
    const involucro = mount(componente, { props, global: opzioniGlobali(), attachTo: document.body });
    await flushPromises();
    if (prima) {
        await prima(involucro);
        await flushPromises();
    }

    return { involucro, frasi: await leggiTutto() };
}

/** La posizione della prima frase che contiene `testo`, per l'ordine di lettura. */
const indice = (frasi, testo) => frasi.findIndex((f) => f.includes(testo));

beforeEach(() => {
    pagina.url = '/';
    pagina.props = { locale: 'it', auth: { user: null }, flash: {}, siteSettings: {}, navigation: [] };
    globalThis.fetch = async () => ({ ok: true, json: async () => ({ count: 0, items: [] }) });
});

afterEach(() => {
    document.body.innerHTML = '';
});

describe('menu principale', () => {
    const navigazione = [
        { id: 1, label: 'Società', href: '/societa', children: [{ id: 2, label: 'Storia', href: '/societa/storia', description: 'Dal 2004 a oggi' }] },
        { id: 3, label: 'News', href: '/news', children: [] },
    ];

    it('e\' una navigazione con nome, e il sottomenu dice solo le sue voci', async () => {
        const { frasi } = await leggi(MegaMenu, { navigation: navigazione, fontSize: 15 });

        expect(frasi).toContain('navigation, Navigazione principale');
        expect(frasi).toContain('link, Società, not expanded, has popup menu');
        expect(frasi.some((f) => f.startsWith('menuitem, Storia, Dal 2004 a oggi'))).toBe(true);
        // Il motto e la foto a destra sono decorazione, il separatore pure.
        expect(frasi.join('\n')).not.toMatch(/Eccellenza|graphics-document|^\|$/m);
    });
});

describe('scheda prodotto', () => {
    const prodotto = {
        id: 5, name: 'Maglia gara', price: 59, stock: 7, description: '<p>Maglia ufficiale</p>', images: [],
        variants: [{ id: 1, size: 'S', stock: 3 }, { id: 2, size: 'M', stock: 4 }, { id: 3, size: 'L', stock: 0 }],
    };

    it('la scelta della taglia e\' un gruppo con il suo nome e dice quale e\' scelta', async () => {
        const { frasi } = await leggi(ProductDetail, { product: prodotto }, async (p) => {
            await p.findAll('[data-scelta-taglia] button')[1].trigger('click');
        });

        expect(frasi).toContain('group, Seleziona variante');
        expect(frasi).toContain('button, S, not pressed');
        expect(frasi).toContain('button, M, pressed');
        expect(frasi).toContain('button, L (Esaurito), disabled, not pressed');
        expect(frasi).toContain('group, Quantità');
        expect(frasi).toContain('navigation, Percorso');
        expect(frasi).not.toContain('/');
    });

    it('aggiungendo senza taglia l\'errore e\' un avviso legato al gruppo, e resta', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
        const { involucro, frasi } = await leggi(ProductDetail, { product: prodotto }, async (p) => {
            await p.find('[data-aggiungi-al-carrello]').trigger('click');
        });

        expect(frasi).toContain('group, Seleziona variante, Seleziona una variante prima di aggiungere al carrello');
        expect(frasi).toContain('alert');
        // Spariva dopo tre secondi: ora resta finche' non si sceglie.
        vi.advanceTimersByTime(10000);
        await flushPromises();
        expect(involucro.find('#errore-taglia').exists()).toBe(true);
        vi.useRealTimers();
    });
});

describe('carrello', () => {
    it('i pulsanti della riga dicono di quale prodotto, e l\'intestazione visiva non si legge', async () => {
        const { frasi } = await leggi(Cart, {
            cart: { items: [{ id: 1, name: 'Maglia gara', variant: 'M', quantity: 2, price: 59, stock: 5 }] },
            total: 118,
            itemCount: 2,
            freeShippingThreshold: 100,
        });

        expect(frasi).toContain('button, Diminuisci quantità: Maglia gara');
        expect(frasi).toContain('button, Aumenta quantità: Maglia gara');
        expect(frasi).toContain('button, Rimuovi dal carrello: Maglia gara');
        // "Prodotto Prezzo Quantità Totale" prima delle righe non diceva nulla.
        expect(indice(frasi, 'Prodotto')).toBe(-1);
        expect(frasi).toContain('Quantità:');
    });
});

describe('checkout con errori', () => {
    const props = {
        cart: { items: [{ id: 1, product_name: 'Maglia gara', quantity: 1, unit_price: 59, total: 59 }] },
        cartTotal: 59,
        shippingZones: [{ id: 1, countries: ['IT'], flat_rate: 7.9 }],
        paymentGateways: [{ value: 'bank_transfer', label: 'Bonifico bancario' }],
    };

    it('l\'avviso viene prima dei campi, e ogni campo dice il suo errore', async () => {
        const { frasi } = await leggi(Checkout, props, async (c) => {
            await c.find('[data-passaggio-successivo]').trigger('click');
        });

        const avviso = indice(frasi, 'Compila tutti i campi obbligatori');
        expect(frasi[avviso - 2]).toBe('alert');
        expect(avviso).toBeLessThan(indice(frasi, 'textbox, Nome e Cognome'));
        expect(frasi).toContain('heading, Passo 1 di 2: Dati Spedizione, level 2');
        expect(frasi.some((f) => /^textbox, Telefono \*, Campo obbligatorio, invalid, .*required$/.test(f))).toBe(true);
        expect(frasi.some((f) => /^combobox, Via \/ Indirizzo \*, Campo obbligatorio, .*invalid, required$/.test(f))).toBe(true);
        // Icone e numeri dei passi non si leggono piu' come contenuto.
        expect(frasi).not.toContain('graphics-document');
        expect(frasi).not.toContain('1');
    });
});

describe('recesso online', () => {
    it('i campi vuoti dicono l\'errore, e l\'aiuto resta legato al campo facoltativo', async () => {
        const { frasi } = await leggi(Recesso, {}, async (r) => {
            await r.find('form').trigger('submit');
        });

        expect(frasi).toContain('textbox, Nome e cognome *, Campo obbligatorio., invalid, required');
        expect(frasi).toContain('textbox, Email *, Campo obbligatorio., invalid, required');
        expect(frasi).toContain('textbox, Articoli da restituire (facoltativo), Lascia vuoto se recedi dall\'intero ordine., not invalid');
        expect(indice(frasi, 'button, Continua')).toBeGreaterThan(indice(frasi, 'Articoli da restituire'));
    });
});
