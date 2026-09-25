import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Le pagine pubbliche possono arrivare dalla cache (CachePublicResponse), che
 * toglie i Set-Cookie: chi apre il sito direttamente su una di quelle pagine
 * non ha né la sessione né il cookie XSRF-TOKEN, e il primo modulo che invia
 * risponde 419. Prima di una richiesta che scrive, se il cookie manca lo si
 * chiede a /csrf-cookie; axios poi lo copia da sé nell'intestazione.
 * Inertia usa questa stessa istanza di axios.
 */
export function mancaIlCookieXsrf(cookie = document.cookie) {
    return !/(?:^|;\s*)XSRF-TOKEN=/.test(cookie);
}

axios.interceptors.request.use(async (config) => {
    const metodo = (config.method ?? 'get').toLowerCase();
    const stessaOrigine = !config.url || config.url.startsWith('/') || config.url.startsWith(window.location.origin);

    if (!['get', 'head', 'options'].includes(metodo) && stessaOrigine && mancaIlCookieXsrf()) {
        try {
            await axios.get('/csrf-cookie');
        } catch {
            // Senza cookie la richiesta risponderà 419 come prima: niente di
            // peggio, e la pagina d'errore invita a ricaricare.
        }
    }

    return config;
});
