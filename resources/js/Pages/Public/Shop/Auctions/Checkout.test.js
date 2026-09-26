import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { invii, opzioniGlobali, pagina, violazioniAxe } from '@/testing/paginaDiProva.js';

vi.mock('@inertiajs/vue3', async () => (await import('@/testing/paginaDiProva.js')).inertiaFinto());

import CheckoutAsta from './Checkout.vue';

/**
 * Il checkout dell'asta e' il modello che quello dello shop ora segue: il
 * pulsante resta attivo, al clic ogni campo vuoto riceve il suo errore e il
 * focus va al primo. Qui si blocca quel comportamento e si passa axe sul
 * modulo con gli errori a schermo, che la scansione in produzione non puo'
 * raggiungere (serve un'asta vinta).
 */
function monta() {
    return mount(CheckoutAsta, {
        props: {
            auction: { id: 1, title: { it: 'Maglia autografata' } },
            product: { id: 9, name: { it: 'Maglia autografata' }, media: [] },
            shippingZones: [{ id: 1, countries: ['IT'], flat_rate: 7.9, weight_rates: [] }],
            checkoutDeadline: new Date(Date.now() + 3 * 86400000).toISOString(),
            winningBid: 120,
            token: '00000000-0000-4000-8000-000000000a11',
        },
        global: opzioniGlobali(),
        attachTo: document.body,
    });
}

const pulsanteOrdine = (p) => p.findAll('button').find((b) => b.text().includes('obbligo di pagamento'));

beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    invii.length = 0;
    pagina.props = { locale: 'it', auth: { user: { name: 'Maria Rossi', email: 'maria@example.test' } }, flash: {} };
});

afterEach(() => {
    vi.useRealTimers();
    document.body.innerHTML = '';
});

describe('Checkout dell\'asta', () => {
    it('il pulsante e\' attivo e al clic segna i campi che mancano, senza inviare', async () => {
        const p = monta();
        expect(pulsanteOrdine(p).attributes('disabled')).toBeUndefined();

        await pulsanteOrdine(p).trigger('click');
        await flushPromises();
        vi.advanceTimersByTime(100);

        expect(invii).toHaveLength(0);
        expect(document.activeElement?.getAttribute('aria-invalid')).toBe('true');
        expect(p.find('#errore-privacy_accepted').exists()).toBe(true);
    });

    it('axe: modulo vuoto e modulo con gli errori', async () => {
        const p = monta();
        expect(await violazioniAxe(p.element)).toEqual([]);

        await pulsanteOrdine(p).trigger('click');
        await flushPromises();
        expect(await violazioniAxe(p.element)).toEqual([]);
    });

    it('il conto alla rovescia si legge per esteso, senza cifre sciolte', async () => {
        const p = monta();

        const testo = p.find('[class*="rounded-xl px-4 py-3"] .sr-only').text();
        expect(testo).toMatch(/^Tempo rimasto: \d+ giorni, \d+ ore e \d+ minuti$/);
        expect(p.find('[class*="rounded-xl px-4 py-3"] [aria-hidden="true"]').exists()).toBe(true);
    });
});
