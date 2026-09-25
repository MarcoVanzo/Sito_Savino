import { nextTick, onUnmounted, unref, watch } from 'vue';

const FOCALIZZABILI = [
    'a[href]', 'button:not([disabled])', 'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])', 'textarea:not([disabled])', '[tabindex]:not([tabindex="-1"])',
].join(',');

// Le trappole aperte, dall'ultima in cima. Agisce solo quella in cima: il
// carrello aperto dal badge del menu mobile deve trattenere il Tab senza che la
// trappola del menu, rimasta aperta sotto, glielo riporti via.
const pila = [];

const visibile = (el) => el.offsetParent !== null || el.getClientRects().length > 0;

/**
 * Tiene il focus dentro un pannello a comparsa mentre e' aperto (WCAG 2.4.3):
 * all'apertura lo porta sul primo elemento del pannello, il Tab non esce sul
 * sito coperto dal pannello, e alla chiusura torna sull'elemento che l'aveva
 * aperto. Serve ai pannelli che non sono un <dialog> nativo (carrello, menu
 * mobile): quelli con showModal() lo fanno gia' da soli.
 *
 * Il pannello puo' essere fatto di piu' contenitori (il menu mobile: la barra
 * col pulsante di chiusura e, fratello, l'elenco delle voci): l'ordine del Tab
 * e' quello dell'elenco dei contenitori.
 *
 * @param {import('vue').Ref<HTMLElement|null>|Array<import('vue').Ref<HTMLElement|null>>} contenitori
 * @param {import('vue').Ref<boolean>} aperto
 * @param {{ iniziale?: import('vue').Ref<HTMLElement|null> }} [opzioni]
 *        `iniziale`: il contenitore nel cui primo elemento va il focus
 *        all'apertura (predefinito: il primo elemento della trappola).
 */
export function useTrappolaDelFocus(contenitori, aperto, opzioni = {}) {
    let daRipristinare = null;

    const radici = () => (Array.isArray(contenitori) ? contenitori : [contenitori])
        .map((r) => unref(r))
        .filter(Boolean);

    const focalizzabiliIn = (radice) => [...radice.querySelectorAll(FOCALIZZABILI)]
        .filter((el) => visibile(el) || el === document.activeElement);

    const elementi = () => radici().flatMap(focalizzabiliIn);

    const dentro = (el) => radici().some((r) => r.contains(el));

    const suTab = (evento) => {
        if (evento.key !== 'Tab' || !aperto.value || pila[pila.length - 1] !== suTab) return;

        const lista = elementi();
        if (lista.length === 0) {
            evento.preventDefault();
            return;
        }

        const primo = lista[0];
        const ultimo = lista[lista.length - 1];
        const attivo = document.activeElement;

        if (!dentro(attivo)) {
            evento.preventDefault();
            (evento.shiftKey ? ultimo : primo).focus();
        } else if (evento.shiftKey && attivo === primo) {
            evento.preventDefault();
            ultimo.focus();
        } else if (!evento.shiftKey && attivo === ultimo) {
            evento.preventDefault();
            primo.focus();
        } else {
            // Contenitori fratelli: il Tab naturale andrebbe al contenitore
            // successivo nel DOM, non per forza al successivo dell'elenco.
            const i = lista.indexOf(attivo);
            if (i === -1) return;
            evento.preventDefault();
            lista[i + (evento.shiftKey ? -1 : 1)].focus();
        }
    };

    const chiudi = () => {
        const i = pila.indexOf(suTab);
        if (i !== -1) pila.splice(i, 1);
        document.removeEventListener('keydown', suTab);
    };

    watch(aperto, async (valore) => {
        if (valore) {
            daRipristinare = document.activeElement;
            pila.push(suTab);
            document.addEventListener('keydown', suTab);
            await nextTick();
            const partenza = unref(opzioni.iniziale);
            const bersaglio = (partenza ? focalizzabiliIn(partenza)[0] : null) ?? elementi()[0];
            bersaglio?.focus();
        } else {
            chiudi();
            // Solo se e' ancora nella pagina e visibile: un pulsante nascosto
            // nel frattempo (header ridisegnato) prenderebbe un focus invisibile.
            if (daRipristinare && document.contains(daRipristinare) && visibile(daRipristinare)) {
                daRipristinare.focus();
            }
            daRipristinare = null;
        }
    });

    onUnmounted(chiudi);
}
