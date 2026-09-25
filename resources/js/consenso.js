/**
 * La scelta fatta sul banner dei cookie: dove si legge, dove si scrive, e come
 * arriva al registro sul server.
 *
 * Sta in un modulo solo perché la leggono in due — `app.js`, che all'avvio
 * decide se far partire GA4 e il Pixel, e il banner, che la raccoglie — e due
 * copie della stessa regola prima o poi divergono: era già successo con il
 * calcolo della spedizione.
 */

export const CHIAVE_CONSENSO = 'cookie-consent-v2';

/**
 * La registrazione sul server che non è andata a buon fine, in attesa del
 * prossimo caricamento: senza, una connessione ballerina nel momento della
 * scelta lascerebbe un consenso senza prova.
 */
export const CHIAVE_IN_ATTESA = 'cookie-consent-da-registrare';

/**
 * Quanto vale una scelta, in giorni: dodici mesi, poi il banner torna a
 * chiedere.
 *
 * È ciò che la Cookie Policy dichiara («La scelta vale 12 mesi»), ma fino al
 * 25 settembre 2026 nessuno lo applicava: un sì dato una volta valeva per
 * sempre. Vale anche per il rifiuto, e resta sopra i sei mesi che le linee
 * guida del Garante sui cookie (10 giugno 2021) indicano come il tempo minimo
 * prima di riproporre il banner. Il registro sul server conserva la prova per
 * lo stesso periodo (`consensi:pota`).
 */
export const DURATA_GIORNI = 365;

const GIORNO_IN_MS = 24 * 60 * 60 * 1000;

/**
 * Una scelta senza data (salvata prima che la si registrasse) vale come
 * scaduta: non si può dire da quanto dura, e chiederla di nuovo costa un click.
 */
export function eScaduta(data, adesso = Date.now()) {
    const quando = data ? new Date(data).getTime() : Number.NaN;

    if (Number.isNaN(quando)) {
        return true;
    }

    return adesso - quando > DURATA_GIORNI * GIORNO_IN_MS;
}

/**
 * Legge la scelta salvata nel browser.
 *
 * Con `versioneAttesa` valorizzata, una scelta fatta su un'informativa
 * precedente vale come nessuna scelta: il consenso si riferisce a ciò che era
 * scritto quel giorno, e se abbiamo aggiunto un tracker va chiesto di nuovo.
 *
 * Torna sempre un oggetto: `{ scelto: false }` quando non c'è niente di valido,
 * così chi la usa non deve distinguere fra assente, illeggibile e scaduta.
 */
export function leggiIlConsenso(versioneAttesa = null) {
    let grezzo;

    try {
        grezzo = localStorage.getItem(CHIAVE_CONSENSO);
    } catch {
        // Navigazione privata, cookie di terze parti bloccati, spazio esaurito:
        // in tutti questi casi il consenso non è recuperabile e si richiede.
        return { scelto: false, statistiche: false, marketing: false };
    }

    if (! grezzo) {
        return { scelto: false, statistiche: false, marketing: false };
    }

    let salvato;

    try {
        salvato = JSON.parse(grezzo);
    } catch {
        return { scelto: false, statistiche: false, marketing: false };
    }

    // `analytics` è il nome che la chiave aveva prima del registro: le scelte
    // già salvate nei browser dei visitatori si continuano a leggere.
    const statistiche = salvato.statistiche ?? salvato.analytics === true;
    const marketing = salvato.marketing === true;

    // Una scelta superata (informativa cambiata) o scaduta non autorizza
    // niente — `scelto` è falso — ma le caselle tornano come il visitatore le
    // aveva lasciate, così il banner si riapre già compilato. Chi decide se
    // far partire un tag guarda quindi `scelto && statistiche`, mai la sola
    // casella: `app.js` guardava la casella, e dopo un cambio d'informativa
    // faceva partire GA4 prima che il banner tornasse a chiedere.
    if (versioneAttesa && salvato.versione !== versioneAttesa) {
        return { scelto: false, statistiche: statistiche === true, marketing, versioneSuperata: true, riferimento: salvato.riferimento ?? null };
    }

    if (eScaduta(salvato.data ?? salvato.timestamp ?? null)) {
        return { scelto: false, statistiche: statistiche === true, marketing, scaduta: true, riferimento: salvato.riferimento ?? null };
    }

    return {
        scelto: true,
        statistiche: statistiche === true,
        marketing,
        versione: salvato.versione ?? null,
        riferimento: salvato.riferimento ?? null,
        data: salvato.data ?? salvato.timestamp ?? null,
    };
}

export function salvaIlConsenso({ statistiche, marketing, versione, riferimento = null, data = null }) {
    const valore = {
        necessary: true,
        statistiche: statistiche === true,
        marketing: marketing === true,
        // Il nome vecchio resta scritto: se un domani si torna indietro con una
        // versione del sito precedente, il consenso non si perde.
        analytics: statistiche === true,
        versione,
        riferimento,
        data: data ?? new Date().toISOString(),
    };

    try {
        localStorage.setItem(CHIAVE_CONSENSO, JSON.stringify(valore));
    } catch {
        // Se il browser non ce lo fa scrivere, il banner tornerà a chiedere:
        // è il comportamento giusto, non un errore da mostrare.
    }

    return valore;
}

/**
 * Manda la scelta al registro sul server.
 *
 * Non decide niente di ciò che il sito carica — quello lo fa già il valore
 * salvato nel browser — quindi un errore qui non si mostra al visitatore: si
 * mette la chiamata in attesa e si riprova al caricamento successivo.
 */
export async function registraIlConsenso({ statistiche, marketing, riferimento = null }, indirizzo) {
    const corpo = {
        statistiche: statistiche === true,
        marketing: marketing === true,
        riferimento: riferimento || null,
    };

    try {
        const risposta = await window.axios.post(indirizzo, corpo);

        smettiDiAspettare();

        return risposta?.data ?? null;
    } catch {
        mettiInAttesa(corpo);

        return null;
    }
}

export function mettiInAttesa(corpo) {
    try {
        localStorage.setItem(CHIAVE_IN_ATTESA, JSON.stringify(corpo));
    } catch {
        // vedi sopra
    }
}

export function smettiDiAspettare() {
    try {
        localStorage.removeItem(CHIAVE_IN_ATTESA);
    } catch {
        // vedi sopra
    }
}

export function consensoInAttesa() {
    try {
        const grezzo = localStorage.getItem(CHIAVE_IN_ATTESA);

        return grezzo ? JSON.parse(grezzo) : null;
    } catch {
        return null;
    }
}
