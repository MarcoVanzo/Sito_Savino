import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { ricaricaDopoIlRilascio, ricaricaInCorso, RIPROVA_DOPO_MS } from './ricaricaDopoIlRilascio.js';

function finestraFinta(storage = new Map()) {
    const target = new EventTarget();
    return {
        addEventListener: target.addEventListener.bind(target),
        dispatchEvent: target.dispatchEvent.bind(target),
        location: { reload: vi.fn(), assign: vi.fn() },
        sessionStorage: {
            getItem: (k) => storage.get(k) ?? null,
            setItem: (k, v) => storage.set(k, v),
        },
    };
}

const errore = () => new Event('vite:preloadError', { cancelable: true });

describe('ricaricaDopoIlRilascio', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('ricarica la pagina e assorbe l\'errore', () => {
        const f = finestraFinta();
        ricaricaDopoIlRilascio(f);
        const e = errore();
        f.dispatchEvent(e);
        expect(f.location.reload).toHaveBeenCalledOnce();
        expect(e.defaultPrevented).toBe(true);
        expect(ricaricaInCorso()).toBe(true);
    });

    it('non ricarica due volte di fila: un pezzo che manca davvero arriva a Sentry', () => {
        const f = finestraFinta();
        ricaricaDopoIlRilascio(f);
        f.dispatchEvent(errore());
        const secondo = errore();
        f.dispatchEvent(secondo);
        expect(f.location.reload).toHaveBeenCalledOnce();
        expect(secondo.defaultPrevented).toBe(false);
    });

    it('torna a ricaricare dopo l\'intervallo', () => {
        const f = finestraFinta();
        ricaricaDopoIlRilascio(f);
        f.dispatchEvent(errore());
        vi.advanceTimersByTime(RIPROVA_DOPO_MS + 1);
        f.dispatchEvent(errore());
        expect(f.location.reload).toHaveBeenCalledTimes(2);
    });

    it('senza sessionStorage non ricarica', () => {
        const f = finestraFinta();
        f.sessionStorage.getItem = () => { throw new Error('bloccato'); };
        ricaricaDopoIlRilascio(f);
        f.dispatchEvent(errore());
        expect(f.location.reload).not.toHaveBeenCalled();
    });

    it('apre la pagina della visita in corso invece di ricaricare quella di partenza', () => {
        const f = finestraFinta();
        ricaricaDopoIlRilascio(f, () => 'https://sito.test/gallery');
        f.dispatchEvent(errore());
        expect(f.location.assign).toHaveBeenCalledWith('https://sito.test/gallery');
        expect(f.location.reload).not.toHaveBeenCalled();
    });
});
