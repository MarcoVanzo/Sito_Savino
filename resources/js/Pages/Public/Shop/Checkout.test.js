import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { invii, opzioniGlobali, pagina, violazioniAxe } from '@/testing/paginaDiProva.js';

vi.mock('@inertiajs/vue3', async () => (await import('@/testing/paginaDiProva.js')).inertiaFinto());
vi.mock('@/meta-pixel.js', () => ({ trackInitiateCheckout: () => {} }));

import Checkout from './Checkout.vue';

const carrello = {
    items: [{ id: 1, product_name: 'Maglia gara', variant: 'M', quantity: 1, unit_price: 59, total: 59 }],
    total: 59,
};

const zone = [{ id: 1, name: 'Italia', countries: ['IT'], flat_rate: 7.9, weight_rates: [], free_threshold: 100 }];
const gateway = [{ value: 'bank_transfer', label: 'Bonifico bancario' }];

function monta() {
    return mount(Checkout, {
        props: { cart: carrello, cartTotal: 59, cartWeight: 0.3, itemCount: 1, shippingZones: zone, paymentGateways: gateway },
        global: opzioniGlobali(),
        attachTo: document.body,
    });
}

async function compilaIlPasso1(pagina) {
    const valori = {
        '#checkout-guest-name': 'Maria Rossi',
        '#checkout-email': 'maria@example.test',
        '#checkout-phone': '055 000 0000',
        '#checkout-first-name': 'Maria',
        '#checkout-last-name': 'Rossi',
        '#checkout-street': 'Via Gozzoli 5',
        '#checkout-city': 'Scandicci',
        '#checkout-zip': '50018',
        '#checkout-province': 'FI',
        '#checkout-cf': 'RSSMRA80A41H501U',
    };

    for (const [selettore, valore] of Object.entries(valori)) {
        await pagina.find(selettore).setValue(valore);
    }

    await pagina.find('[data-passaggio-successivo]').trigger('click');
    await flushPromises();
}

const pulsanteOrdine = (pagina) => pagina.findAll('button').find((b) => b.text().includes('obbligo di pagamento'));

beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    invii.length = 0;
    pagina.props = { locale: 'it', auth: { user: null }, flash: {} };
});

afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = '';
});

/**
 * Il pulsante che chiude l'ordine era spento finche' mancavano metodo di
 * pagamento e accettazione delle condizioni, e non diceva perche': con la
 * tastiera o uno screen reader si arrivava su un pulsante muto. Ora resta
 * attivo, come nel checkout delle aste: al clic ogni mancanza riceve il suo
 * errore legato al campo e il focus va alla prima (WCAG 3.3.1, 4.1.2).
 */
describe('Checkout dello shop: il pulsante che chiude l\'ordine', () => {
    it('non e\' disattivato quando mancano pagamento e condizioni', async () => {
        const pagina = monta();
        await compilaIlPasso1(pagina);

        expect(pulsanteOrdine(pagina).attributes('disabled')).toBeUndefined();
    });

    it('al clic dice cosa manca, sotto ciascun campo, e non invia', async () => {
        const pagina = monta();
        await compilaIlPasso1(pagina);

        await pulsanteOrdine(pagina).trigger('click');
        await flushPromises();

        expect(invii).toHaveLength(0);
        expect(pagina.find('#errore-payment_gateway').text()).toBe('Scegli un metodo di pagamento');
        expect(pagina.find('#errore-privacy_accepted').text()).toBe('Per ordinare devi accettare le condizioni di vendita');

        const radio = pagina.find('input[name="payment_gateway"]');
        expect(radio.attributes('aria-invalid')).toBe('true');
        expect(radio.attributes('aria-describedby')).toBe('errore-payment_gateway');

        const casella = pagina.find('input[type="checkbox"][aria-required="true"]');
        expect(casella.attributes('aria-invalid')).toBe('true');
        expect(casella.attributes('aria-describedby')).toBe('errore-privacy_accepted');
    });

    it('porta il focus sul primo campo che manca', async () => {
        const pagina = monta();
        await compilaIlPasso1(pagina);

        await pulsanteOrdine(pagina).trigger('click');
        await flushPromises();
        vi.advanceTimersByTime(100);

        expect(document.activeElement).toBe(pagina.find('input[name="payment_gateway"]').element);
    });

    it('con metodo e condizioni l\'ordine parte', async () => {
        const pagina = monta();
        await compilaIlPasso1(pagina);

        await pagina.find('input[name="payment_gateway"]').setValue(true);
        await pagina.find('input[type="checkbox"][aria-required="true"]').setValue(true);
        await pulsanteOrdine(pagina).trigger('click');

        expect(invii).toHaveLength(1);
        expect(invii[0].url).toBe('/shop.checkout.store');
        expect(pagina.find('#errore-payment_gateway').exists()).toBe(false);
    });

    it('il telefono dell\'ospite si chiede al passo 1, come fa il server', async () => {
        const pagina = monta();

        await pagina.find('[data-passaggio-successivo]').trigger('click');
        await flushPromises();

        expect(pagina.find('#checkout-phone').attributes('aria-required')).toBe('true');
        expect(pagina.find('#errore-guest_phone').text()).toBe('Campo obbligatorio');
    });

    it('con la fatturazione diversa la provincia si chiede al passo 1, come fa il server', async () => {
        const pagina = monta();
        const casella = pagina.findAll('input[type="checkbox"]').find((c) => c.element.checked);
        await casella.setValue(false);
        for (const [selettore, valore] of Object.entries({
            '#billing-first-name': 'Maria', '#billing-last-name': 'Rossi', '#billing-street': 'Via Roma 1',
            '#billing-city': 'Firenze', '#billing-zip': '50100',
        })) {
            await pagina.find(selettore).setValue(valore);
        }

        await compilaIlPasso1(pagina);

        expect(pagina.find('#errore-billing_province').text()).toBe('Campo obbligatorio');
        expect(pagina.text()).not.toContain('Passo 2 di 2');
    });

    it('passando al pagamento il focus va al titolo del passo', async () => {
        const pagina = monta();
        await compilaIlPasso1(pagina);

        const titolo = pagina.find('h2.sr-only');
        expect(titolo.text()).toBe('Passo 2 di 2: Pagamento');
        expect(document.activeElement).toBe(titolo.element);
    });
});

describe('Checkout dello shop: axe sugli stati del modulo', () => {
    it('passo 1 vuoto', async () => {
        const pagina = monta();

        expect(await violazioniAxe(pagina.element)).toEqual([]);
    });

    it('passo 1 con gli errori a schermo', async () => {
        const pagina = monta();
        await pagina.find('[data-passaggio-successivo]').trigger('click');
        await flushPromises();

        expect(await violazioniAxe(pagina.element)).toEqual([]);
    });

    it('passo 2 con gli errori a schermo', async () => {
        const pagina = monta();
        await compilaIlPasso1(pagina);
        await pulsanteOrdine(pagina).trigger('click');
        await flushPromises();

        expect(await violazioniAxe(pagina.element)).toEqual([]);
    });
});
