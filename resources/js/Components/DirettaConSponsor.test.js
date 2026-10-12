import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import DirettaConSponsor from './DirettaConSponsor.vue';
import { violazioniAxe } from '@/testing/paginaDiProva.js';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { locale: 'it', consensoCookie: { versione: '2026-09-22' } } }),
}));

const SRC = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ';

const video = { id: 3, tipo: 'video', url: 'https://cdn/spot.mp4', durata: 15, link: 'https://www.estra.it/', alt: 'Estra', sponsor: 'Estra' };
const cornice = { id: 9, tipo: 'immagine', url: 'https://cdn/cornice.png', poster: 'https://cdn/cornice.png', fascia_mobile: null, durata: 15, link: 'https://www.estra.it/', alt: 'Estra, energia', sponsor: 'Estra' };

function monta(props = {}) {
    return mount(DirettaConSponsor, {
        attachTo: document.body,
        props: {
            src: SRC,
            titolo: 'Savino — Conegliano',
            linkDiretto: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            sponsor: { gara: 42, preroll: { salta_dopo: 5, creativita: [video] } },
            ...props,
        },
    });
}

function larghezza(diretta, px) {
    diretta.vm.$.setupState.larghezza = px;
}

describe('DirettaConSponsor', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        sessionStorage.clear();
        localStorage.clear();
        window.matchMedia = (query) => ({ matches: false, media: query, addEventListener() {}, removeEventListener() {} });
        HTMLMediaElement.prototype.play = () => Promise.resolve();
    });

    afterEach(() => {
        vi.useRealTimers();
        document.body.innerHTML = '';
    });

    it('prima lo spot, senza iframe; a fine spot la diretta', async () => {
        const diretta = monta();
        await nextTick();

        expect(diretta.find('[data-preroll] video').exists()).toBe(true);
        expect(diretta.findComponent({ name: 'ContenutoIncorporato' }).exists()).toBe(false);

        await diretta.find('video').trigger('ended');

        expect(diretta.find('[data-preroll]').exists()).toBe(false);
        expect(diretta.findComponent({ name: 'ContenutoIncorporato' }).props('src')).toBe(`${SRC}?fs=0`);
        expect(diretta.emitted('evento').map(([e]) => e.evento)).toEqual(['preroll_avviato', 'preroll_completato']);
    });

    it('«Salta» funziona solo dopo i secondi decisi', async () => {
        const diretta = monta();
        await nextTick();
        const salta = diretta.find('[data-salta]');

        expect(salta.text()).toBe('Puoi saltare tra 5 s');
        expect(salta.attributes('aria-disabled')).toBe('true');
        await salta.trigger('click');
        expect(diretta.find('[data-preroll]').exists()).toBe(true);

        vi.advanceTimersByTime(5000);
        await nextTick();
        expect(salta.text()).toBe('Salta lo spot');

        await salta.trigger('click');
        expect(diretta.find('[data-preroll]').exists()).toBe(false);
        expect(diretta.emitted('evento').at(-1)[0]).toEqual({ evento: 'preroll_saltato', creativita: 3 });
    });

    it('con 0 secondi lo spot non si salta', async () => {
        const diretta = monta({ sponsor: { gara: 42, preroll: { salta_dopo: 0, creativita: [video] } } });
        await nextTick();

        expect(diretta.find('[data-salta]').exists()).toBe(false);
    });

    it('lo spot parte muto e l\'audio si accende col pulsante', async () => {
        const diretta = monta();
        await nextTick();
        const pulsante = diretta.findAll('button').find((b) => b.attributes('aria-pressed') !== undefined && b.text().includes('audio'));

        expect(diretta.find('video').element.muted).toBe(true);
        await pulsante.trigger('click');
        expect(pulsante.attributes('aria-pressed')).toBe('true');
        expect(diretta.find('video').element.muted).toBe(false);
    });

    it('riaprendo la stessa gara nella stessa scheda lo spot non si rivede', async () => {
        monta().unmount();
        const seconda = monta();
        await nextTick();

        expect(seconda.find('[data-preroll]').exists()).toBe(false);
    });

    it('uno spot che non si carica non ferma la diretta', async () => {
        const diretta = monta();
        await nextTick();

        await diretta.find('video').trigger('error');

        expect(diretta.findComponent({ name: 'ContenutoIncorporato' }).exists()).toBe(true);
    });

    it('chi chiede meno movimento vede lo spot fermo, che dura come un\'immagine', async () => {
        window.matchMedia = (query) => ({ matches: query.includes('reduce'), media: query, addEventListener() {}, removeEventListener() {} });
        const diretta = monta();
        await nextTick();

        expect(diretta.find('video').attributes('autoplay')).toBeUndefined();
        vi.advanceTimersByTime(15000);
        await nextTick();
        expect(diretta.find('[data-preroll]').exists()).toBe(false);
    });

    it('la cornice cambia la disposizione senza rimontare l\'iframe', async () => {
        const diretta = monta({ sponsor: { gara: 1, preroll: null } });
        larghezza(diretta, 1280);
        await nextTick();
        const iframe = diretta.findComponent({ name: 'ContenutoIncorporato' }).element;

        await diretta.setProps({ contenuto: cornice });

        expect(diretta.find('[data-player]').attributes('style')).toContain('width: 75%');
        expect(diretta.find('[data-sponsor="colonna"]').exists()).toBe(true);
        expect(diretta.find('[data-sponsor="fascia"]').exists()).toBe(true);
        expect(diretta.findComponent({ name: 'ContenutoIncorporato' }).element).toBe(iframe);

        await diretta.setProps({ contenuto: null });
        expect(diretta.find('[data-player]').attributes('style')).toContain('width: 100%');
        expect(diretta.findComponent({ name: 'ContenutoIncorporato' }).element).toBe(iframe);
    });

    it('nessun elemento sponsor sta dentro il contenitore del player', async () => {
        const diretta = monta({ sponsor: { gara: 1, preroll: null }, contenuto: cornice });
        larghezza(diretta, 1280);
        await nextTick();

        expect(diretta.find('[data-player] [data-sponsor]').exists()).toBe(false);
        expect(diretta.find('[data-player] img').exists()).toBe(false);
    });

    it('sul telefono il player resta intero e la fascia va sotto il riquadro', async () => {
        const diretta = monta({ sponsor: { gara: 1, preroll: null }, contenuto: cornice });
        larghezza(diretta, 375);
        await nextTick();

        expect(diretta.find('[data-player]').attributes('style')).toContain('width: 100%');
        expect(diretta.find('[data-sponsor="colonna"]').exists()).toBe(false);
        const fascia = diretta.find('[data-sponsor="fascia-mobile"]');
        expect(fascia.exists()).toBe(true);
        expect(diretta.find('.diretta-riquadro [data-sponsor="fascia-mobile"]').exists()).toBe(false);
    });

    it('il clic sulla cornice apre una scheda nuova e conta il clic, senza toccare il player', async () => {
        const diretta = monta({ sponsor: { gara: 1, preroll: null }, contenuto: cornice });
        larghezza(diretta, 1280);
        await nextTick();
        const iframe = diretta.findComponent({ name: 'ContenutoIncorporato' }).element;
        const colonna = diretta.find('[data-sponsor="colonna"]');

        expect(colonna.attributes('target')).toBe('_blank');
        expect(colonna.attributes('rel')).toBe('sponsored noopener');
        await colonna.trigger('click');

        expect(diretta.emitted('evento').at(-1)[0]).toEqual({ evento: 'clic', creativita: 9 });
        expect(diretta.findComponent({ name: 'ContenutoIncorporato' }).element).toBe(iframe);
    });

    it('annuncia lo sponsor accanto alla diretta', async () => {
        const diretta = monta({ sponsor: { gara: 1, preroll: null }, contenuto: cornice });
        await nextTick();

        expect(diretta.find('[aria-live="polite"]').text()).toBe('Accanto alla diretta: Estra');
    });

    it('tutto schermo sul contenitore intero, con la cornice dentro', async () => {
        const diretta = monta({ sponsor: { gara: 1, preroll: null }, contenuto: cornice });
        const richiesto = vi.fn().mockResolvedValue();
        Object.defineProperty(document, 'fullscreenEnabled', { value: true, configurable: true });
        diretta.find('[data-diretta-con-sponsor]').element.requestFullscreen = richiesto;
        await nextTick();

        await diretta.find('[data-tutto-schermo]').trigger('click');

        expect(richiesto).toHaveBeenCalledOnce();
        expect(diretta.find('[data-diretta-con-sponsor] [data-sponsor="colonna"]').exists()).toBe(true);
    });

    it('senza Fullscreen API (iPhone) simula lo schermo intero e si chiude con Esc', async () => {
        Object.defineProperty(document, 'fullscreenEnabled', { value: false, configurable: true });
        const diretta = monta({ sponsor: { gara: 1, preroll: null } });
        await nextTick();

        await diretta.find('[data-tutto-schermo]').trigger('click');
        const contenitore = diretta.find('[data-diretta-con-sponsor]');
        expect(contenitore.classes()).toContain('diretta-con-sponsor--simulato');
        expect(diretta.text()).toContain('Ruota il telefono');

        await contenitore.trigger('keydown', { key: 'Escape' });
        expect(contenitore.classes()).not.toContain('diretta-con-sponsor--simulato');
    });

    it('nessuna violazione di axe nei tre stati', async () => {
        vi.useRealTimers();
        const preroll = monta();
        await nextTick();
        expect(await violazioniAxe(preroll.element)).toEqual([]);
        preroll.unmount();

        const piena = monta({ sponsor: { gara: 2, preroll: null } });
        await nextTick();
        expect(await violazioniAxe(piena.element)).toEqual([]);

        await piena.setProps({ contenuto: cornice });
        expect(await violazioniAxe(piena.element)).toEqual([]);
    }, 30000);
});
