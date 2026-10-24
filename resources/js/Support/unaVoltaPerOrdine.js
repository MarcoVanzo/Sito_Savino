/**
 * Un evento d'acquisto una volta sola per ordine, nella sessione del browser.
 *
 * La pagina di conferma si ricarica da sola ogni 5 secondi finche' il webhook
 * del pagamento non arriva: senza il segno su sessionStorage lo stesso ordine
 * diventava fino a dodici conversioni. Lo usano il pixel di Meta e GA4, ognuno
 * con la sua chiave: il consenso dell'uno non deve consumare l'evento
 * dell'altro.
 *
 * L'ordine si segna solo se l'evento e' partito davvero (`invia` torna vero):
 * senza consenso non parte nulla, e segnarlo lo stesso impedirebbe di
 * contarlo se il cliente accetta i cookie sulla pagina di conferma.
 *
 * @param {string} chiave
 * @param {() => boolean} invia
 */
export function unaVoltaPerOrdine(chiave, invia) {
    try {
        if (sessionStorage.getItem(chiave)) return;
    } catch {
        // Storage non disponibile (navigazione privata, storage pieno): meglio
        // un evento in piu' che una conversione persa.
    }

    if (!invia()) return;

    try {
        sessionStorage.setItem(chiave, '1');
    } catch {
        // Come sopra: senza storage si rinuncia alla deduplica.
    }
}
