import { describe, it, expect, vi, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { opzioniGlobali, violazioniAxe } from '@/testing/paginaDiProva.js';

vi.mock('@inertiajs/vue3', async () => (await import('@/testing/paginaDiProva.js')).inertiaFinto());
// Il modulo della newsletter ha i suoi test: qui interessa la riga legale.
vi.mock('@/Components/NewsletterForm.vue', () => ({ default: { template: '<div />' } }));

const { default: SiteFooter } = await import('./SiteFooter.vue');

let wrapper;
afterEach(() => wrapper?.unmount());

/**
 * Il link «Preferenze cookie» del footer: le linee guida del Garante sui
 * cookie (10/06/2021, §7.1) vogliono le scelte modificabili in ogni momento
 * da un punto sempre raggiungibile.
 */
describe('SiteFooter, preferenze cookie', () => {
    it('è un pulsante accanto alla Cookie Policy che riapre il pannello', async () => {
        wrapper = mount(SiteFooter, { attachTo: document.body, global: opzioniGlobali() });

        const pulsante = wrapper.findAll('button').find((b) => b.text() === 'Preferenze cookie');
        expect(pulsante).toBeDefined();
        expect(pulsante.attributes('type')).toBe('button');
        // Il focus si vede anche da tastiera, come chiede WCAG 2.4.7.
        expect(pulsante.classes()).toContain('focus-visible:ring-2');

        const ascoltatore = vi.fn();
        window.addEventListener('preferenze-cookie:apri', ascoltatore);
        await pulsante.trigger('click');
        window.removeEventListener('preferenze-cookie:apri', ascoltatore);

        expect(ascoltatore).toHaveBeenCalledTimes(1);
    });

    it('la riga legale non ha violazioni di accessibilità', async () => {
        wrapper = mount(SiteFooter, { attachTo: document.body, global: opzioniGlobali() });

        expect(await violazioniAxe(wrapper.element)).toEqual([]);
    });
});
