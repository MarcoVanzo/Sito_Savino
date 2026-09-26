/**
 * Il cambio di pagina in una SPA, reso percepibile a chi usa lo screen reader
 * o la tastiera (WCAG 2.4.3 e 4.1.3).
 *
 * Con Inertia la pagina cambia senza ricaricarsi: il focus resta sul link
 * appena premuto, in un menu che magari non c'e' piu', e lo screen reader non
 * dice niente. Qui, a ogni navigazione verso un indirizzo diverso, il focus va
 * al titolo della pagina nuova: il primo <h1> dentro `#contenuto` (il <main>),
 * reso focalizzabile al volo con `tabindex="-1"`. Lo screen reader legge il
 * titolo da se', perche' e' l'elemento che riceve il focus.
 *
 * Una sola voce, non due. Prima il focus andava sul <main> e in piu' una
 * regione `aria-live` leggeva `document.title`: NVDA e JAWS annunciavano sia
 * l'elemento focalizzato sia la regione, cioe' la stessa pagina due volte.
 * Ora la regione parla solo quando un <h1> non c'e' (o non e' visibile, e
 * quindi non prende il focus): allora il focus ripiega sul <main>, che da
 * solo non dice di quale pagina si tratta, e il titolo lo legge la regione.
 *
 * Solo quando cambia il percorso: filtri, paginazione con `preserveScroll`,
 * invii di moduli restano dove sono, o il focus salterebbe via mentre si
 * compila.
 */
export function annunciaIlCambioDiPagina(router, documento = document) {
    let percorsoPrecedente = documento.location?.pathname ?? null;

    const regione = documento.createElement('div');
    regione.setAttribute('aria-live', 'polite');
    regione.setAttribute('aria-atomic', 'true');
    regione.className = 'sr-only';
    regione.dataset.annuncioPagina = '';
    documento.body.appendChild(regione);

    return router.on('navigate', (evento) => {
        const url = evento?.detail?.page?.url ?? documento.location?.pathname ?? '';
        const percorso = url.split('?')[0].split('#')[0];

        if (percorso === percorsoPrecedente) {
            return;
        }

        percorsoPrecedente = percorso;

        // Svuotata subito e riscritta dopo: due pagine con lo stesso titolo
        // lascerebbero il testo identico, e una regione che non cambia non
        // viene riletta.
        regione.textContent = '';

        // Il titolo lo aggiorna <Head> un attimo dopo il cambio del componente.
        window.setTimeout(() => {
            if (portaIlFocusSulTitolo(documento)) {
                return;
            }

            const contenuto = documento.getElementById('contenuto');
            contenuto?.focus({ preventScroll: true });
            regione.textContent = documento.title;
        }, 50);
    });
}

/**
 * Mette il focus sul primo <h1> del contenuto. Restituisce `false` se non
 * c'e' o se non l'ha preso (un titolo nascosto con `display: none` non e'
 * focalizzabile): chi chiama ripiega sul <main>.
 */
function portaIlFocusSulTitolo(documento) {
    const titolo = documento.querySelector('#contenuto h1');

    if (!titolo) {
        return false;
    }

    if (!titolo.hasAttribute('tabindex')) {
        titolo.setAttribute('tabindex', '-1');
        // Come il <main>: un titolo non e' un comando, l'anello del focus
        // attorno a una scritta confonde piu' di quanto aiuti.
        titolo.classList.add('focus:outline-none');
    }

    titolo.focus({ preventScroll: true });

    return documento.activeElement === titolo;
}
