import { ref } from 'vue'

/**
 * Caricamento differito di un archivio troppo grande per il payload iniziale.
 *
 * Il server manda solo il primo blocco; il resto arriva da un endpoint JSON
 * quando la pagina è già interattiva. In caso di errore non si mostra nulla:
 * restano visibili gli elementi del primo blocco e la pagina è comunque
 * utilizzabile.
 *
 * @param {{url: () => string, initialCount: number, totalCount: number}} options
 */
export function useDeferredArchive({ url, initialCount, totalCount }) {
    const items = ref(null)

    async function load() {
        if (items.value || totalCount <= initialCount) return

        try {
            const response = await globalThis.fetch(url(), {
                headers: { Accept: 'application/json' },
            })
            if (!response.ok) return

            const data = await response.json()
            if (Array.isArray(data.media) && data.media.length) {
                items.value = data.media
            }
        } catch {
            // vedi sopra: il primo blocco resta la pagina
        }
    }

    return { items, load }
}
