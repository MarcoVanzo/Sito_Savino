/**
 * Quale sponsor sta accanto alla diretta in questo istante, dal JSON di stato
 * (App\Services\Diretta\StatoPubblicoDellaDiretta). Stesse regole di
 * App\Support\Diretta\CicloDellaCornice.
 *
 * - `inattiva`: nessuno;
 * - `pausa` non ancora finita: la creatività scelta dalla regia, oppure le
 *   cornici a rotazione contate da `dalle`, ognuna per la sua durata. Il
 *   ciclo automatico intanto tace;
 * - altrimenti il ciclo: il ciclo `k` comincia a `inizio + k × ogni` e dura
 *   `durata_secondi`; nessun ciclo comincia dopo `fine`; la cornice è la
 *   `k`-esima della rotazione. Tutti gli spettatori fanno lo stesso conto
 *   sugli stessi numeri, quindi vedono lo stesso sponsor negli stessi istanti.
 *
 * `adesso` è già corretto con lo scarto dall'orologio del server.
 */
import { creativitaValide, oggiLocale } from '@/Support/direttaConSponsor.js';

const ms = (iso) => (iso ? Date.parse(iso) : NaN);

export function contenutoInOnda(stato, adesso, oggi = oggiLocale(new Date(adesso))) {
    if (!stato || stato.stato === 'inattiva') {
        return null;
    }

    const playlist = creativitaValide(stato.playlist, oggi);
    const fine = ms(stato.fino_alle);

    if (stato.stato === 'pausa' && adesso < fine) {
        if (stato.contenuto) {
            return stato.contenuto;
        }

        return aRotazione(playlist, adesso - ms(stato.dalle));
    }

    return dalCiclo(stato.ciclo, playlist, adesso);
}

/** Le cornici una dopo l'altra, ognuna per la sua durata, da capo alla fine. */
function aRotazione(playlist, trascorsi) {
    if (playlist.length === 0 || !(trascorsi >= 0)) {
        return null;
    }

    const durate = playlist.map((c) => Math.max(1, Number(c.durata) || 10) * 1000);
    const giro = durate.reduce((a, b) => a + b, 0);
    let resto = trascorsi % giro;

    for (let i = 0; i < playlist.length; i++) {
        if (resto < durate[i]) {
            return playlist[i];
        }
        resto -= durate[i];
    }

    return playlist[0];
}

function dalCiclo(ciclo, playlist, adesso) {
    if (!ciclo?.attivo || playlist.length === 0) {
        return null;
    }

    const inizio = ms(ciclo.inizio);
    const fine = ms(ciclo.fine);
    const periodo = Number(ciclo.ogni_minuti) * 60_000;
    const durata = Number(ciclo.durata_secondi) * 1000;

    if (!(periodo > 0) || !(adesso >= inizio)) {
        return null;
    }

    const numero = Math.floor((adesso - inizio) / periodo);
    const comincia = inizio + numero * periodo;

    if (!(comincia < fine) || adesso - comincia >= durata) {
        return null;
    }

    return playlist[numero % playlist.length];
}

/**
 * Lo scarto fra l'orologio del server e quello del dispositivo, da una
 * richiesta all'endpoint dell'ora: si prende il punto di mezzo del viaggio.
 */
export function scartoDellOrologio(oraDelServer, partenza, arrivo) {
    if (!Number.isFinite(oraDelServer)) {
        return 0;
    }

    return oraDelServer - (partenza + arrivo) / 2;
}

/** Il JSON nuovo vale solo se la versione è più alta. */
export function statoPiuRecente(attuale, arrivato) {
    if (!arrivato || typeof arrivato.versione !== 'number') {
        return attuale;
    }

    return !attuale || arrivato.versione > attuale.versione ? arrivato : attuale;
}
