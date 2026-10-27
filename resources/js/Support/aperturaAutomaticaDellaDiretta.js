/**
 * La diretta che la home apre da sola a gara in corso (MatchDay::apriDiretta).
 *
 * Parte senza audio: i browser fanno partire da soli solo i video muti, e con
 * l'audio YouTube resterebbe fermo sul pulsante di play. Si apre una volta per
 * scheda e per gara: chi la chiude e torna in home non se la ritrova davanti.
 */
import { EVENTO_CAMBIO, leggiIlConsenso, marketingConsentito } from '@/consenso.js';

export const CHIAVE_APERTA = 'diretta-aperta-da-sola';

/** Con la sola scelta già fatta si aspetta poco, che la pagina si sistemi. */
export const ATTESA_CON_LA_SCELTA_FATTA = 1000;

/** Dopo il banner, il tempo di vederlo chiudersi. */
export const ATTESA_DOPO_IL_BANNER = 800;

const archivioDellaScheda = () => {
    try {
        return window.sessionStorage;
    } catch {
        return null;
    }
};

export function giaAperta(garaId, archivio = archivioDellaScheda()) {
    try {
        return archivio?.getItem(CHIAVE_APERTA) === String(garaId);
    } catch {
        // Senza memoria della scheda non si sa: meglio non aprirla a ogni visita.
        return true;
    }
}

export function ricordaAperta(garaId, archivio = archivioDellaScheda()) {
    try {
        archivio?.setItem(CHIAVE_APERTA, String(garaId));
    } catch {
        // Niente da fare: giaAperta() dirà di sì e non si riaprirà.
    }
}

/** L'indirizzo del player con l'audio spento (YouTube: `mute=1`). */
export function conAudioSpento(indirizzo) {
    try {
        const url = new URL(indirizzo);
        url.searchParams.set('mute', '1');

        return url.toString();
    } catch {
        return indirizzo;
    }
}

/**
 * Aspetta che il visitatore abbia risposto al banner dei cookie (la finestra
 * della diretta lo renderebbe inerte), poi chiama `decidi(puoPartire)`.
 * Non può partire senza consenso di marketing (YouTube non si carica senza un
 * clic) né per chi ha chiesto al sistema meno movimento (un video che parte
 * da solo è proprio quello: gli spot della diretta fanno lo stesso). In quei
 * casi la home mostra il pop-up di sempre. Restituisce la funzione che smette
 * di aspettare.
 */
export function aspettaLaScelta({ versione, decidi, finestra = window }) {
    let attesa = null;

    const movimentoRidotto = () => finestra.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

    const decidiTra = (millisecondi) => {
        attesa = setTimeout(() => decidi(marketingConsentito(versione) && !movimentoRidotto()), millisecondi);
    };

    const dopoIlBanner = () => {
        finestra.removeEventListener(EVENTO_CAMBIO, dopoIlBanner);
        decidiTra(ATTESA_DOPO_IL_BANNER);
    };

    if (leggiIlConsenso(versione).scelto) {
        decidiTra(ATTESA_CON_LA_SCELTA_FATTA);
    } else {
        finestra.addEventListener(EVENTO_CAMBIO, dopoIlBanner);
    }

    return () => {
        clearTimeout(attesa);
        finestra.removeEventListener(EVENTO_CAMBIO, dopoIlBanner);
    };
}
