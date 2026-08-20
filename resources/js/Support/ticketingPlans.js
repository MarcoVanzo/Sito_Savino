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
            period: p.period || t('ticketing.period_season'),
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
