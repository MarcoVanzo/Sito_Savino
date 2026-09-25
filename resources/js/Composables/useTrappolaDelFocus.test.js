import { afterEach, beforeAll, describe, expect, it } from 'vitest';
import { defineComponent, h, nextTick, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { useTrappolaDelFocus } from './useTrappolaDelFocus.js';

// jsdom non calcola il layout: offsetParent e' sempre null e ogni elemento
// sembrerebbe nascosto. Qui "visibile" vuol dire "non marcato hidden".
beforeAll(() => {
    Object.defineProperty(HTMLElement.prototype, 'offsetParent', {
        configurable: true,
        get() { return this.closest('[hidden]') ? null : document.body; },
    });
});

function montaTrappola({ contenitori = 1, conIniziale = false } = {}) {
    const aperto = ref(false);
    const radici = Array.from({ length: contenitori }, () => ref(null));

    const Componente = defineComponent({
        setup() {
            useTrappolaDelFocus(
                contenitori === 1 ? radici[0] : radici,
                aperto,
                conIniziale ? { iniziale: radici[contenitori - 1] } : {},
            );
            return () => h('div', radici.map((r, i) => h('div', { ref: r, 'data-radice': i }, [
                h('button', { id: `a${i}` }, 'a'),
                h('button', { id: `b${i}` }, 'b'),
            ])));
        },
    });

    const wrapper = mount(Componente, { attachTo: document.body });
    return { aperto, wrapper };
}

const tab = (shiftKey = false) => {
    const evento = new KeyboardEvent('keydown', { key: 'Tab', shiftKey, bubbles: true, cancelable: true });
    document.dispatchEvent(evento);
    return evento;
};

describe('useTrappolaDelFocus', () => {
    let montati = [];

    afterEach(() => {
        montati.forEach((w) => w.unmount());
        montati = [];
        document.body.innerHTML = '';
    });

    it('porta il focus dentro e lo fa girare fra primo e ultimo', async () => {
        const { aperto, wrapper } = montaTrappola();
        montati.push(wrapper);
        aperto.value = true;
        await nextTick(); await nextTick();

        expect(document.activeElement.id).toBe('a0');
        tab(true);
        expect(document.activeElement.id).toBe('b0');
        tab();
        expect(document.activeElement.id).toBe('a0');
    });

    it('Shift+Tab da fuori va all\'ultimo elemento', async () => {
        const fuori = document.createElement('button');
        document.body.appendChild(fuori);
        const { aperto, wrapper } = montaTrappola();
        montati.push(wrapper);
        aperto.value = true;
        await nextTick(); await nextTick();

        fuori.focus();
        const evento = tab(true);
        expect(evento.defaultPrevented).toBe(true);
        expect(document.activeElement.id).toBe('b0');
    });

    it('attraversa piu\' contenitori e parte da quello indicato', async () => {
        const { aperto, wrapper } = montaTrappola({ contenitori: 2, conIniziale: true });
        montati.push(wrapper);
        aperto.value = true;
        await nextTick(); await nextTick();

        expect(document.activeElement.id).toBe('a1');
        tab(); // b1, ultimo
        tab(); // torna al primo del primo contenitore
        expect(document.activeElement.id).toBe('a0');
        tab(); tab();
        expect(document.activeElement.id).toBe('a1');
    });

    it('agisce solo l\'ultima trappola aperta', async () => {
        const prima = montaTrappola();
        const seconda = montaTrappola();
        montati.push(prima.wrapper, seconda.wrapper);
        prima.aperto.value = true;
        await nextTick(); await nextTick();
        seconda.aperto.value = true;
        await nextTick(); await nextTick();

        const [, secondaRadice] = document.querySelectorAll('[data-radice="0"]');
        expect(secondaRadice.contains(document.activeElement)).toBe(true);
        tab(); tab(); tab();
        expect(secondaRadice.contains(document.activeElement)).toBe(true);
    });

    it('senza elementi focalizzabili il Tab non esce', async () => {
        const { aperto, wrapper } = montaTrappola();
        montati.push(wrapper);
        wrapper.element.querySelectorAll('button').forEach((b) => { b.disabled = true; });
        aperto.value = true;
        await nextTick(); await nextTick();

        expect(tab().defaultPrevented).toBe(true);
    });

    it('alla chiusura restituisce il focus solo a un elemento ancora visibile', async () => {
        const apritore = document.createElement('button');
        document.body.appendChild(apritore);
        const { aperto, wrapper } = montaTrappola();
        montati.push(wrapper);

        apritore.focus();
        aperto.value = true;
        await nextTick(); await nextTick();
        aperto.value = false;
        await nextTick();
        expect(document.activeElement).toBe(apritore);

        aperto.value = true;
        await nextTick(); await nextTick();
        apritore.hidden = true;
        aperto.value = false;
        await nextTick();
        expect(document.activeElement).not.toBe(apritore);
    });
});
