import { describe, it, expect, beforeEach } from 'vitest';
import {
    creativitaValide,
    disposizione,
    indirizzoSenzaTuttoSchermo,
    modoDellaDisposizione,
    oggiLocale,
    prerollGiaVisto,
    sceltaDelPreroll,
    segnaPrerollVisto,
    sovrapposti,
    tuttoSchermoDisponibile,
} from './direttaConSponsor.js';

const spot = (id, extra = {}) => ({ id, tipo: 'video', url: `https://cdn/spot-${id}.mp4`, durata: 15, ...extra });

describe('direttaConSponsor', () => {
    beforeEach(() => sessionStorage.clear());

    it('il giorno è quello del visitatore, non di Greenwich', () => {
        expect(oggiLocale(new Date(2026, 9, 18, 0, 30))).toBe('2026-10-18');
    });

    it('scarta le creatività non ancora valide o già scadute', () => {
        const lista = [spot(1, { dal: '2026-10-19' }), spot(2, { al: '2026-10-17' }), spot(3, { dal: '2026-10-18', al: '2026-10-18' }), { id: 4 }];

        expect(creativitaValide(lista, '2026-10-18').map((c) => c.id)).toEqual([3]);
    });

    it('sceglie uno spot a rotazione e nessuno se non ce ne sono', () => {
        const preroll = { creativita: [spot(1), spot(2), spot(3)] };

        expect(sceltaDelPreroll(preroll, '2026-10-18', () => 0).id).toBe(1);
        expect(sceltaDelPreroll(preroll, '2026-10-18', () => 0.99).id).toBe(3);
        expect(sceltaDelPreroll({ creativita: [] })).toBeNull();
        expect(sceltaDelPreroll(null)).toBeNull();
    });

    it('ricorda il pre-roll visto per gara, nella scheda', () => {
        expect(prerollGiaVisto(7)).toBe(false);
        segnaPrerollVisto(7);
        expect(prerollGiaVisto(7)).toBe(true);
        expect(prerollGiaVisto(8)).toBe(false);
    });

    it('spegne il tutto schermo di YouTube e lascia stare le altre piattaforme', () => {
        expect(indirizzoSenzaTuttoSchermo('https://www.youtube-nocookie.com/embed/abc123')).toBe('https://www.youtube-nocookie.com/embed/abc123?fs=0');
        expect(indirizzoSenzaTuttoSchermo('https://player.vimeo.com/video/1')).toBe('https://player.vimeo.com/video/1');
    });

    it('sul telefono e sotto i 640 px il player non si riduce', () => {
        expect(modoDellaDisposizione({ larghezza: 1280, conSponsor: true })).toBe('cornice');
        expect(modoDellaDisposizione({ larghezza: 700, conSponsor: true })).toBe('telefono');
        expect(modoDellaDisposizione({ larghezza: 1280, conSponsor: false })).toBe('pieno');
    });

    it('nessun pezzo dello sponsor tocca il rettangolo del player', () => {
        for (const modo of ['pieno', 'cornice', 'telefono']) {
            const { player, colonna, fascia } = disposizione(modo);

            expect(sovrapposti(player, colonna)).toBe(false);
            expect(sovrapposti(player, fascia)).toBe(false);
        }
    });

    it('in cornice il player tiene il 75% e almeno 480 px su 640', () => {
        const { player, colonna, fascia } = disposizione('cornice');

        expect(player).toEqual({ x: 0, y: 0, w: 75, h: 75 });
        expect(colonna).toEqual({ x: 75, y: 0, w: 25, h: 75 });
        expect(fascia).toEqual({ x: 0, y: 75, w: 100, h: 25 });
        expect(modoDellaDisposizione({ larghezza: 639, conSponsor: true })).toBe('telefono');
    });

    it('il tutto schermo vero serve la Fullscreen API sull\'elemento', () => {
        expect(tuttoSchermoDisponibile({ requestFullscreen() {} }, { fullscreenEnabled: true })).toBe(true);
        expect(tuttoSchermoDisponibile({}, { fullscreenEnabled: true })).toBe(false);
        expect(tuttoSchermoDisponibile({ requestFullscreen() {} }, { fullscreenEnabled: false })).toBe(false);
    });
});
