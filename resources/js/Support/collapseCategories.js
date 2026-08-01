/**
 * Riduce una lista di categorie alle più usate, tenendo sempre visibile
 * quella attiva.
 *
 * Le categorie delle news sono più di venti (una per stagione, più le
 * coppe): mostrarle tutte riempiva mezzo schermo di pillole prima della
 * prima notizia.
 *
 * @param {Array<{slug: string, count?: number}>} categories
 * @param {{activeSlug?: string|null, limit?: number, showAll?: boolean}} options
 * @returns {{visible: Array, hiddenCount: number}}
 */
export function collapseCategories(categories, { activeSlug = null, limit = 8, showAll = false } = {}) {
    const sorted = [...categories].sort((a, b) => (b.count ?? 0) - (a.count ?? 0))

    if (showAll) {
        return { visible: sorted, hiddenCount: 0 }
    }

    const head = sorted.slice(0, limit)
    const active = activeSlug ? sorted.find(c => c.slug === activeSlug) : null

    const visible = active && !head.includes(active) ? [...head, active] : head

    return { visible, hiddenCount: Math.max(sorted.length - visible.length, 0) }
}
