/**
 * Gli eventi del funnel, verso GA4 e il pixel di Meta insieme.
 *
 * I componenti chiamano questo modulo e non i due tag: cosi' un evento nuovo
 * non arriva a uno solo dei due, e il consenso lo controlla ciascun tag con le
 * sue regole (statistiche per GA4, marketing per il pixel). Senza consenso le
 * chiamate non mandano nulla.
 */
import * as ga4 from './analytics.js';
import * as pixel from './meta-pixel.js';
import { leggiIlConsenso } from './consenso.js';

/**
 * Scheda prodotto aperta.
 *
 * @param {{ id: number, name: string, price: number }} prodotto il prezzo che si paga, non il barrato
 */
export function prodottoVisto({ id, name, price }) {
    pixel.trackViewContent({ id, name, value: price });
    ga4.trackViewItem({ id, name, price });
}

/**
 * Prodotto aggiunto al carrello.
 *
 * @param {{ id: number, name: string, price: number, quantity?: number }} riga `price` di un pezzo
 */
export function aggiuntoAlCarrello({ id, name, price, quantity = 1 }) {
    pixel.trackAddToCart({ id, name, value: (Number(price) || 0) * quantity, quantity });
    ga4.trackAddToCart({ id, name, price, quantity });
}

/**
 * Checkout aperto.
 *
 * @param {{ value: number, items: Array<{ id: number, name: string, price: number, quantity: number }> }} carrello
 */
export function checkoutIniziato({ value, items = [] }) {
    pixel.trackInitiateCheckout({
        value,
        numItems: items.reduce((totale, riga) => totale + (Number(riga.quantity) || 0), 0),
    });
    ga4.trackBeginCheckout({ value, items });
}

/**
 * Ordine concluso: una volta sola per ordine anche se la pagina si ricarica.
 *
 * @param {{ orderNumber: string, value: number, items: Array<{ id: number, name: string, price: number, quantity: number }> }} ordine
 */
export function acquistoConcluso({ orderNumber, value, items = [] }) {
    pixel.trackPurchase({ orderNumber, value, items });
    ga4.trackPurchase({ orderNumber, value, items });
}

/**
 * Un modulo inviato con successo: da chiamare nell'`onSuccess`, mai al clic.
 *
 * @param {'contatti'|'newsletter'|'accredito_stampa'} formName
 */
export function moduloInviato(formName) {
    ga4.trackGenerateLead(formName);
    pixel.trackLead(formName);
}

const BIGLIETTERIA = /(^|\.)vivaticket\.(com|it)$/i;

/**
 * L'indirizzo della biglietteria a cui porta un clic, o null.
 *
 * @param {EventTarget|null} bersaglio
 * @returns {string|null}
 */
export function destinazioneVersoLaBiglietteria(bersaglio) {
    const link = bersaglio instanceof Element ? bersaglio.closest('a[href]') : null;

    if (!link) return null;

    try {
        const indirizzo = new URL(link.getAttribute('href'), window.location.href);

        return BIGLIETTERIA.test(indirizzo.hostname) ? indirizzo.href : null;
    } catch {
        return null;
    }
}

/**
 * I link a Vivaticket li scrive la redazione in molti punti (pagina Ticketing,
 * pop-up del Match Day, menu, schede delle gare): un solo ascolto su tutto il
 * documento li prende tutti, anche quelli aggiunti dopo.
 *
 * @returns {() => void} per staccare l'ascolto
 */
export function seguiIClicVersoLaBiglietteria(radice = document) {
    const alClic = (evento) => {
        const destinazione = destinazioneVersoLaBiglietteria(evento.target);

        if (!destinazione) return;

        ga4.trackTicketClick(destinazione);
        pixel.trackTicketClick(destinazione);
    };

    radice.addEventListener('click', alClic, true);

    return () => radice.removeEventListener('click', alClic, true);
}

function cookie(nome) {
    const coppia = document.cookie.split(';').map((voce) => voce.trim()).find((voce) => voce.startsWith(`${nome}=`));

    return coppia ? decodeURIComponent(coppia.slice(nome.length + 1)) : null;
}

/**
 * Cosa viaggia con l'ordine per la Conversions API: il riferimento della
 * scelta sul banner (il server la ricontrolla sul registro dei consensi) e i
 * cookie `_fbp`/`_fbc` del pixel. Senza consenso di marketing valido, niente.
 *
 * @returns {{ consenso_riferimento: string|null, meta_fbp: string|null, meta_fbc: string|null }}
 */
export function consensoPerLOrdine(versioneAttesa = null) {
    const consenso = leggiIlConsenso(versioneAttesa);

    if (!consenso.scelto || !consenso.marketing || !consenso.riferimento) {
        return { consenso_riferimento: null, meta_fbp: null, meta_fbc: null };
    }

    return {
        consenso_riferimento: consenso.riferimento,
        meta_fbp: cookie('_fbp'),
        meta_fbc: cookie('_fbc'),
    };
}
