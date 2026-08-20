/**
 * Raggruppa le foto della gallery negli album a cui appartengono.
 *
 * L'archivio è di circa novecento foto e rovesciarle tutte in una griglia sola
 * era il disordine che la redazione ci ha segnalato. Gli album esistono già nel
 * CMS ("Eventi Gallery"): qui si ricostruiscono dal lato foto, perché è la foto
 * a portarsi dietro l'evento e la copertina.
 *
 * Le foto senza album restano fuori: comparirebbero come cartelle anonime da
 * una sola immagine.
 *
 * @param {Array<{event_id?: number|null, event_name?: string|null, event_date?: string|null, thumb?: string, url?: string}>} media
 * @param {{untitled?: string}} [options] etichetta per un album senza nome
 * @returns {Array<{id: number, name: string, date: string|null, cover: string|undefined, count: number}>}
 */
export function groupIntoAlbums(media, { untitled = '' } = {}) {
    if (!Array.isArray(media)) return []

    const byId = new Map()

    for (const item of media) {
        if (!item?.event_id) continue

        let album = byId.get(item.event_id)

        if (!album) {
            album = {
                id: item.event_id,
                name: item.event_name || untitled,
                date: item.event_date || null,
                // La prima foto dell'album fa da copertina: l'ordine arriva già
                // deciso dal backend (`GalleryImage::ordered()`).
                cover: item.thumb || item.url,
                count: 0,
            }
            byId.set(item.event_id, album)
        }

        album.count += 1
    }

    // Dal più recente. Un album senza data finisce in fondo, non in cima:
    // `''` perde il confronto con qualunque data.
    return [...byId.values()].sort((a, b) => (b.date ?? '').localeCompare(a.date ?? ''))
}

/**
 * Cerca fra le foto per tag dell'AI, nome dell'atleta, nome dell'evento e
 * titolo. La ricerca attraversa tutte le categorie: restringerla a quella
 * aperta nascondeva metà dei risultati senza dirlo.
 *
 * @param {Array<object>} media
 * @param {string} query già normalizzata in minuscolo e senza spazi ai lati
 * @returns {Array<object>}
 */
export function searchMedia(media, query) {
    if (!Array.isArray(media)) return []
    if (!query) return media

    return media.filter(m => {
        const matchAlt = m.alt?.toLowerCase().includes(query)
        const matchTags = m.tags?.some(t => t.toLowerCase().includes(query))
        const matchEvent = m.event_name?.toLowerCase().includes(query)

        return Boolean(matchAlt || matchTags || matchEvent)
    })
}
