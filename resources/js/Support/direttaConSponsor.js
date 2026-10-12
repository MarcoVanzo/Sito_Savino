/**
 * La logica della diretta con sponsor, fuori dal componente.
 *
 * Il vincolo che regge tutto: niente sopra il player di YouTube (le sue
 * Developer Policies vietano di coprirlo o modificarlo). Il pre-roll è un
 * player nostro che viene PRIMA dell'iframe; durante la gara la cornice sta
 * ACCANTO: il player si riduce al 75% in alto a sinistra, lo sponsor occupa
 * la colonna a destra e la fascia sotto. Le misure sono in percentuale del
 * riquadro 16:9, così valgono a ogni dimensione, e `disposizione()` le
 * calcola in un posto solo: il test verifica che nessun pezzo dello sponsor
 * tocchi il rettangolo del player.
 */

/** Sotto questa larghezza (px) il telefono: player intero, fascia sotto. */
export const SOGLIA_TELEFONO = 768;

/** Il player non scende mai sotto questa larghezza (px) quando si riduce. */
export const PLAYER_MINIMO = 480;

/** Quanto del riquadro tiene il player in modalità sponsor. */
export const QUOTA_PLAYER = 0.75;

const CHIAVE_PREROLL_VISTO = 'diretta-preroll-visto:';

/** Il giorno del visitatore (non di Greenwich), come `YYYY-MM-DD`. */
export function oggiLocale(data = new Date()) {
    const due = (n) => String(n).padStart(2, '0');

    return `${data.getFullYear()}-${due(data.getMonth() + 1)}-${due(data.getDate())}`;
}

/**
 * Le creatività valide oggi. La pagina può restare in cache oltre la
 * mezzanotte: le date di validità le controlla anche il browser.
 */
export function creativitaValide(lista, oggi = oggiLocale()) {
    return (Array.isArray(lista) ? lista : []).filter((c) => c?.url
        && (!c.dal || c.dal <= oggi)
        && (!c.al || c.al >= oggi));
}

/** Uno spot per visione, a rotazione casuale fra quelli validi. */
export function sceltaDelPreroll(preroll, oggi = oggiLocale(), casuale = Math.random) {
    const valide = creativitaValide(preroll?.creativita, oggi);

    if (valide.length === 0) {
        return null;
    }

    return valide[Math.min(valide.length - 1, Math.floor(casuale() * valide.length))];
}

/**
 * Chi ha già visto il pre-roll di questa gara nella stessa scheda non lo
 * rivede riaprendo la diretta. `sessionStorage` (si svuota chiudendo la
 * scheda, nessun identificativo): senza, meglio rimostrarlo che bloccare.
 */
export function prerollGiaVisto(gara) {
    try {
        return sessionStorage.getItem(CHIAVE_PREROLL_VISTO + gara) === '1';
    } catch {
        return false;
    }
}

export function segnaPrerollVisto(gara) {
    try {
        sessionStorage.setItem(CHIAVE_PREROLL_VISTO + gara, '1');
    } catch {
        // Archivio non disponibile (navigazione privata): pazienza.
    }
}

/**
 * L'indirizzo del player senza il suo tutto schermo (`fs=0`, parametro
 * ufficiale di YouTube): a tutto schermo col pulsante di YouTube la cornice
 * sparirebbe. Il sito offre il proprio, che porta con sé lo sponsor.
 */
export function indirizzoSenzaTuttoSchermo(src) {
    try {
        const url = new URL(src);

        if (/(^|\.)youtube(-nocookie)?\.com$/.test(url.hostname)) {
            url.searchParams.set('fs', '0');
        }

        return url.toString();
    } catch {
        return src;
    }
}

/**
 * Come si dispone il riquadro: `pieno` (solo diretta), `cornice` (player
 * ridotto, sponsor a destra e sotto) o `telefono` (player intero, fascia
 * sotto il riquadro). Sotto la soglia, o se ridotto il player scenderebbe
 * sotto i 480 px, si passa al telefono: il player non si rimpicciolisce.
 */
export function modoDellaDisposizione({ larghezza, conSponsor }) {
    if (!conSponsor) {
        return 'pieno';
    }

    if (larghezza < SOGLIA_TELEFONO || larghezza * QUOTA_PLAYER < PLAYER_MINIMO) {
        return 'telefono';
    }

    return 'cornice';
}

/**
 * I rettangoli in percentuale del riquadro 16:9 (x, y, larghezza, altezza).
 * Nel modo telefono la fascia sta sotto il riquadro: `y` parte da 100.
 */
export function disposizione(modo) {
    const tutto = { x: 0, y: 0, w: 100, h: 100 };

    if (modo === 'cornice') {
        const q = QUOTA_PLAYER * 100;

        return {
            player: { x: 0, y: 0, w: q, h: q },
            colonna: { x: q, y: 0, w: 100 - q, h: q },
            fascia: { x: 0, y: q, w: 100, h: 100 - q },
        };
    }

    if (modo === 'telefono') {
        return { player: tutto, colonna: null, fascia: { x: 0, y: 100, w: 100, h: 0 } };
    }

    return { player: tutto, colonna: null, fascia: null };
}

/** Vero se i due rettangoli hanno un'area in comune (i bordi che si toccano no). */
export function sovrapposti(a, b) {
    if (!a || !b) {
        return false;
    }

    return a.x < b.x + b.w && b.x < a.x + a.w && a.y < b.y + b.h && b.y < a.y + a.h;
}

/** Il rettangolo come stile CSS assoluto in percentuale. */
export function stileDelRettangolo(r) {
    return r ? { left: `${r.x}%`, top: `${r.y}%`, width: `${r.w}%`, height: `${r.h}%` } : {};
}

/**
 * Lo schermo intero vero (Fullscreen API) c'è quasi ovunque, non sugli
 * elementi di Safari per iPhone: lì si simula con un contenitore fisso.
 */
export function tuttoSchermoDisponibile(elemento, doc = globalThis.document) {
    return Boolean(doc?.fullscreenEnabled && typeof elemento?.requestFullscreen === 'function');
}
