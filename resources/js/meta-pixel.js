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

let pixelId = null;
let requiresConsent = false;
let consentGranted = true;
let scriptLoaded = false;

function ensureStub() {
    if (window.fbq) return;

    const fbq = function () {
        fbq.callMethod ? fbq.callMethod.apply(fbq, arguments) : fbq.queue.push(arguments);
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

    window.fbq('init', pixelId);
    trackPageView();
}

function allowed() {
    return !requiresConsent || consentGranted;
}

/**
 * Prepara il pixel.
 *
 * `needsConsent` arriva dalla configurazione: oggi il pixel parte per tutti,
 * ma il giorno in cui va subordinato al consenso marketing si cambia una
 * variabile d'ambiente e questo file non si tocca.
 */
export function initMetaPixel(id, { needsConsent = false, hasConsent = false } = {}) {
    if (!id) return;

    pixelId = String(id);
    requiresConsent = Boolean(needsConsent);
    consentGranted = requiresConsent ? Boolean(hasConsent) : true;

    loadScript();
}

/** Da chiamare quando l'utente accetta o revoca i cookie di marketing. */
export function updateMarketingConsent(granted) {
    consentGranted = Boolean(granted);

    if (allowed()) loadScript();
}

function track(event, params) {
    if (!pixelId || !scriptLoaded || !window.fbq) return;

    params === undefined ? window.fbq('track', event) : window.fbq('track', event, params);
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

/** Checkout aperto. */
export function trackInitiateCheckout({ value, numItems, currency = 'EUR' }) {
    track('InitiateCheckout', {
        value: Number(value) || 0,
        num_items: Number(numItems) || 0,
        currency,
    });
}

/**
 * Ordine concluso. Il blocco è per numero d'ordine e vive nella sessione del
 * browser: ricaricare la pagina di conferma non deve produrre una seconda
 * conversione.
 */
export function trackPurchase({ orderNumber, value, items = [], currency = 'EUR' }) {
    if (!pixelId || !orderNumber) return;

    const key = `meta-pixel-purchase-${orderNumber}`;

    try {
        if (sessionStorage.getItem(key)) return;
        sessionStorage.setItem(key, '1');
    } catch {
        // Storage non disponibile (navigazione privata, storage pieno): meglio
        // un evento in più che una conversione persa.
    }

    track('Purchase', {
        content_ids: items.map((item) => String(item.id)),
        contents: items.map((item) => ({ id: String(item.id), quantity: Number(item.quantity) || 1 })),
        content_type: 'product',
        num_items: items.reduce((total, item) => total + (Number(item.quantity) || 0), 0),
        value: Number(value) || 0,
        currency,
    });
}
