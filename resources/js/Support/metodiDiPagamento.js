/**
 * Il metodo di pagamento da proporre gia' selezionato.
 *
 * Il server manda solo i metodi davvero utilizzabili (credenziali presenti e
 * attivi dal pannello). Quando ne resta uno solo non c'e' niente da scegliere:
 * lo si seleziona, cosi' il vincitore di un'asta non si ferma su una domanda
 * con una risposta sola. Con piu' metodi, o nessuno, la scelta resta vuota.
 *
 * @param {Array<{value: string}>|null|undefined} metodi
 * @returns {string}
 */
export function metodoPredefinito(metodi) {
    const validi = (Array.isArray(metodi) ? metodi : [])
        .filter((metodo) => typeof metodo?.value === 'string' && metodo.value !== '');

    return validi.length === 1 ? validi[0].value : '';
}
