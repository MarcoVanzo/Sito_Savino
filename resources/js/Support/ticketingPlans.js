/**
 * Normalizza i piani abbonamento pubblicati dal CMS.
 *
 * I piani arrivano SOLO dal CMS: nessun prezzo di esempio come fallback.
 * Prima il componente conteneva tre piani hard-coded (15/199/99 €) che
 * andavano in produzione come listino ufficiale, con CTA che non portavano
 * da nessuna parte. Un piano senza `cta_url` valido non ha pulsante.
 *
 * Un abbonamento ha piu' tariffe per lo stesso posto — intero, riconferma,
 * under 16 — e il listino della societa' e' fatto cosi'. Le tariffe ridotte
 * sono facoltative: senza, la scheda mostra il solo prezzo pieno.
 *
 * Il periodo puo' essere scritto dalla redazione ("a partita") oppure essere
 * la parola chiave `season`, con cui i listini sono stati importati: quella
 * si traduce, altrimenti in pagina compariva "/season" anche in italiano.
 *
 * @param {unknown} raw contenuto di content_data.plans
 * @param {{t: (key: string) => string, safeUrl: (url: unknown) => string|null|undefined}} deps
 * @returns {Array<{name: string, price: string, period: string, rates: Array<{label: string, price: string}>, features: Array, highlight: boolean, cta: string, ctaUrl: string|null}>}
 */
export function mapCmsPlans(raw, { t, safeUrl }) {
    if (!Array.isArray(raw)) {
        return []
    }

    return raw
        .filter(p => p && (p.name || p.price))
        .map(p => ({
            name: p.name || t('ticketing.plan_default_name'),
            price: p.price || '0',
            period: periodoLeggibile(p.period, t),
            rates: [
                { label: t('ticketing.rate_returning'), price: p.price_returning },
                { label: t('ticketing.rate_under16'), price: p.price_under16 },
            ].filter(r => r.price !== undefined && r.price !== null && String(r.price).trim() !== ''),
            features: Array.isArray(p.features) ? p.features : [],
            highlight: !!p.highlight,
            cta: p.cta || t('ticketing.buy_cta'),
            ctaUrl: safeUrl(p.cta_url) || null,
        }))
}

/**
 * @param {unknown} periodo
 * @param {(key: string) => string} t
 * @returns {string}
 */
function periodoLeggibile(periodo, t) {
    const testo = typeof periodo === 'string' ? periodo.trim() : ''

    if (testo === '' || testo.toLowerCase() === 'season') {
        return t('ticketing.period_season')
    }

    return testo
}
