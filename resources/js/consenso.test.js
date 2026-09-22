import { describe, it, expect, beforeEach, vi } from 'vitest';
import { leggiIlConsenso, salvaIlConsenso, CHIAVE_CONSENSO } from './consenso.js';

/**
 * La lettura della scelta salvata nel browser. La fanno in due — `app.js`, che
 * decide se far partire GA4 e il Pixel, e il banner — e una divergenza fra le
 * due si vedrebbe come un tracker che parte senza consenso.
 */
describe('leggiIlConsenso', () => {
    beforeEach(() => localStorage.clear());

    it('senza niente in archivio non dà per scelto niente', () => {
        expect(leggiIlConsenso()).toEqual({ scelto: false, statistiche: false, marketing: false });
    });

    it('legge una scelta salvata', () => {
        salvaIlConsenso({ statistiche: true, marketing: false, versione: '2026-09-22', riferimento: 'abc' });

        const letto = leggiIlConsenso('2026-09-22');

        expect(letto.scelto).toBe(true);
        expect(letto.statistiche).toBe(true);
        expect(letto.marketing).toBe(false);
        expect(letto.riferimento).toBe('abc');
    });

    it('con un\'informativa nuova la scelta di prima non vale più', () => {
        salvaIlConsenso({ statistiche: true, marketing: true, versione: '2026-01-01' });

        const letto = leggiIlConsenso('2026-09-22');

        expect(letto.scelto).toBe(false);
        expect(letto.versioneSuperata).toBe(true);
        // Le caselle restano come le aveva lasciate: il banner si riapre già
        // compilato, non azzerato.
        expect(letto.statistiche).toBe(true);
        expect(letto.marketing).toBe(true);
    });

    it('legge le scelte salvate prima del registro, che usavano "analytics"', () => {
        localStorage.setItem(CHIAVE_CONSENSO, JSON.stringify({
            necessary: true, analytics: true, marketing: false, timestamp: '2026-09-01T10:00:00Z',
        }));

        const letto = leggiIlConsenso();

        expect(letto.scelto).toBe(true);
        expect(letto.statistiche).toBe(true);
        expect(letto.data).toBe('2026-09-01T10:00:00Z');
    });

    it('un valore illeggibile vale come nessuna scelta', () => {
        localStorage.setItem(CHIAVE_CONSENSO, '{non è json');

        expect(leggiIlConsenso().scelto).toBe(false);
    });

    it('sopravvive a un archivio che non si lascia leggere', () => {
        const rotto = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('navigazione privata');
        });

        expect(leggiIlConsenso().scelto).toBe(false);

        rotto.mockRestore();
    });

    it('non dà per buono un consenso che non è stato dato', () => {
        salvaIlConsenso({ statistiche: false, marketing: false, versione: '2026-09-22' });

        const letto = leggiIlConsenso('2026-09-22');

        expect(letto.scelto).toBe(true);
        expect(letto.statistiche).toBe(false);
        expect(letto.marketing).toBe(false);
    });
});
