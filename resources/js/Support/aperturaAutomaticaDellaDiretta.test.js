import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { salvaIlConsenso } from '@/consenso.js';
import {
    ATTESA_CON_LA_SCELTA_FATTA,
    ATTESA_DOPO_IL_BANNER,
    aspettaLaScelta,
    conAudioSpento,
    giaAperta,
    ricordaAperta,
} from './aperturaAutomaticaDellaDiretta.js';

describe('aperturaAutomaticaDellaDiretta', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        localStorage.clear();
        sessionStorage.clear();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('apre la diretta senza audio, tenendo i parametri del player', () => {
        expect(conAudioSpento('https://www.youtube-nocookie.com/embed/abc123?rel=0'))
            .toBe('https://www.youtube-nocookie.com/embed/abc123?rel=0&mute=1');
    });

    it('si ricorda la gara aperta per la scheda, non le altre', () => {
        expect(giaAperta(7)).toBe(false);

        ricordaAperta(7);

        expect(giaAperta(7)).toBe(true);
        expect(giaAperta(8)).toBe(false);
    });

    it('senza memoria della scheda non la apre', () => {
        const rotto = { getItem: () => { throw new Error('bloccato'); } };

        expect(giaAperta(7, rotto)).toBe(true);
    });

    it('con il marketing già consentito decide poco dopo il caricamento', () => {
        salvaIlConsenso({ statistiche: false, marketing: true, versione: 'v1' });
        const decidi = vi.fn();

        aspettaLaScelta({ versione: 'v1', decidi });
        vi.advanceTimersByTime(ATTESA_CON_LA_SCELTA_FATTA);

        expect(decidi).toHaveBeenCalledWith(true);
    });

    it('aspetta la risposta al banner prima di decidere', () => {
        const decidi = vi.fn();

        aspettaLaScelta({ versione: 'v1', decidi });
        vi.advanceTimersByTime(5000);
        expect(decidi).not.toHaveBeenCalled();

        salvaIlConsenso({ statistiche: true, marketing: false, versione: 'v1' });
        vi.advanceTimersByTime(ATTESA_DOPO_IL_BANNER);

        expect(decidi).toHaveBeenCalledWith(false);
    });

    it('a chi ha chiesto meno movimento non parte da sola', () => {
        salvaIlConsenso({ statistiche: false, marketing: true, versione: 'v1' });
        const decidi = vi.fn();
        const finestra = {
            matchMedia: (query) => ({ matches: query === '(prefers-reduced-motion: reduce)' }),
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        };

        aspettaLaScelta({ versione: 'v1', decidi, finestra });
        vi.advanceTimersByTime(ATTESA_CON_LA_SCELTA_FATTA);

        expect(decidi).toHaveBeenCalledWith(false);
    });

    it('lasciando la pagina smette di aspettare', () => {
        salvaIlConsenso({ statistiche: false, marketing: true, versione: 'v1' });
        const decidi = vi.fn();

        const smetti = aspettaLaScelta({ versione: 'v1', decidi });
        smetti();
        vi.advanceTimersByTime(ATTESA_CON_LA_SCELTA_FATTA);

        expect(decidi).not.toHaveBeenCalled();
    });
});
