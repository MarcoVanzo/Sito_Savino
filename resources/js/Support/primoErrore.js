/**
 * Dopo un invio rifiutato, porta il focus sul primo campo in errore (WCAG
 * 3.3.1): chi usa la tastiera o uno screen reader altrimenti resta sul
 * pulsante di invio senza sapere che cosa non va, e in un modulo lungo il
 * messaggio puo' essere fuori dallo schermo.
 *
 * I campi in errore si riconoscono da `aria-invalid="true"`, che i moduli
 * impostano insieme ad `aria-describedby` verso il messaggio. Il ritardo lascia
 * il tempo al modulo di ridisegnarsi (il checkout, per esempio, torna al primo
 * passo se l'errore e' li').
 */
export function vaiAlPrimoErrore() {
    window.setTimeout(() => {
        const campo = document.querySelector('[aria-invalid="true"]');

        if (campo) {
            campo.focus();
            campo.scrollIntoView?.({ block: 'center', behavior: 'smooth' });
        }
    }, 60);
}
