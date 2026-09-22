import { describe, it, expect, beforeEach, vi } from 'vitest';
import {
    leggiIlConsenso,
    salvaIlConsenso,
    registraIlConsenso,
    mettiInAttesa,
    smettiDiAspettare,
    consensoInAttesa,
    CHIAVE_CONSENSO,
    CHIAVE_IN_ATTESA,
} from './consenso.js';

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

describe('salvaIlConsenso', () => {
    beforeEach(() => localStorage.clear());

    it('scrive anche il nome vecchio della casella statistiche', () => {
        // Se un giorno si torna indietro a una versione del sito che leggeva
        // `analytics`, il consenso non si perde e il banner non ricompare.
        salvaIlConsenso({ statistiche: true, marketing: false, versione: '2026-09-22' });

        const scritto = JSON.parse(localStorage.getItem(CHIAVE_CONSENSO));

        expect(scritto.analytics).toBe(true);
        expect(scritto.statistiche).toBe(true);
        expect(scritto.necessary).toBe(true);
    });

    it('senza data mette quella di adesso', () => {
        const prima = Date.now();

        const valore = salvaIlConsenso({ statistiche: false, marketing: false, versione: '2026-09-22' });

        expect(Date.parse(valore.data)).toBeGreaterThanOrEqual(prima);
    });

    it('tiene la data che arriva dal server', () => {
        // È quella registrata nel registro: le due devono raccontare lo stesso
        // momento, o il riferimento mostrato al visitatore non combacia con la
        // riga che lo documenta.
        const valore = salvaIlConsenso({
            statistiche: true, marketing: true, versione: '2026-09-22',
            riferimento: 'abc', data: '2026-09-22T10:00:00.000Z',
        });

        expect(valore.data).toBe('2026-09-22T10:00:00.000Z');
        expect(valore.riferimento).toBe('abc');
    });

    it('un archivio che non si lascia scrivere non fa saltare la pagina', () => {
        const rotto = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('spazio esaurito');
        });

        // Il valore torna comunque: è quello che il banner usa per accendere o
        // spegnere GA4, e non dipende dall'essere riuscito a salvarlo.
        expect(() => salvaIlConsenso({ statistiche: true, marketing: false, versione: '2026-09-22' }))
            .not.toThrow();
        expect(salvaIlConsenso({ statistiche: true, marketing: false, versione: '2026-09-22' }).statistiche).toBe(true);

        rotto.mockRestore();
    });
});

/**
 * La registrazione sul server. Non decide niente di ciò che il sito carica —
 * quello lo fa il valore salvato nel browser — ma è la prova del consenso
 * richiesta dall'art. 7 §1 del GDPR: se si perde per una connessione ballerina
 * resta una scelta che nessuno può dimostrare.
 */
describe('registraIlConsenso', () => {
    beforeEach(() => {
        localStorage.clear();
        window.axios = { post: vi.fn() };
    });

    it('manda la scelta e torna quello che risponde il server', async () => {
        window.axios.post.mockResolvedValue({ data: { riferimento: 'abc', registrato_il: '2026-09-22T10:00:00Z' } });

        const esito = await registraIlConsenso({ statistiche: true, marketing: false }, '/consenso-cookie');

        expect(window.axios.post).toHaveBeenCalledWith('/consenso-cookie', {
            statistiche: true, marketing: false, riferimento: null,
        });
        expect(esito.riferimento).toBe('abc');
    });

    it('porta con sé il riferimento di chi ha già scelto una volta', () => {
        window.axios.post.mockResolvedValue({ data: {} });

        registraIlConsenso({ statistiche: false, marketing: true, riferimento: 'abc' }, '/consenso-cookie');

        expect(window.axios.post).toHaveBeenCalledWith('/consenso-cookie', {
            statistiche: false, marketing: true, riferimento: 'abc',
        });
    });

    it('con la rete assente mette la registrazione in attesa invece di perderla', async () => {
        window.axios.post.mockRejectedValue(new Error('rete assente'));

        const esito = await registraIlConsenso({ statistiche: true, marketing: true }, '/consenso-cookie');

        // Non si mostra niente al visitatore: il sito si comporta già come ha
        // chiesto, ed è solo la prova a essere rimasta indietro.
        expect(esito).toBeNull();
        expect(consensoInAttesa()).toEqual({ statistiche: true, marketing: true, riferimento: null });
    });

    it('andata a buon fine, non resta niente in attesa', async () => {
        mettiInAttesa({ statistiche: false, marketing: false, riferimento: null });
        window.axios.post.mockResolvedValue({ data: { riferimento: 'abc' } });

        await registraIlConsenso({ statistiche: true, marketing: false }, '/consenso-cookie');

        expect(consensoInAttesa()).toBeNull();
    });

    it('una risposta senza corpo non è un errore', async () => {
        window.axios.post.mockResolvedValue({});

        await expect(registraIlConsenso({ statistiche: true, marketing: false }, '/consenso-cookie'))
            .resolves.toBeNull();
    });
});

describe('la registrazione rimasta in attesa', () => {
    beforeEach(() => localStorage.clear());

    it('senza niente in attesa non trova niente', () => {
        expect(consensoInAttesa()).toBeNull();
    });

    it('si mette da parte e si toglie', () => {
        mettiInAttesa({ statistiche: true, marketing: false, riferimento: 'abc' });

        expect(consensoInAttesa().riferimento).toBe('abc');

        smettiDiAspettare();

        expect(consensoInAttesa()).toBeNull();
    });

    it('un valore illeggibile vale come niente in attesa', () => {
        localStorage.setItem(CHIAVE_IN_ATTESA, '{non è json');

        expect(consensoInAttesa()).toBeNull();
    });

    it('un archivio che non si lascia toccare non fa saltare la pagina', () => {
        const scrittura = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('navigazione privata');
        });
        const cancellazione = vi.spyOn(Storage.prototype, 'removeItem').mockImplementation(() => {
            throw new Error('navigazione privata');
        });

        expect(() => mettiInAttesa({ statistiche: true, marketing: true, riferimento: null })).not.toThrow();
        expect(() => smettiDiAspettare()).not.toThrow();

        scrittura.mockRestore();
        cancellazione.mockRestore();
    });
});
