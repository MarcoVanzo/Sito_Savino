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

        await diretta.find('video').trigger('playing');
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

    it('i comandi dello spot stanno sotto il riquadro, non sopra il video', async () => {
        const diretta = monta();
        await nextTick();

        expect(diretta.find('.diretta-riquadro [data-salta]').exists()).toBe(false);
        expect(diretta.find('[data-comandi-spot] [data-salta]').exists()).toBe(true);
    });

    it('dalla diretta non ci sono link verso YouTube, solo quelli degli sponsor', async () => {
        const diretta = monta({ sponsor: { gara: 1, preroll: null }, contenuto: cornice });
        larghezza(diretta, 1280);
        await nextTick();

        const esterni = diretta.findAll('a[href]').map((a) => a.attributes('href'));
        expect(esterni.some((h) => /youtube|youtu\.be/.test(h))).toBe(false);
        expect(esterni.every((h) => h === cornice.link)).toBe(true);
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

    it('in prova lo spot si rivede anche se già visto nella scheda', async () => {
        sessionStorage.setItem('diretta-preroll-visto:42', '1');
        const diretta = monta({ sponsor: { gara: 42, prova: true, preroll: { salta_dopo: 5, creativita: [video] } } });
        await nextTick();

        expect(diretta.find('[data-preroll]').exists()).toBe(true);
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

    it('la cornice video si scarica una volta sola, dietro il player', async () => {
        const diretta = monta({ sponsor: { gara: 1, preroll: null }, contenuto: { ...cornice, tipo: 'video', url: 'https://cdn/cornice.mp4' } });
        larghezza(diretta, 1280);
        await nextTick();

        expect(diretta.findAll('video')).toHaveLength(1);
        // Il file sta fuori dal player e sotto di lui: il player ha z-10, il file no.
        expect(diretta.find('[data-player] video').exists()).toBe(false);
        expect(diretta.find('[data-player]').classes()).toContain('z-10');
        expect(diretta.find('[data-cornice-file]').classes()).not.toContain('z-10');
        // Le zone del link non hanno file dentro.
        expect(diretta.find('[data-sponsor="colonna"] video').exists()).toBe(false);
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

    it('ogni evento parte anche verso il report dello sponsor', async () => {
        navigator.sendBeacon = vi.fn(() => true);
        const diretta = monta({ sponsor: { gara: 1, preroll: null, eventi: '/api/diretta/1/eventi' }, contenuto: cornice });
        larghezza(diretta, 1280);
        await nextTick();

        await diretta.find('[data-sponsor="colonna"]').trigger('click');

        expect(navigator.sendBeacon).toHaveBeenCalledWith('/api/diretta/1/eventi', expect.any(Blob));
        delete navigator.sendBeacon;
    });

    it('annuncia lo sponsor accanto alla diretta', async () => {
        const diretta = monta({ sponsor: { gara: 1, preroll: null }, contenuto: cornice });
        await nextTick();

        // Nasce vuota: il testo presente al montaggio non verrebbe letto.
        expect(diretta.find('[aria-live="polite"]').text()).toBe('');
        await vi.advanceTimersByTimeAsync(300);
        expect(diretta.find('[aria-live="polite"]').text()).toBe('Accanto alla diretta: Estra');
    });

    it('uno spot che il browser non fa partire non blocca la diretta, anche senza «Salta»', async () => {
        HTMLMediaElement.prototype.play = () => Promise.reject(new DOMException('NotAllowedError'));
        const diretta = monta({ sponsor: { gara: 7, preroll: { salta_dopo: 0, creativita: [video] } } });
        await vi.advanceTimersByTimeAsync(10);

        expect(diretta.find('[data-guarda-spot]').exists()).toBe(true);
        await vi.advanceTimersByTimeAsync(15_000);

        expect(diretta.find('[data-preroll]').exists()).toBe(false);
        expect(diretta.findComponent({ name: 'ContenutoIncorporato' }).exists()).toBe(true);
    });

    it('uno spot fermo in caricamento lascia la diretta poco dopo la sua durata', async () => {
        const diretta = monta({ sponsor: { gara: 8, preroll: { salta_dopo: 0, creativita: [video] } } });
        await vi.advanceTimersByTimeAsync(24_000);
        expect(diretta.find('[data-preroll]').exists()).toBe(true);

        await vi.advanceTimersByTimeAsync(1_000);
        expect(diretta.find('[data-preroll]').exists()).toBe(false);
        // Mai partito: nessun avvio né completamento nel report.
        expect(diretta.emitted('evento')).toBeUndefined();
    });

    it('dopo «Salta» il fuoco passa al pulsante del tutto schermo', async () => {
        const diretta = monta();
        await vi.advanceTimersByTimeAsync(5_000);

        diretta.find('[data-salta]').element.focus();
        await diretta.find('[data-salta]').trigger('click');
        await nextTick();

        expect(document.activeElement).toBe(diretta.find('[data-tutto-schermo]').element);
    });

    it('la cornice video si ferma col suo pulsante', async () => {
        const pausa = vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(() => {});
        const diretta = monta({ sponsor: { gara: 1, preroll: null }, contenuto: { ...cornice, tipo: 'video', url: 'https://cdn/cornice.mp4' } });
        larghezza(diretta, 1280);
        await nextTick();

        await diretta.find('[data-pausa-cornice]').trigger('click');

        expect(pausa).toHaveBeenCalledTimes(1);
        expect(diretta.find('[data-pausa-cornice]').attributes('aria-pressed')).toBe('true');
        pausa.mockRestore();
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

    it('se il browser lascia in sospeso il tutto schermo, dopo mezzo secondo lo simula', async () => {
        Object.defineProperty(document, 'fullscreenEnabled', { value: true, configurable: true });
        const diretta = monta({ sponsor: { gara: 1, preroll: null } });
        diretta.find('[data-diretta-con-sponsor]').element.requestFullscreen = () => new Promise(() => {});
        await nextTick();

        await diretta.find('[data-tutto-schermo]').trigger('click');
        expect(diretta.find('[data-diretta-con-sponsor]').classes()).not.toContain('diretta-con-sponsor--simulato');

        await vi.advanceTimersByTimeAsync(500);
        expect(diretta.find('[data-diretta-con-sponsor]').classes()).toContain('diretta-con-sponsor--simulato');
        expect(diretta.find('[data-tutto-schermo]').text()).toBe('Esci dal tutto schermo');
    });

    it('senza Fullscreen API (iPhone) simula lo schermo intero e si chiude con Esc', async () => {
        Object.defineProperty(document, 'fullscreenEnabled', { value: false, configurable: true });
        const diretta = monta({ sponsor: { gara: 1, preroll: null } });
        await nextTick();

        await diretta.find('[data-tutto-schermo]').trigger('click');
        const contenitore = diretta.find('[data-diretta-con-sponsor]');
        expect(contenitore.classes()).toContain('diretta-con-sponsor--simulato');
        expect(diretta.text()).toContain('Ruota il telefono');

        const esc = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true });
        contenitore.element.dispatchEvent(esc);
        await nextTick();
        expect(contenitore.classes()).not.toContain('diretta-con-sponsor--simulato');
        // La finestra della diretta non deve chiudersi insieme.
        expect(esc.defaultPrevented).toBe(true);
    });

    it('legge lo stato ogni dieci secondi e cambia disposizione senza rimontare l\'iframe', async () => {
        vi.setSystemTime(new Date('2026-10-18T17:20:00+02:00'));
        const stato = (versione, extra = {}) => ({
            versione,
            stato: 'in_onda',
            ciclo: { attivo: false },
            playlist: [cornice],
            ...extra,
        });
        let risposta = stato(1);
        globalThis.fetch = vi.fn(async (url) => ({
            ok: true,
            json: async () => (String(url).includes('/ora') ? { ora: Date.now() } : risposta),
        }));

        const diretta = monta({ sponsor: { gara: 5, preroll: null, stato: 'https://cdn/diretta/5.json', ora: '/api/diretta/ora' } });
        larghezza(diretta, 1280);
        await vi.advanceTimersByTimeAsync(10);
        const iframe = diretta.findComponent({ name: 'ContenutoIncorporato' }).element;
        expect(diretta.find('[data-sponsor="colonna"]').exists()).toBe(false);

        risposta = stato(2, { stato: 'pausa', dalle: '2026-10-18T17:20:00+02:00', fino_alle: '2026-10-18T17:23:00+02:00', contenuto: cornice });
        await vi.advanceTimersByTimeAsync(10_000);

        expect(diretta.find('[data-sponsor="colonna"]').exists()).toBe(true);
        expect(diretta.findComponent({ name: 'ContenutoIncorporato' }).element).toBe(iframe);
        expect(diretta.emitted('evento').at(-1)[0]).toEqual({ evento: 'pausa_mostrata', creativita: 9 });

        // A fine pausa torna piena, senza altre richieste.
        await vi.advanceTimersByTimeAsync(3 * 60_000);
        expect(diretta.find('[data-sponsor="colonna"]').exists()).toBe(false);
    });

    it('se lo stato non si legge la diretta va senza sponsor', async () => {
        globalThis.fetch = vi.fn(async () => { throw new TypeError('Failed to fetch'); });

        const diretta = monta({ sponsor: { gara: 5, preroll: null, stato: 'https://cdn/diretta/5.json', ora: '/api/diretta/ora' } });
        larghezza(diretta, 1280);
        await vi.advanceTimersByTimeAsync(20_000);

        expect(diretta.findComponent({ name: 'ContenutoIncorporato' }).exists()).toBe(true);
        expect(diretta.find('[data-sponsor]').exists()).toBe(false);
    });

    it('durante il pre-roll e con la scheda nascosta non chiede lo stato', async () => {
        globalThis.fetch = vi.fn(async () => ({ ok: true, json: async () => ({}) }));
        const diretta = monta({ sponsor: { gara: 6, preroll: { salta_dopo: 5, creativita: [video] }, stato: 'https://cdn/diretta/6.json', ora: null } });
        await vi.advanceTimersByTimeAsync(3_000);
        expect(globalThis.fetch).not.toHaveBeenCalled();

        await diretta.find('video').trigger('ended');
        await vi.advanceTimersByTimeAsync(10);
        expect(globalThis.fetch).toHaveBeenCalledTimes(1);

        Object.defineProperty(document, 'visibilityState', { value: 'hidden', configurable: true });
        await vi.advanceTimersByTimeAsync(30_000);
        expect(globalThis.fetch).toHaveBeenCalledTimes(1);
        Object.defineProperty(document, 'visibilityState', { value: 'visible', configurable: true });
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
