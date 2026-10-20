/**
 * Caricamento di Google Analytics 4, subordinato al consenso.
 *
 * Due vincoli guidano questo file:
 *
 * 1. Il tag non si carica finché l'utente non accetta i cookie di statistica.
 *    Si dichiara comunque il Consent Mode v2 con tutto negato, così se in
 *    futuro si attivassero i segnali anonimi il comportamento è già corretto.
 *
 * 2. Il sito è una SPA Inertia: dopo il primo caricamento il browser non
 *    ricarica più la pagina, e senza un `page_view` a ogni navigazione GA4
 *    attribuirebbe tutto il traffico alla pagina d'ingresso. È esattamente la
 *    misura "pagina per pagina" che serve al pannello.
 */

import { unaVoltaPerOrdine } from './Support/unaVoltaPerOrdine.js';

let measurementId = null;
let scriptLoaded = false;
let consentGranted = false;

// Deve accodare l'oggetto `arguments`, non un array: gtag.js distingue i due
// casi e un array verrebbe interpretato come un messaggio dataLayer qualunque
// invece che come un comando gtag. È il motivo per cui lo snippet ufficiale di
// Google è scritto così, e non è una svista da modernizzare.
function gtag() {
    window.dataLayer.push(arguments);
}

function ensureDataLayer() {
    window.dataLayer = window.dataLayer || [];
    window.gtag = window.gtag || gtag;
}

function loadScript() {
    if (scriptLoaded || !measurementId) return;

    scriptLoaded = true;

    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
    document.head.appendChild(script);

    gtag('js', new Date());
    // `send_page_view: false`: la prima visualizzazione la inviamo noi insieme a
    // tutte le altre, altrimenti la pagina d'ingresso verrebbe contata due volte.
    gtag('config', measurementId, { send_page_view: false });

    trackPageView();
}

/**
 * Prepara la misurazione. Il tag parte solo se il consenso c'è già.
 */
export function initAnalytics(id, hasConsent) {
    if (!id) return;

    measurementId = id;
    ensureDataLayer();

    gtag('consent', 'default', {
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
        analytics_storage: 'denied',
    });

    if (hasConsent) updateAnalyticsConsent(true);
}

/**
 * Da chiamare quando l'utente accetta o revoca i cookie di statistica.
 *
 * La revoca cancella anche `_ga` e `_ga_<id>` gia' scritti, come il pixel fa
 * con `_fbp`: `analytics_storage: denied` impedisce a gtag di scriverne di
 * nuovi, ma quelli che ci sono resterebbero per due anni.
 */
export function updateAnalyticsConsent(granted) {
    if (!granted) cancellaICookieDiAnalytics();

    if (!measurementId) return;

    ensureDataLayer();
    consentGranted = Boolean(granted);
    gtag('consent', 'update', { analytics_storage: consentGranted ? 'granted' : 'denied' });

    if (consentGranted) loadScript();
}

/**
 * Una visualizzazione di pagina. Senza titolo e percorso espliciti GA4
 * leggerebbe quelli del documento, che durante una navigazione Inertia sono
 * ancora quelli della pagina precedente.
 */
export function trackPageView() {
    // Il tag caricato non si può togliere, ma dopo una revoca smette di ricevere
    // visualizzazioni: continuare a mandarle vorrebbe dire misurare comunque
    // chi ha detto di no.
    if (!measurementId || !scriptLoaded || !consentGranted) return;

    gtag('event', 'page_view', {
        page_location: window.location.href,
        page_path: window.location.pathname + window.location.search,
        page_title: document.title,
    });
}

/**
 * Un evento GA4, solo con il tag caricato e il consenso dato: le stesse
 * condizioni di `trackPageView`.
 *
 * @returns {boolean} se l'evento e' partito davvero
 */
function track(evento, parametri) {
    if (!measurementId || !scriptLoaded || !consentGranted) return false;

    gtag('event', evento, parametri);

    return true;
}

/**
 * Le righe nello schema e-commerce di GA4. `price` e' il prezzo unitario
 * pagato (sconto, taglia e firma compresi), non il barrato.
 *
 * @param {Array<{ id: number|string, name?: string, price?: number, quantity?: number }>} righe
 */
function articoli(righe) {
    return righe.map((riga) => ({
        item_id: String(riga.id),
        item_name: riga.name ?? '',
        price: Number(riga.price) || 0,
        quantity: Number(riga.quantity) || 1,
    }));
}

function valoreDi(righe) {
    return Math.round(righe.reduce((totale, riga) => totale + (Number(riga.price) || 0) * (Number(riga.quantity) || 1), 0) * 100) / 100;
}

/** Scheda prodotto aperta. */
export function trackViewItem({ id, name, price, currency = 'EUR' }) {
    const righe = [{ id, name, price, quantity: 1 }];

    track('view_item', { currency, value: valoreDi(righe), items: articoli(righe) });
}

/** Prodotto aggiunto al carrello; `price` e' quello di un pezzo. */
export function trackAddToCart({ id, name, price, quantity = 1, currency = 'EUR' }) {
    const righe = [{ id, name, price, quantity }];

    track('add_to_cart', { currency, value: valoreDi(righe), items: articoli(righe) });
}

/** Checkout aperto: `value` e' il totale del carrello (coupon e spedizione esclusi). */
export function trackBeginCheckout({ value, items = [], currency = 'EUR' }) {
    track('begin_checkout', { currency, value: Number(value) || 0, items: articoli(items) });
}

/**
 * Ordine concluso, una volta sola per numero d'ordine come il `Purchase` del
 * pixel (`unaVoltaPerOrdine`, chiave propria).
 */
export function trackPurchase({ orderNumber, value, items = [], currency = 'EUR' }) {
    if (!measurementId || !orderNumber) return;

    unaVoltaPerOrdine(`ga4-purchase-${orderNumber}`, () => track('purchase', {
        transaction_id: String(orderNumber),
        currency,
        value: Number(value) || 0,
        items: articoli(items),
    }));
}

/**
 * GA4 scrive i suoi cookie sul dominio piu' alto che il browser gli concede
 * (`.savinodelbenevolley.it` anche visitando `www.`), con o senza il punto:
 * si cancellano sul nome dell'host e su ciascuno dei domini che lo contengono.
 */
function cancellaICookieDiAnalytics() {
    if (typeof document === 'undefined') return;

    const nomi = document.cookie
        .split(';')
        .map((coppia) => coppia.split('=')[0].trim())
        .filter((nome) => nome === '_ga' || nome.startsWith('_ga_'));

    if (nomi.length === 0) return;

    const parti = window.location.hostname.split('.');
    const domini = parti.map((_, i) => parti.slice(i).join('.')).filter((d) => d.includes('.'));
    const scadenza = 'expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';

    for (const nome of nomi) {
        document.cookie = `${nome}=; ${scadenza}`;
        for (const dominio of domini) {
            document.cookie = `${nome}=; ${scadenza}; domain=${dominio}`;
            document.cookie = `${nome}=; ${scadenza}; domain=.${dominio}`;
        }
    }
}
