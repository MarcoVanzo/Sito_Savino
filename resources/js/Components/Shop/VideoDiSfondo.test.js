import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import VideoDiSfondo from './VideoDiSfondo.vue';

vi.mock('@/Composables/useTranslations.js', () => ({ useTranslations: () => (chiave) => chiave }));

/**
 * Il video della testata parte da solo (anche sul telefono), tranne per chi
 * ha chiesto al sistema meno movimento; il pulsante di pausa c'è sempre
 * (WCAG 2.2.2) e ferma davvero il video.
 */
function monta(riduciMovimento = false) {
    window.matchMedia = vi.fn().mockReturnValue({ matches: riduciMovimento });

    return mount(VideoDiSfondo, {
        props: { src: '/video.mp4', poster: '/copertina.jpg' },
        attachTo: document.body,
    });
}

describe('VideoDiSfondo', () => {
    let play;
    let pause;

    beforeEach(() => {
        play = vi.spyOn(HTMLMediaElement.prototype, 'play').mockResolvedValue();
        pause = vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(() => {});
    });

    it('parte muto, in loop e inline, con la copertina', async () => {
        const sfondo = monta();
        await nextTick();

        const video = sfondo.find('video').element;
        expect(video.muted).toBe(true);
        expect(video.loop).toBe(true);
        expect(video.hasAttribute('playsinline')).toBe(true);
        expect(video.getAttribute('poster')).toBe('/copertina.jpg');
        expect(sfondo.find('source').attributes('src')).toBe('/video.mp4');
        expect(play).toHaveBeenCalled();
    });

    it('con meno movimento non parte e offre di riprendere', async () => {
        const sfondo = monta(true);
        await nextTick();

        expect(play).not.toHaveBeenCalled();
        expect(sfondo.find('button').text()).toBe('accessibilita.resume_motion');
    });

    it('il pulsante mette in pausa e fa ripartire', async () => {
        const sfondo = monta();
        await nextTick();

        await sfondo.find('button').trigger('click');
        expect(pause).toHaveBeenCalled();
        expect(sfondo.find('button').text()).toBe('accessibilita.resume_motion');

        await sfondo.find('button').trigger('click');
        expect(play).toHaveBeenCalledTimes(2);
        expect(sfondo.find('button').text()).toBe('accessibilita.pause_motion');
    });
});
