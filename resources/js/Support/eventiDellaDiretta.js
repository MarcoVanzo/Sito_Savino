/**
 * Manda un contatore del report sponsor (pre-roll visti, saltati, cornici,
 * clic) senza rallentare nulla: `sendBeacon` parte anche se la finestra si
 * chiude, e se non c'è si ripiega su un `fetch` che sopravvive alla pagina.
 * Solo l'evento e la creatività: nessun dato del visitatore.
 */
/**
 * Gli eventi che tutti gli spettatori vivono nello stesso istante (il ciclo
 * è sincronizzato, lo spot della regia anche): mandati subito sarebbero mille
 * richieste nello stesso secondo. Partono con un ritardo casuale entro questa
 * finestra; se la pagina si chiude prima, il contatore si perde (pazienza).
 */
export const EVENTI_SINCRONIZZATI = ['ciclo_mostrato', 'pausa_mostrata'];

export const FINESTRA_DI_SPARPAGLIAMENTO = 60_000;

export function registraEvento(url, dati, casuale = Math.random) {
    if (EVENTI_SINCRONIZZATI.includes(dati?.evento) && url && dati.creativita != null) {
        setTimeout(() => invia(url, dati), Math.floor(casuale() * FINESTRA_DI_SPARPAGLIAMENTO));

        return true;
    }

    return invia(url, dati);
}

function invia(url, { evento, creativita } = {}) {
    if (!url || !evento || creativita == null) {
        return false;
    }

    const corpo = JSON.stringify({ evento, creativita });

    try {
        if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
            return navigator.sendBeacon(url, new Blob([corpo], { type: 'application/json' }));
        }

        fetch(url, { method: 'POST', body: corpo, headers: { 'Content-Type': 'application/json' }, keepalive: true, credentials: 'omit' }).catch(() => {});

        return true;
    } catch {
        // Un contatore perso non deve mai disturbare la diretta.
        return false;
    }
}
