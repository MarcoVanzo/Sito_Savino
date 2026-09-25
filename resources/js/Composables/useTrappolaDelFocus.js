import { nextTick, onUnmounted, watch } from 'vue';

const FOCALIZZABILI = [
    'a[href]', 'button:not([disabled])', 'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])', 'textarea:not([disabled])', '[tabindex]:not([tabindex="-1"])',
].join(',');

/**
 * Tiene il focus dentro un pannello a comparsa mentre e' aperto (WCAG 2.4.3):
 * all'apertura lo porta sul primo elemento del pannello, il Tab non esce sul
 * sito coperto dal pannello, e alla chiusura torna sull'elemento che l'aveva
 * aperto. Serve ai pannelli che non sono un <dialog> nativo (carrello, menu
 * mobile): quelli con showModal() lo fanno gia' da soli.
 *
 * @param {import('vue').Ref<HTMLElement|null>} contenitore
 * @param {import('vue').Ref<boolean>} aperto
 */
export function useTrappolaDelFocus(contenitore, aperto) {
    let daRipristinare = null;

    const elementi = () => [...(contenitore.value?.querySelectorAll(FOCALIZZABILI) ?? [])]
        .filter((el) => el.offsetParent !== null || el === document.activeElement);

    const suTab = (evento) => {
        if (evento.key !== 'Tab' || !aperto.value) return;

        const lista = elementi();
        if (lista.length === 0) return;

        const primo = lista[0];
        const ultimo = lista[lista.length - 1];

        if (evento.shiftKey && document.activeElement === primo) {
            evento.preventDefault();
            ultimo.focus();
        } else if (!evento.shiftKey && document.activeElement === ultimo) {
            evento.preventDefault();
            primo.focus();
        } else if (!contenitore.value?.contains(document.activeElement)) {
            evento.preventDefault();
            primo.focus();
        }
    };

    watch(aperto, async (valore) => {
        if (valore) {
            daRipristinare = document.activeElement;
            document.addEventListener('keydown', suTab);
            await nextTick();
            elementi()[0]?.focus();
        } else {
            document.removeEventListener('keydown', suTab);
            if (daRipristinare && document.contains(daRipristinare)) {
                daRipristinare.focus();
            }
            daRipristinare = null;
        }
    });

    onUnmounted(() => document.removeEventListener('keydown', suTab));
}
