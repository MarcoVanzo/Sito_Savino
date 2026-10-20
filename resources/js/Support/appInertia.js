/**
 * Le parti dell'app Inertia uguali nel browser (app.js) e nel server SSR
 * (ssr.js): titolo della scheda, `route()` nella lingua della pagina e `$t`.
 * Due copie della stessa regola prima o poi divergono, e qui una differenza
 * fra server e browser diventa un errore di idratazione.
 */
import { createTranslations } from '../i18n/index.js';
import { titoloDellaPagina } from './metaDellaPagina.js';

export const NOME_DEL_SITO = 'Savino Del Bene Volley';

/**
 * Il titolo calcolato dal server (prop `seo`, ServeSocialCrawlerMeta) vince su
 * quello della pagina: e' quello dell'HTML servito, e il client non deve
 * riscriverlo diverso (audit SEO del 14/10/2026). Le pagine che usano
 * useOgMeta arrivano con il nome del sito gia' in coda: senza il controllo
 * diventava «Shop Ufficiale — Savino Del Bene Volley - Savino Del Bene Volley».
 */
export function titoloDellaScheda(titolo, props, nomeDelSito = NOME_DEL_SITO) {
    const titoloDelServer = titoloDellaPagina(props);
    if (titoloDelServer) return titoloDelServer;

    if (!titolo) return nomeDelSito;

    return titolo.includes(nomeDelSito) ? titolo : `${titolo} - ${nomeDelSito}`;
}

/**
 * `route()` nella lingua della pagina: in inglese il nome `x` diventa `en.x`
 * quando la rotta inglese esiste (gli slug sono tradotti).
 *
 * @param {(name?: string, params?: unknown, absolute?: boolean, config?: object) => any} originale
 * @param {string} locale
 * @param {(nome: string) => boolean} esiste
 */
export function routeNellaLingua(originale, locale, esiste) {
    return (name, params, absolute, config) => {
        if (locale && locale !== 'it' && name && !name.startsWith(`${locale}.`)) {
            const localizzato = `${locale}.${name}`;

            if (esiste(localizzato)) {
                return originale(localizzato, params, absolute, config);
            }
        }

        return originale(name, params, absolute, config);
    };
}

/**
 * `$t` globale dei template, con la lingua della pagina corrente.
 */
export function installaLeTraduzioni(app, linguaIniziale) {
    app.config.globalProperties.$t = function (key, params = {}) {
        const locale = this.$page?.props?.locale || linguaIniziale || 'it';

        return createTranslations(locale)(key, params);
    };
}
