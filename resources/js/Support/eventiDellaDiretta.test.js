import { describe, it, expect, vi, afterEach } from 'vitest';
import { FINESTRA_DI_SPARPAGLIAMENTO, registraEvento } from './eventiDellaDiretta.js';

describe('eventiDellaDiretta', () => {
    afterEach(() => {
        delete navigator.sendBeacon;
    });

    it('manda evento e creatività con sendBeacon, in JSON', async () => {
        const beacon = vi.fn(() => true);
        navigator.sendBeacon = beacon;

        expect(registraEvento('/api/diretta/4/eventi', { evento: 'clic', creativita: 7 })).toBe(true);

        const [url, blob] = beacon.mock.calls[0];
        expect(url).toBe('/api/diretta/4/eventi');
        expect(blob.type).toBe('application/json');
        expect(JSON.parse(await blob.text())).toEqual({ evento: 'clic', creativita: 7 });
    });

    it('senza sendBeacon ripiega su fetch keepalive', () => {
        globalThis.fetch = vi.fn(() => Promise.resolve({}));

        registraEvento('/api/diretta/4/eventi', { evento: 'preroll_avviato', creativita: 1 });

        expect(globalThis.fetch.mock.calls[0][1]).toMatchObject({ method: 'POST', keepalive: true, credentials: 'omit' });
    });

    it('senza indirizzo o senza creatività non manda nulla', () => {
        navigator.sendBeacon = vi.fn();

        expect(registraEvento(null, { evento: 'clic', creativita: 1 })).toBe(false);
        expect(registraEvento('/x', { evento: 'clic', creativita: null })).toBe(false);
        expect(navigator.sendBeacon).not.toHaveBeenCalled();
    });

    it('le cornici viste da tutti insieme partono sparpagliate nel minuto', () => {
        vi.useFakeTimers();
        navigator.sendBeacon = vi.fn(() => true);

        registraEvento('/x', { evento: 'ciclo_mostrato', creativita: 2 }, () => 0.5);
        expect(navigator.sendBeacon).not.toHaveBeenCalled();

        vi.advanceTimersByTime(FINESTRA_DI_SPARPAGLIAMENTO / 2);
        expect(navigator.sendBeacon).toHaveBeenCalledTimes(1);

        // Il clic è del singolo visitatore: parte subito.
        registraEvento('/x', { evento: 'clic', creativita: 2 });
        expect(navigator.sendBeacon).toHaveBeenCalledTimes(2);
        vi.useRealTimers();
    });
});
