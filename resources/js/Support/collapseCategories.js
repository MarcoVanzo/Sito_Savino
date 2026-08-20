/**
 * Riduce una lista di categorie alle più usate, tenendo sempre visibile
 * quella attiva.
 *
 * Le categorie delle news sono più di venti (una per stagione, più le
 * coppe): mostrarle tutte riempiva mezzo schermo di pillole prima della
 * prima notizia.
 *
 * Le categorie di stagione ("Serie A1 2023/2024", "CEV Cup 2022/2023") sono
 * la maggioranza, e ordinandole per numero di notizie le annate vecchie
 * scalzavano dalla prima fila le categorie sempre valide — Notizie, Sponsor,
 * Società. Di quelle stagionali resta in vista solo l'annata più recente:
 * le precedenti stanno dietro "Altro", che è esattamente dove si va a
 * cercarle.
 *
 * @param {Array<{slug: string, count?: number}>} categories
 * @param {{activeSlug?: string|null, limit?: number, showAll?: boolean}} options
 * @returns {{visible: Array, hiddenCount: number}}
 */

/** Anno di apertura di una categoria stagionale, oppure null. */
export function seasonYear(category) {
    const testo = `${category?.slug ?? ''} ${nomeLeggibile(category?.name)}`
    const match = testo.match(/(\d{4})\s*[-/]\s*(\d{4}|\d{2})\b/)

    return match ? Number(match[1]) : null
}

/**
 * Il nome arriva tradotto (una stringa) oppure come oggetto per lingua:
 * qui serve solo del testo in cui cercare l'annata.
 */
function nomeLeggibile(name) {
    if (typeof name === 'string') return name
    if (name && typeof name === 'object') return Object.values(name).join(' ')

    return ''
}

export function collapseCategories(categories, { activeSlug = null, limit = 8, showAll = false } = {}) {
    const sorted = [...categories].sort((a, b) => (b.count ?? 0) - (a.count ?? 0))

    if (showAll) {
        return { visible: sorted, hiddenCount: 0 }
    }

    const annoPiuRecente = sorted.reduce((massimo, categoria) => {
        const anno = seasonYear(categoria)

        return anno !== null && anno > massimo ? anno : massimo
    }, -Infinity)

    // Una stagionale entra in prima fila solo se è l'annata in corso.
    const candidate = sorted.filter((categoria) => {
        const anno = seasonYear(categoria)

        return anno === null || anno === annoPiuRecente
    })

    const head = candidate.slice(0, limit)
    const active = activeSlug ? sorted.find(c => c.slug === activeSlug) : null

    const visible = active && !head.includes(active) ? [...head, active] : head

    return { visible, hiddenCount: Math.max(sorted.length - visible.length, 0) }
}
