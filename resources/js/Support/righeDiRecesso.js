/*
 * Le righe d'ordine nella funzione di recesso online (`/recesso`).
 *
 * La lista arriva dal server solo a chi può vedere l'ordine (account o token
 * dell'ordine). Le righe personalizzate — la firma della giocatrice — sono
 * escluse dal recesso (art. 59 c. 1 lett. c del Codice del consumo): non si
 * possono scegliere qui, e `RecessoController::store` le rifiuta comunque.
 */

/**
 * Gli id delle righe che si possono restituire: la scelta di partenza,
 * perché di solito si recede da tutto quello che si può.
 */
export function righeSelezionabili(righe) {
    if (!Array.isArray(righe)) {
        return [];
    }

    return righe.filter((riga) => !riga.personalizzata).map((riga) => riga.id);
}

/**
 * La lista vale per l'ordine con cui la pagina è stata aperta: se chi compila
 * cambia il numero, si torna alla descrizione a testo.
 */
export function mostraLaLista(righe, numeroDiPartenza, numeroAttuale) {
    return Array.isArray(righe)
        && righe.length > 0
        && String(numeroAttuale ?? '').trim() === String(numeroDiPartenza ?? '').trim();
}

/**
 * Le descrizioni delle righe scelte, per il riepilogo prima della conferma.
 * Una riga personalizzata non entra mai, anche se il suo id fosse fra gli
 * scelti.
 */
export function descrizioniScelte(righe, idScelti) {
    if (!Array.isArray(righe) || !Array.isArray(idScelti)) {
        return [];
    }

    const scelti = new Set(idScelti);

    return righe
        .filter((riga) => scelti.has(riga.id) && !riga.personalizzata)
        .map((riga) => riga.descrizione);
}

/**
 * I dati che partono verso il server: senza lista, niente righe né token,
 * così il server tratta la dichiarazione come testo libero.
 */
export function datiDaInviare(dati, conLista) {
    if (conLista) {
        return { ...dati, articoli: '', righe: Array.isArray(dati.righe) ? [...dati.righe] : [] };
    }

    return { ...dati, righe: null, token: null };
}

/**
 * Il valore di `aria-describedby`: l'errore, se c'è, e il testo d'aiuto
 * insieme, così chi usa un lettore di schermo sente entrambi.
 */
export function descrittoDa(...id) {
    return id.filter(Boolean).join(' ') || undefined;
}
