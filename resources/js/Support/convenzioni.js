/**
 * I partner che offrono agevolazioni agli abbonati, come arrivano dal
 * pannello: nome, sito, sconto (e su cosa), come usarlo, logo.
 *
 * Senza nome la voce non compare; senza un link http(s) valido il nome
 * non diventa un collegamento. Il logo arriva gia' come indirizzo pubblico
 * (CmsFile lato server).
 *
 * @param {unknown} raw contenuto di content_data.partners
 * @param {{safeUrl: (url: unknown) => string|null|undefined}} deps
 */
export function partnerConvenzionati(raw, { safeUrl }) {
    if (!Array.isArray(raw)) {
        return []
    }

    return raw
        .filter(p => p && typeof p === 'object')
        .map(p => ({
            name: testo(p.name),
            url: safeUrl(p.url) || null,
            discount: testo(p.discount),
            description: testo(p.description),
            howToUse: testo(p.how_to_use),
            logo: testo(p.logo) || null,
        }))
        .filter(p => p.name !== '')
        .map(p => ({ ...p, initial: p.name.charAt(0).toUpperCase() }))
}

function testo(valore) {
    return typeof valore === 'string' ? valore.trim() : ''
}
