import { afterEach, describe, expect, it, vi } from 'vitest'
import { useDeferredArchive } from './useDeferredArchive.js'

const media = (n) => Array.from({ length: n }, (_, i) => ({ id: i }))

function stubFetch(response) {
    const spy = vi.fn().mockResolvedValue(response)
    vi.stubGlobal('fetch', spy)

    return spy
}

describe('useDeferredArchive', () => {
    afterEach(() => vi.unstubAllGlobals())

    it('carica l\'archivio completo dall\'endpoint', async () => {
        const spy = stubFetch({ ok: true, json: async () => ({ media: media(130) }) })
        const { items, load } = useDeferredArchive({ url: () => '/gallery/data', initialCount: 120, totalCount: 130 })

        await load()

        expect(spy).toHaveBeenCalledWith('/gallery/data', expect.objectContaining({
            headers: { Accept: 'application/json' },
        }))
        expect(items.value).toHaveLength(130)
    })

    it('non chiama la rete se il primo blocco è già tutto', async () => {
        const spy = stubFetch({ ok: true, json: async () => ({ media: [] }) })
        const { items, load } = useDeferredArchive({ url: () => '/gallery/data', initialCount: 50, totalCount: 50 })

        await load()

        expect(spy).not.toHaveBeenCalled()
        expect(items.value).toBeNull()
    })

    it('non ricarica se l\'archivio è già in memoria', async () => {
        const spy = stubFetch({ ok: true, json: async () => ({ media: media(130) }) })
        const { load } = useDeferredArchive({ url: () => '/gallery/data', initialCount: 120, totalCount: 130 })

        await load()
        await load()

        expect(spy).toHaveBeenCalledTimes(1)
    })

    it('su errore di rete restano gli elementi del primo blocco', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('rete giù')))
        const { items, load } = useDeferredArchive({ url: () => '/gallery/data', initialCount: 120, totalCount: 130 })

        await load()

        expect(items.value).toBeNull()
    })

    it('una risposta non ok viene ignorata', async () => {
        stubFetch({ ok: false })
        const { items, load } = useDeferredArchive({ url: () => '/gallery/data', initialCount: 120, totalCount: 130 })

        await load()

        expect(items.value).toBeNull()
    })
})
