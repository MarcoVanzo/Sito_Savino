import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, ref } from 'vue';
import { useStatoDellaDiretta } from './useStatoDellaDiretta.js';

function ospite(attivo, statoDiProva = null) {
    let esposto;
    const Ospite = defineComponent({
        setup() {
            esposto = useStatoDellaDiretta({ statoUrl: statoDiProva ? null : 'https://cdn/diretta/1.json', oraUrl: null, attivo, statoDiProva });

            return () => null;
        },
    });

    return { wrapper: mount(Ospite), stato: () => esposto };
}

describe('useStatoDellaDiretta', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('non chiede nulla finché la diretta non è aperta', async () => {
        globalThis.fetch = vi.fn(async () => ({ ok: true, json: async () => ({ versione: 1 }) }));
        const attivo = ref(false);
        ospite(attivo);

        await vi.advanceTimersByTimeAsync(30_000);
        expect(globalThis.fetch).not.toHaveBeenCalled();

        attivo.value = true;
        await vi.advanceTimersByTimeAsync(10);
        expect(globalThis.fetch).toHaveBeenCalledTimes(1);
    });

    it('tiene lo stato più recente anche se arriva una copia vecchia', async () => {
        let versione = 5;
        globalThis.fetch = vi.fn(async () => ({ ok: true, json: async () => ({ versione, stato: 'in_onda', playlist: [] }) }));
        const { stato } = ospite(ref(true));
        await vi.advanceTimersByTimeAsync(10);

        versione = 4;
        await vi.advanceTimersByTimeAsync(10_000);

        expect(stato().stato.value.versione).toBe(5);
    });

    it('chiusa la diretta smette di chiedere', async () => {
        globalThis.fetch = vi.fn(async () => ({ ok: true, json: async () => ({ versione: 1 }) }));
        const { wrapper } = ospite(ref(true));
        await vi.advanceTimersByTimeAsync(10);
        wrapper.unmount();

        await vi.advanceTimersByTimeAsync(60_000);
        expect(globalThis.fetch).toHaveBeenCalledTimes(1);
    });

    it('in prova la cornice compare appena parte la diretta e torna ogni minuto, senza richieste', async () => {
        globalThis.fetch = vi.fn();
        const cornice = { id: 4, url: 'https://cdn/c.png', durata: 15 };
        const prova = { versione: 1, stato: 'in_onda', ciclo: { attivo: true, inizio: null, fine: null, ogni_minuti: 1, durata_secondi: 15 }, playlist: [cornice] };
        const attivo = ref(false);
        const { stato } = ospite(attivo, prova);

        expect(stato().contenuto.value).toBeNull();
        attivo.value = true;
        await vi.advanceTimersByTimeAsync(10);
        expect(stato().contenuto.value?.id).toBe(4);

        await vi.advanceTimersByTimeAsync(20_000);
        expect(stato().contenuto.value).toBeNull();

        await vi.advanceTimersByTimeAsync(41_000);
        expect(stato().contenuto.value?.id).toBe(4);
        expect(globalThis.fetch).not.toHaveBeenCalled();
    });
});
