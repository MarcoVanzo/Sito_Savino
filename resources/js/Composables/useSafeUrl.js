/**
 * Normalizza gli URL che arrivano dal CMS prima di usarli in un attributo href.
 *
 * Un valore salvato dal pannello (es. `content_data.button_url`) finisce
 * direttamente nel DOM: senza controllo sullo schema un `javascript:...`
 * diventerebbe un link cliccabile che esegue codice nella pagina.
 *
 * Sono ammessi solo http, https e mailto; i percorsi relativi allo stesso
 * sito (`/pagina`, `#ancora`) passano perché non hanno schema.
 *
 * Due forme scritte di frequente in redazione non hanno schema e il browser le
 * leggerebbe come percorsi relativi alla pagina: un'email nuda
 * (`marketing@…`, era il pulsante "Contattaci" di Hospitality, che portava a
 * `/sponsor/marketing@…` e quindi a un 404) e un dominio nudo (`www.sito.it`).
 * La prima diventa `mailto:`, il secondo `https://`.
 */

const SAFE_PROTOCOLS = new Set(['http:', 'https:', 'mailto:']);

// Base fittizia: serve solo a far risolvere i percorsi relativi a `new URL`.
const RESOLUTION_BASE = 'https://savinodelbenevolley.local';

const BARE_EMAIL = /^[^\s@/:?#]+@[^\s@/:?#]+\.[^\s@/:?#]+$/;
const BARE_DOMAIN = /^www\.[^\s/]+\.[^\s/]+/i;

/**
 * @param {*} url Valore grezzo (tipicamente dal CMS).
 * @param {string|undefined} fallback Valore restituito se l'URL non è sicuro.
 * @returns {string|undefined} URL utilizzabile, oppure il fallback.
 */
export function safeUrl(url, fallback = undefined) {
    if (typeof url !== 'string') return fallback;

    const value = url.trim();
    if (value === '') return fallback;

    // Percorsi interni: nessuno schema da validare.
    // `//host` è invece protocol-relative e va trattato come assoluto.
    if (value.startsWith('#')) return value;
    if (value.startsWith('/') && !value.startsWith('//')) return value;

    if (BARE_EMAIL.test(value)) return `mailto:${value}`;
    if (BARE_DOMAIN.test(value)) return `https://${value}`;

    try {
        const parsed = new URL(value, RESOLUTION_BASE);
        return SAFE_PROTOCOLS.has(parsed.protocol) ? value : fallback;
    } catch {
        return fallback;
    }
}

export function useSafeUrl() {
    return { safeUrl };
}

export default safeUrl;
