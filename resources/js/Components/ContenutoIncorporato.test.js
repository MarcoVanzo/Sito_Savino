import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import ContenutoIncorporato from './ContenutoIncorporato.vue';
import { salvaIlConsenso } from '@/consenso.js';

const VERSIONE = '2026-09-22';

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { locale: 'it', consensoCookie: { versione: '2026-09-22' } } }),
}));

/**
 * YouTube e Google Maps scrivono cookie di marketing appena il riquadro si
 * apre, e gli iframe partivano prima di qualsiasi scelta sul banner. Quello
 * che va provato: senza consenso non c'è nessun iframe (quindi nessuna
 * richiesta verso terzi), il clic carica solo quel video, e col consenso dato
 * il riquadro compare subito — anche se lo si dà dopo, dal banner.
 */
const VIDEO = 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ';

function monta(props = {}) {
    return mount(ContenutoIncorporato, {
        props: { src: VIDEO, titolo: 'Intervista', linkDiretto: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', ...props },
    });
}

describe('ContenutoIncorporato', () => {
    beforeEach(() => {
        localStorage.clear();
    });

    it('senza consenso non carica l\'iframe e mostra il segnaposto', async () => {
        const video = monta();
        await nextTick();

        expect(video.find('iframe').exists()).toBe(false);
        expect(video.find('[data-segnaposto-incorporato]').exists()).toBe(true);
        expect(video.text()).toContain('Carica il video (YouTube raccoglie cookie)');
    });

    it('offre il link diretto al video in alternativa', async () => {
        const video = monta();
        await nextTick();

        const link = video.find('a[href="https://www.youtube.com/watch?v=dQw4w9WgXcQ"]');
        expect(link.exists()).toBe(true);
        expect(link.attributes('rel')).toContain('noopener');
    });

    it('al clic carica solo quel video, con l\'avvio automatico', async () => {
        const video = monta();
        const altro = monta();
        await nextTick();

        await video.find('button').trigger('click');

        const iframe = video.find('iframe');
        expect(iframe.exists()).toBe(true);
        expect(iframe.attributes('src')).toBe(`${VIDEO}?autoplay=1`);
        expect(altro.find('iframe').exists()).toBe(false);
    });

    it('col consenso di marketing carica subito, senza avvio automatico', async () => {
        salvaIlConsenso({ statistiche: false, marketing: true, versione: VERSIONE });

        const video = monta();
        await nextTick();

        expect(video.find('iframe').attributes('src')).toBe(VIDEO);
    });

    it('il solo consenso statistico non basta', async () => {
        salvaIlConsenso({ statistiche: true, marketing: false, versione: VERSIONE });

        const video = monta();
        await nextTick();

        expect(video.find('iframe').exists()).toBe(false);
    });

    it('un consenso dato su un\'informativa precedente non vale', async () => {
        salvaIlConsenso({ statistiche: true, marketing: true, versione: '2025-01-01' });

        const video = monta();
        await nextTick();

        expect(video.find('iframe').exists()).toBe(false);
    });

    it('si carica appena il consenso arriva dal banner', async () => {
        const video = monta();
        await nextTick();
        expect(video.find('iframe').exists()).toBe(false);

        salvaIlConsenso({ statistiche: false, marketing: true, versione: VERSIONE });
        await nextTick();

        expect(video.find('iframe').exists()).toBe(true);
    });

    it('la mappa ha il suo testo e nessun autoplay', async () => {
        const mappa = monta({ src: 'https://maps.google.com/maps?q=Pala&output=embed', tipo: 'mappa', linkDiretto: null });
        await nextTick();

        expect(mappa.text()).toContain('Carica la mappa (Google raccoglie cookie)');

        await mappa.find('button').trigger('click');
        expect(mappa.find('iframe').attributes('src')).not.toContain('autoplay');
    });

    it('il pulsante delle preferenze riapre il banner', async () => {
        const ascoltatore = vi.fn();
        window.addEventListener('preferenze-cookie:apri', ascoltatore);

        const video = monta();
        await nextTick();
        await video.findAll('button').at(1).trigger('click');

        expect(ascoltatore).toHaveBeenCalledOnce();
        window.removeEventListener('preferenze-cookie:apri', ascoltatore);
    });
});
