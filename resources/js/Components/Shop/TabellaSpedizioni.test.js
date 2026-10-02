import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import TabellaSpedizioni from './TabellaSpedizioni.vue';

// La lingua arriva dalle props della pagina Inertia, che in un test non c'e'.
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { locale: 'it' } }),
}));

const zona = (soglia) => ({
    nome: 'Italia',
    paesi: ['IT'],
    tariffa: 7.9,
    fasce: [],
    soglia_gratuita: soglia,
    giorni_min: 2,
    giorni_max: 4,
});

// La colonna della soglia e' la terza cella della riga (la prima e' un <th>).
const cellaSoglia = (tabella) => tabella.findAll('tbody td')[1].text();

/**
 * La tabella pubblica delle spedizioni deve dire la stessa soglia che il
 * checkout applica: una soglia assente o a zero non e' "gratuita da 0,00 €".
 */
describe('TabellaSpedizioni', () => {
    it('mostra la soglia quando c\'e\'', () => {
        const tabella = mount(TabellaSpedizioni, { props: { zone: [zona(100)] } });

        expect(cellaSoglia(tabella)).toContain('100,00');
    });

    it.each([null, undefined, 0, -5])('con soglia %s non promette la spedizione gratuita', (soglia) => {
        const tabella = mount(TabellaSpedizioni, { props: { zone: [zona(soglia)] } });

        expect(cellaSoglia(tabella)).toBe('—');
    });
});
