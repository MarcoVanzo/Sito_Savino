import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { inertiaFinto, opzioniGlobali, pagina, violazioniAxe } from '@/testing/paginaDiProva.js';
import { salvaIlConsenso } from '@/consenso.js';
import StrisciaSponsor from './StrisciaSponsor.vue';
import EventiInHome from './EventiInHome.vue';
import PopupMatchDay from './PopupMatchDay.vue';

vi.mock('@inertiajs/vue3', () => inertiaFinto());

const VERSIONE = '2026-09-22';

const sponsor = (n) => Array.from({ length: n }, (_, i) => ({
    id: i + 1,
    name: `Sponsor ${i + 1}`,
    website_url: `https://sponsor${i + 1}.example`,
    logo_url: `/logo-${i + 1}.png`,
}));

const monta = (componente, props) => mount(componente, { props, global: opzioniGlobali(), attachTo: document.body });

describe('StrisciaSponsor', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('scorre con una seconda copia fuori dal Tab e dagli screen reader', async () => {
        const striscia = monta(StrisciaSponsor, { sponsor: sponsor(8) });

        const copie = striscia.findAll('li[aria-hidden="true"]');
        expect(copie).toHaveLength(8);
        copie.forEach((voce) => {
            expect(voce.find('a').attributes('tabindex')).toBe('-1');
            expect(voce.find('img').attributes('alt')).toBe('');
        });
        expect(striscia.find('li:not([aria-hidden]) img').attributes('alt')).toBe('Sponsor 1');
        expect(striscia.find('li:not([aria-hidden]) a').attributes('rel')).toContain('sponsored');
        expect(await violazioniAxe(striscia.element)).toEqual([]);
    });

    it('in pausa mostra tutti i loghi fermi, una volta sola', async () => {
        const striscia = monta(StrisciaSponsor, { sponsor: sponsor(8), fermo: true });

        expect(striscia.find('.striscia').exists()).toBe(false);
        expect(striscia.findAll('img')).toHaveLength(8);
        expect(striscia.find('button').text()).toBe('Riprendi le animazioni');

        await striscia.find('button').trigger('click');
        expect(striscia.emitted('alterna')).toHaveLength(1);
    });

    it('con pochi sponsor non scorre e non offre la pausa; senza sponsor sparisce', () => {
        const pochi = monta(StrisciaSponsor, { sponsor: sponsor(3) });
        expect(pochi.find('.striscia').exists()).toBe(false);
        expect(pochi.find('button').exists()).toBe(false);

        expect(monta(StrisciaSponsor, { sponsor: [] }).find('section').exists()).toBe(false);
    });
});

describe('EventiInHome', () => {
    it('senza eventi la sezione non c\'è', () => {
        expect(monta(EventiInHome, { eventi: [] }).find('section').exists()).toBe(false);
    });

    it('mostra data, titolo e un link che dice a quale evento porta', async () => {
        const eventi = monta(EventiInHome, {
            eventi: [{
                id: 1,
                titolo: 'Presentazione della squadra',
                descrizione: 'Squadra e tifosi insieme.',
                luogo: 'Palazzetto',
                inizia_il: '2026-10-10T18:30:00+02:00',
                finisce_il: null,
                link: 'https://biglietti.example/evento',
                immagine: '',
            }],
        });

        expect(eventi.find('time').attributes('datetime')).toBe('2026-10-10T18:30:00+02:00');
        expect(eventi.find('[aria-hidden="true"] .text-2xl').text()).toBe('10');
        const link = eventi.find('a[href="https://biglietti.example/evento"]');
        expect(link.text()).toContain('Presentazione della squadra');
        expect(await violazioniAxe(eventi.element)).toEqual([]);
        document.body.innerHTML = '';
    });
});

describe('PopupMatchDay', () => {
    const popup = { titolo: 'Oggi si gioca!', testo: 'Vieni al palazzetto.', pulsante: 'Acquista i biglietti', url: '' };

    beforeEach(() => {
        vi.useFakeTimers();
        localStorage.clear();
        sessionStorage.clear();
        pagina.props.consensoCookie = { versione: VERSIONE };
        HTMLDialogElement.prototype.showModal ??= function showModal() { this.open = true; };
        HTMLDialogElement.prototype.close ??= function close() { this.open = false; };
    });

    afterEach(() => {
        vi.useRealTimers();
        document.body.innerHTML = '';
    });

    it('aspetta la risposta al banner dei cookie, poi si apre una volta sola', async () => {
        const finestra = monta(PopupMatchDay, { popup });

        vi.advanceTimersByTime(5000);
        await nextTick();
        expect(finestra.find('dialog').element.open).toBe(false);

        salvaIlConsenso({ statistiche: false, marketing: false, versione: VERSIONE });
        vi.advanceTimersByTime(1000);
        await nextTick();
        await nextTick();
        expect(finestra.find('dialog').element.open).toBe(true);
        // Senza indirizzo dalla redazione porta alla Biglietteria.
        expect(finestra.find('a').attributes('href')).toBe('/ticketing.page/biglietteria');
        // axe usa setTimeout: con i timer finti non finirebbe mai.
        vi.useRealTimers();
        expect(await violazioniAxe(finestra.element)).toEqual([]);
        vi.useFakeTimers();

        finestra.unmount();
        const seconda = monta(PopupMatchDay, { popup });
        vi.advanceTimersByTime(5000);
        await nextTick();
        expect(seconda.find('dialog').element.open).toBe(false);
    });

    it('con il consenso già dato si apre da solo e porta al link della redazione', async () => {
        salvaIlConsenso({ statistiche: false, marketing: false, versione: VERSIONE });
        const finestra = monta(PopupMatchDay, { popup: { ...popup, url: 'https://www.vivaticket.com/partita' } });

        vi.advanceTimersByTime(2000);
        await nextTick();
        await nextTick();

        expect(finestra.find('dialog').element.open).toBe(true);
        const link = finestra.find('a');
        expect(link.attributes('href')).toBe('https://www.vivaticket.com/partita');
        expect(link.attributes('target')).toBe('_blank');
    });
});
