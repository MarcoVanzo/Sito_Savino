/**
 * Meta Pixel.
 *
 * È il gemello pubblicitario di `analytics.js`, ma misura un'altra cosa: non
 * quante persone leggono il sito, bensì cosa fanno quelle arrivate da una
 * inserzione. Serve al retargeting e all'attribuzione delle conversioni, quindi
 * gli eventi che contano sono quelli del funnel di acquisto.
 *
 * Tre dettagli che non sono opzionali:
 *
 * 1. Il sito è una SPA Inertia: senza un PageView a ogni navigazione, Meta
 *    vedrebbe solo la pagina d'ingresso, e i pubblici "ha visitato la pagina X"
 *    resterebbero vuoti.
 *
 * 2. `Purchase` va inviato una volta sola per ordine. La pagina di conferma si
 *    ricarica da sola ogni 5 secondi finché il webhook del pagamento non
 *    arriva: senza il blocco su sessionStorage, un ordine solo verrebbe contato
 *    fino a dodici volte e il ritorno delle campagne risulterebbe gonfiato.
 *
 * 3. `fbq` accoda l'oggetto `arguments`, non un array: è la forma che lo script
 *    di Meta si aspetta di trovare in coda quando finisce di caricarsi.
 */

import { unaVoltaPerOrdine } from './Support/unaVoltaPerOrdine.js';

let pixelId = null;
let requiresConsent = false;
let consentGranted = true;
let scriptLoaded = false;

function ensureStub() {
    if (window.fbq) return;

    const fbq = function (...args) {
        if (fbq.callMethod) {
            fbq.callMethod(...args);
        } else {
            fbq.queue.push(args);
        }
    };

    fbq.push = fbq;
    fbq.loaded = true;
    fbq.version = '2.0';
    fbq.queue = [];

    window.fbq = fbq;
    window._fbq = window._fbq || fbq;
}

function loadScript() {
    if (scriptLoaded || !pixelId || !allowed()) return;

    scriptLoaded = true;
    ensureStub();

    const script = document.createElement('script');
    script.async = true;
    script.src = 'https://connect.facebook.net/en_US/fbevents.js';
    document.head.appendChild(script);

    // Niente advanced matching automatico: con `autoConfig` attivo lo script
    // di Meta legge da solo i campi dei moduli (l'email e il telefono del
    // checkout) e li manda a Meta, cosa che l'informativa non dichiara. Va
    // detto prima di `init`, o per quel pixel vale gia' l'impostazione di Meta.
    window.fbq('set', 'autoConfig', false, pixelId);
    window.fbq('init', pixelId);
    trackPageView();
}

function allowed() {
    return !requiresConsent || consentGranted;
}

/**
 * Prepara il pixel.
 *
 * `needsConsent` arriva dalla configurazione (`META_PIXEL_REQUIRES_CONSENT`,
 * vero per impostazione predefinita e non sovrascritto nella spec): il pixel
 * parte solo con il consenso di marketing del banner. Spegnerlo resta
 * possibile da variabile d'ambiente, senza toccare questo file.
 */
export function initMetaPixel(id, { needsConsent = false, hasConsent = false } = {}) {
    if (!id) return;

    pixelId = String(id);
    requiresConsent = Boolean(needsConsent);
    consentGranted = requiresConsent ? Boolean(hasConsent) : true;

    loadScript();
}

/**
 * Da chiamare quando l'utente accetta o revoca i cookie di marketing.
 *
 * La revoca deve fermare il pixel già caricato, non solo impedirgli di
 * partire: fino al 25 settembre 2026 `track()` guardava soltanto se lo script
 * c'era, e dopo un "no" dal banner gli eventi continuavano ad arrivare a Meta
 * fino al ricaricamento della pagina. Ora gli eventi si fermano qui, lo script
 * riceve `consent revoke` (smette di scrivere e leggere i suoi cookie) e il
 * cookie `_fbp` già scritto si cancella.
 */
export function updateMarketingConsent(granted) {
    consentGranted = Boolean(granted);

    if (scriptLoaded && window.fbq) {
        window.fbq('consent', allowed() ? 'grant' : 'revoke');
    }

    if (!allowed()) {
        cancellaICookieDelPixel();

        return;
    }

    loadScript();
}

/**
 * `_fbp` e `_fbc` stanno sul dominio del sito, con o senza il punto davanti a
 * seconda di come Meta li ha scritti: si cancellano in tutte e due le forme.
 */
function cancellaICookieDelPixel() {
    if (typeof document === 'undefined') return;

    const dominio = window.location.hostname;
    const scadenza = 'expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';

    for (const nome of ['_fbp', '_fbc']) {
        document.cookie = `${nome}=; ${scadenza}`;
        document.cookie = `${nome}=; ${scadenza}; domain=${dominio}`;
        document.cookie = `${nome}=; ${scadenza}; domain=.${dominio}`;
    }
}

/**
 * `metodo` e' `track` per gli eventi standard di Meta, `trackCustom` per i
 * nostri.
 *
 * @returns {boolean} se l'evento e' partito davvero
 */
function track(event, params, metodo = 'track') {
    if (!pixelId || !scriptLoaded || !window.fbq || !allowed()) return false;

    params === undefined ? window.fbq(metodo, event) : window.fbq(metodo, event, params);

    return true;
}

export function trackPageView() {
    track('PageView');
}

/** Scheda prodotto aperta. */
export function trackViewContent({ id, name, value, currency = 'EUR' }) {
    track('ViewContent', {
        content_ids: [String(id)],
        content_name: name,
        content_type: 'product',
        value: Number(value) || 0,
        currency,
    });
}

/** Prodotto aggiunto al carrello. */
export function trackAddToCart({ id, name, value, quantity = 1, currency = 'EUR' }) {
    track('AddToCart', {
        content_ids: [String(id)],
        content_name: name,
        content_type: 'product',
        contents: [{ id: String(id), quantity: Number(quantity) || 1 }],
        value: Number(value) || 0,
        currency,
    });
}

/**
 * Clic verso la biglietteria (Vivaticket). Evento nostro e non `Lead`: chi
 * clicca non ha lasciato un contatto, e nelle campagne si usa come
 * conversione personalizzata.
 */
export function trackTicketClick(destinazione) {
    track('TicketClick', { destinazione: String(destinazione) }, 'trackCustom');
}

/** Un modulo inviato con successo (contatti, newsletter, accredito stampa). */
export function trackLead(formName) {
    track('Lead', { content_name: String(formName) });
}

/** Checkout aperto. */
export function trackInitiateCheckout({ value, numItems, currency = 'EUR' }) {
    track('InitiateCheckout', {
        value: Number(value) || 0,
        num_items: Number(numItems) || 0,
        currency,
    });
}

/**
 * Ordine concluso, una volta sola per numero d'ordine (`unaVoltaPerOrdine`):
 * ricaricare la pagina di conferma non deve produrre una seconda conversione.
 */
export function trackPurchase({ orderNumber, value, items = [], currency = 'EUR' }) {
    if (!pixelId || !orderNumber) return;

    unaVoltaPerOrdine(`meta-pixel-purchase-${orderNumber}`, () => track('Purchase', {
        content_ids: items.map((item) => String(item.id)),
        contents: items.map((item) => ({ id: String(item.id), quantity: Number(item.quantity) || 1 })),
        content_type: 'product',
        num_items: items.reduce((total, item) => total + (Number(item.quantity) || 0), 0),
        value: Number(value) || 0,
        currency,
    }));
}
