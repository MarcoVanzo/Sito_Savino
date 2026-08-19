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

let measurementId = null;
let scriptLoaded = false;

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
 */
export function updateAnalyticsConsent(granted) {
    if (!measurementId) return;

    ensureDataLayer();
    gtag('consent', 'update', { analytics_storage: granted ? 'granted' : 'denied' });

    if (granted) loadScript();
}

/**
 * Una visualizzazione di pagina. Senza titolo e percorso espliciti GA4
 * leggerebbe quelli del documento, che durante una navigazione Inertia sono
 * ancora quelli della pagina precedente.
 */
export function trackPageView() {
    if (!measurementId || !scriptLoaded) return;

    gtag('event', 'page_view', {
        page_location: window.location.href,
        page_path: window.location.pathname + window.location.search,
        page_title: document.title,
    });
}
