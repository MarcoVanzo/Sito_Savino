/**
 * Il cambio di pagina in una SPA, reso percepibile a chi usa lo screen reader
 * o la tastiera (WCAG 2.4.3 e 4.1.3).
 *
 * Con Inertia la pagina cambia senza ricaricarsi: il focus resta sul link
 * appena premuto, in un menu che magari non c'e' piu', e lo screen reader non
 * dice niente. Qui, a ogni navigazione verso un indirizzo diverso, il focus va
 * all'inizio del contenuto (`#contenuto`, il <main>) e una regione `aria-live`
 * legge il titolo della pagina nuova.
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
            const contenuto = documento.getElementById('contenuto');
            contenuto?.focus({ preventScroll: true });
            regione.textContent = documento.title;
        }, 50);
    });
}
