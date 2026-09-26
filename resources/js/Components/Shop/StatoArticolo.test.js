import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import StatoArticolo from './StatoArticolo.vue';

const globale = { mocks: { $t: (chiave) => chiave } };

/**
 * Le condizioni vendono maglie da gara e autografati «nello stato descritto
 * nella scheda»: il riquadro deve comparire con il testo della redazione, e
 * sparire per gli articoli nuovi.
 */
describe('StatoArticolo', () => {
    it('mostra titolo e stato dichiarato', () => {
        const riquadro = mount(StatoArticolo, {
            props: { testo: 'Indossata in gara il 12/10/2025, non lavata.' },
            global: globale,
        });

        expect(riquadro.find('section').attributes('aria-label')).toBe('shop.item_condition');
        expect(riquadro.find('h3').text()).toBe('shop.item_condition');
        expect(riquadro.find('p').text()).toBe('Indossata in gara il 12/10/2025, non lavata.');
    });

    it('senza stato non disegna niente', () => {
        const riquadro = mount(StatoArticolo, { props: { testo: null }, global: globale });

        expect(riquadro.find('section').exists()).toBe(false);
    });

    it('sul fondo scuro dell\'asta usa il fucsia chiaro, non quello scurito', () => {
        const riquadro = mount(StatoArticolo, { props: { testo: 'Autografo originale', scuro: true }, global: globale });

        expect(riquadro.find('h3').classes()).toContain('text-savino-fucsia-chiaro');
        expect(riquadro.find('p').classes()).toContain('text-gray-100');
    });
});
