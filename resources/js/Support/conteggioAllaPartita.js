/**
 * Quanto manca a una partita, per il conto alla rovescia della homepage.
 *
 * Una gara di pallavolo dura al massimo un paio d'ore e mezza: per tre ore
 * dall'inizio si dice che è in corso, dopo non si dice niente. Il risultato lo
 * scrive la sincronizzazione con la Lega, non l'orologio del visitatore.
 */
export const DURATA_IN_CORSO_MS = 3 * 60 * 60 * 1000;

const MINUTO = 60 * 1000;
const ORA = 60 * MINUTO;
const GIORNO = 24 * ORA;

/**
 * @param {string|null|undefined} dataIso inizio della gara (ISO 8601 con fuso)
 * @param {number} adesso millisecondi
 * @returns {{stato: 'prima', giorni: number, ore: number, minuti: number}|{stato: 'in_corso'}|{stato: 'nessuno'}}
 */
export function tempoMancante(dataIso, adesso = Date.now()) {
    const inizio = dataIso ? new Date(dataIso).getTime() : Number.NaN;

    if (Number.isNaN(inizio)) {
        return { stato: 'nessuno' };
    }

    const differenza = inizio - adesso;

    if (differenza <= 0) {
        return -differenza < DURATA_IN_CORSO_MS ? { stato: 'in_corso' } : { stato: 'nessuno' };
    }

    // Per eccesso sui minuti: a 30 secondi dall'inizio si legge «1 minuto»,
    // non «0 giorni, 0 ore, 0 minuti» con la gara che non è ancora partita.
    const minutiTotali = Math.ceil(differenza / MINUTO);

    return {
        stato: 'prima',
        giorni: Math.floor(minutiTotali / (GIORNO / MINUTO)),
        ore: Math.floor((minutiTotali % (GIORNO / MINUTO)) / 60),
        minuti: minutiTotali % 60,
    };
}
