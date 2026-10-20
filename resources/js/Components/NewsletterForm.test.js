import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { invii, opzioniGlobali } from '@/testing/paginaDiProva.js';

vi.mock('@inertiajs/vue3', async () => (await import('@/testing/paginaDiProva.js')).inertiaFinto());
vi.mock('@/conversioni.js', () => ({ moduloInviato: vi.fn() }));

const { moduloInviato } = await import('@/conversioni.js');
const { default: NewsletterForm } = await import('./NewsletterForm.vue');

let wrapper;
afterEach(() => wrapper?.unmount());
beforeEach(() => {
    invii.length = 0;
    moduloInviato.mockClear();
});

/**
 * La conversione si conta quando il server ha accettato l'iscrizione, non al
 * clic: un invio respinto (email non valida, troppi tentativi) non e' un
 * contatto.
 */
describe('NewsletterForm, conversione', () => {
    it('conta generate_lead solo quando l\'invio riesce', async () => {
        wrapper = mount(NewsletterForm, { props: { variant: 'footer' }, global: opzioniGlobali() });

        await wrapper.find('form').trigger('submit');
        expect(invii).toHaveLength(1);
        expect(moduloInviato).not.toHaveBeenCalled();

        invii[0].opzioni.onError({ email: 'Email non valida' });
        expect(moduloInviato).not.toHaveBeenCalled();

        invii[0].opzioni.onSuccess();
        expect(moduloInviato).toHaveBeenCalledWith('newsletter');
    });
});
