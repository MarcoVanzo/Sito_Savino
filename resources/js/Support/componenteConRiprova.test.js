import { describe, it, expect, vi, afterEach } from 'vitest';
import { defineAsyncComponent, createApp, h, nextTick } from 'vue';
import { componenteConRiprova, TENTATIVI, ATTESA_MS } from './componenteConRiprova.js';

const fallito = () => new TypeError('Importing a module script failed.');
const Menu = { render: () => h('nav', 'menu') };

function monta(componente, errori) {
    const radice = document.createElement('div');
    const app = createApp({ render: () => h(componente) });
    app.config.errorHandler = (errore) => errori.push(errore);
    app.mount(radice);

    return radice;
}

async function scorri(ms) {
    await vi.advanceTimersByTimeAsync(ms);
    await nextTick();
}

describe('componenteConRiprova', () => {
    afterEach(() => vi.useRealTimers());

    it('riprova l\'import caduto per la rete e mostra il componente', async () => {
        vi.useFakeTimers();
        const caricatore = vi.fn()
            .mockRejectedValueOnce(fallito())
            .mockRejectedValueOnce(fallito())
            .mockResolvedValue(Menu);
        const errori = [];
        const radice = monta(defineAsyncComponent(componenteConRiprova(caricatore)), errori);

        await scorri(ATTESA_MS * 3);

        expect(caricatore).toHaveBeenCalledTimes(3);
        expect(radice.textContent).toBe('menu');
        expect(errori).toEqual([]);
    });

    it('dopo l\'ultimo tentativo lascia passare l\'errore', async () => {
        vi.useFakeTimers();
        const caricatore = vi.fn().mockRejectedValue(fallito());
        const errori = [];
        monta(defineAsyncComponent(componenteConRiprova(caricatore)), errori);

        await scorri(ATTESA_MS * (TENTATIVI * (TENTATIVI + 1)) / 2 + 100);

        expect(caricatore).toHaveBeenCalledTimes(TENTATIVI + 1);
        expect(errori).toHaveLength(1);
        expect(errori[0].message).toBe('Importing a module script failed.');
    });

    it('aspetta di piu\' a ogni tentativo', () => {
        const attendi = vi.fn();
        const { onError } = componenteConRiprova(() => Promise.resolve(Menu), attendi);
        const riprova = () => {};

        onError(fallito(), riprova, () => {}, 1);
        onError(fallito(), riprova, () => {}, 2);

        expect(attendi.mock.calls.map(([, ms]) => ms)).toEqual([ATTESA_MS, ATTESA_MS * 2]);
    });
});
