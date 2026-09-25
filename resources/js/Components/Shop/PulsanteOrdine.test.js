import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PulsanteOrdine from './PulsanteOrdine.vue';
import AccettazioneCondizioni from './AccettazioneCondizioni.vue';

const globale = {
    mocks: { $t: (chiave) => chiave, route: (nome, slug) => `/${slug ?? nome}` },
};

/**
 * Il pulsante che chiude l'ordine deve dire «Ordine con obbligo di pagamento»
 * (art. 51 c. 2 del Codice del consumo), altrimenti il contratto non vincola
 * il cliente. La dicitura sta sul pulsante, sotto l'etichetta: è questo che
 * si blocca, perché una nota accanto non basterebbe.
 */
describe('PulsanteOrdine', () => {
    it('porta la dicitura dell\'obbligo di pagamento dentro il pulsante', () => {
        const pulsante = mount(PulsanteOrdine, { props: { etichetta: 'Conferma Ordine' }, global: globale });

        const bottone = pulsante.find('button');
        expect(bottone.text()).toContain('Conferma Ordine');
        expect(bottone.text()).toContain('shop_checkout.payment_obligation');
    });

    it('la dicitura resta anche mentre l\'ordine parte', () => {
        const pulsante = mount(PulsanteOrdine, { props: { etichetta: 'Paga ora', inCorso: true }, global: globale });

        expect(pulsante.text()).toContain('shop_checkout.processing');
        expect(pulsante.text()).toContain('shop_checkout.payment_obligation');
    });

    it('disabilitato non emette il click', async () => {
        const pulsante = mount(PulsanteOrdine, { props: { etichetta: 'x', disabilitato: true }, global: globale });

        await pulsante.find('button').trigger('click');

        expect(pulsante.emitted('click')).toBeUndefined();
    });
});

describe('AccettazioneCondizioni', () => {
    it('porta a condizioni di vendita, recesso e privacy', () => {
        const casella = mount(AccettazioneCondizioni, { global: globale });

        // Le tre pagine stanno nella frase della casella; il quarto link e'
        // quello dell'avviso UE sulla garanzia legale, dentro la sua finestra.
        const indirizzi = casella.find('label').findAll('a').map((a) => a.attributes('href'));
        expect(indirizzi).toEqual(['/condizioni-di-vendita', '/diritto-di-recesso', '/privacy-policy']);
    });

    it("mostra l'avviso UE sulla garanzia legale prima dell'ordine", () => {
        const casella = mount(AccettazioneCondizioni, { global: globale });

        expect(casella.find('dialog img').attributes('src')).toBe('/images/garanzia/avviso-garanzia-legale-it.png');
    });

    it('aggiorna il modello quando si spunta', async () => {
        const casella = mount(AccettazioneCondizioni, {
            props: { modelValue: false, 'onUpdate:modelValue': (v) => casella.setProps({ modelValue: v }) },
            global: globale,
        });

        await casella.find('input[type="checkbox"]').setValue(true);

        expect(casella.props('modelValue')).toBe(true);
    });
});
