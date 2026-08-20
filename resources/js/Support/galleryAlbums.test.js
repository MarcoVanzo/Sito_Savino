import { describe, expect, it } from 'vitest'
import { groupIntoAlbums, searchMedia } from './galleryAlbums.js'

const foto = (evento, extra = {}) => ({
    event_id: evento?.id ?? null,
    event_name: evento?.name ?? null,
    event_date: evento?.date ?? null,
    thumb: 'thumb.jpg',
    url: 'full.jpg',
    ...extra,
})

const milano = { id: 1, name: 'Savino Del Bene — Numia Milano', date: '2026-10-05' }
const firenze = { id: 2, name: 'Savino Del Bene — Il Bisonte Firenze', date: '2026-10-12' }

describe('groupIntoAlbums', () => {
    it('raggruppa le foto per evento e le conta', () => {
        const album = groupIntoAlbums([foto(milano), foto(milano), foto(firenze)])

        expect(album).toHaveLength(2)
        expect(album.find(a => a.id === 1).count).toBe(2)
        expect(album.find(a => a.id === 2).count).toBe(1)
    })

    it('mette prima gli album piu\' recenti', () => {
        const album = groupIntoAlbums([foto(milano), foto(firenze)])

        expect(album.map(a => a.id)).toEqual([2, 1])
    })

    /**
     * Due eventi possono chiamarsi uguale in stagioni diverse: il
     * raggruppamento segue l'identificativo, non il titolo.
     */
    it('non fonde due album omonimi di stagioni diverse', () => {
        const album = groupIntoAlbums([
            foto({ id: 1, name: 'Coppa Italia', date: '2025-01-10' }),
            foto({ id: 9, name: 'Coppa Italia', date: '2026-01-10' }),
        ])

        expect(album).toHaveLength(2)
        expect(album.map(a => a.id)).toEqual([9, 1])
    })

    it('un album senza data finisce in fondo, non in cima', () => {
        const album = groupIntoAlbums([
            foto({ id: 7, name: 'Senza data', date: null }),
            foto(milano),
        ])

        expect(album.map(a => a.id)).toEqual([1, 7])
    })

    it('la prima foto fa da copertina', () => {
        const album = groupIntoAlbums([
            foto(milano, { thumb: 'prima.jpg' }),
            foto(milano, { thumb: 'seconda.jpg' }),
        ])

        expect(album[0].cover).toBe('prima.jpg')
    })

    it('senza miniatura la copertina ricade sull\'originale', () => {
        const album = groupIntoAlbums([foto(milano, { thumb: undefined })])

        expect(album[0].cover).toBe('full.jpg')
    })

    /** Una foto non assegnata a nessun evento diventerebbe una cartella da una foto. */
    it('scarta le foto senza album', () => {
        expect(groupIntoAlbums([foto(null), foto(milano)])).toHaveLength(1)
    })

    it('usa l\'etichetta di ripiego quando l\'album non ha nome', () => {
        const album = groupIntoAlbums([foto({ id: 3, name: null, date: '2026-01-01' })], { untitled: 'Senza titolo' })

        expect(album[0].name).toBe('Senza titolo')
    })

    it('regge un archivio non ancora caricato', () => {
        expect(groupIntoAlbums(undefined)).toEqual([])
        expect(groupIntoAlbums(null)).toEqual([])
        expect(groupIntoAlbums([])).toEqual([])
    })
})

describe('searchMedia', () => {
    const archivio = [
        foto(milano, { alt: 'Muro di Ognjenovic', tags: ['Maja Ognjenović'], category: 'Partite' }),
        foto(firenze, { alt: 'Esultanza', tags: ['Chidera Eze'], category: 'Partite' }),
        foto({ id: 5, name: 'Presentazione squadra', date: '2026-09-20' }, { alt: 'Foto di gruppo', tags: [], category: 'Eventi' }),
    ]

    it('cerca nel titolo, nei tag e nel nome dell\'evento', () => {
        expect(searchMedia(archivio, 'muro')).toHaveLength(1)
        expect(searchMedia(archivio, 'eze')).toHaveLength(1)
        expect(searchMedia(archivio, 'presentazione')).toHaveLength(1)
    })

    /**
     * La ricerca è un'azione globale: restringerla alla categoria aperta
     * nascondeva i risultati che stavano nelle altre.
     */
    it('attraversa le categorie', () => {
        const risultati = searchMedia(archivio, 'savino del bene')

        expect(risultati).toHaveLength(2)
        expect(risultati.every(r => r.category === 'Partite')).toBe(true)
    })

    it('senza termine restituisce tutto', () => {
        expect(searchMedia(archivio, '')).toHaveLength(3)
    })

    it('una foto senza tag non fa esplodere la ricerca', () => {
        expect(() => searchMedia([foto(milano)], 'qualcosa')).not.toThrow()
        expect(searchMedia([foto(milano)], 'qualcosa')).toEqual([])
    })
})
