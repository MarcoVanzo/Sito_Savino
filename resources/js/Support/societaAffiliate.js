/**
 * Le societa' del progetto affiliazioni raggruppate per livello.
 *
 * I gruppi escono nell'ordine dei livelli (Main Partner, Partner Ufficiale,
 * Societa' Affiliate) e non nell'ordine in cui la redazione ha scritto
 * l'elenco; un livello senza societa' non compare, perche' un'intestazione
 * sopra il vuoto non dice niente a chi guarda.
 *
 * @param {unknown} raw content_data.affiliates
 * @param {{ordine: string[], etichetta: (livello: string) => string, safeUrl: (url: unknown) => string|null|undefined}} deps
 */
export function societaAffiliate(raw, { ordine, etichetta, safeUrl }) {
    if (!Array.isArray(raw)) {
        return []
    }

    const societa = raw
        .filter(voce => voce && typeof voce === 'object')
        .map(voce => ({
            name: typeof voce.name === 'string' ? voce.name.trim() : '',
            tier: typeof voce.tier === 'string' && ordine.includes(voce.tier) ? voce.tier : ordine[ordine.length - 1],
            logo: typeof voce.logo === 'string' && voce.logo !== '' ? voce.logo : null,
            url: safeUrl(voce.url) || null,
        }))
        // Senza nome non c'e' neppure il testo di ripiego per un logo mancante.
        .filter(voce => voce.name !== '')

    return ordine
        .map(livello => ({
            key: livello,
            label: etichetta(livello),
            clubs: societa.filter(voce => voce.tier === livello),
        }))
        .filter(gruppo => gruppo.clubs.length > 0)
}
