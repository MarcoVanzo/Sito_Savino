import { describe, expect, it } from 'vitest'
import { partnerConvenzionati } from './convenzioni.js'

const deps = {
    safeUrl: (url) => (typeof url === 'string' && url.startsWith('https://') ? url : null),
}

describe('partnerConvenzionati', () => {
    it('senza elenco non inventa partner', () => {
        expect(partnerConvenzionati(undefined, deps)).toEqual([])
        expect(partnerConvenzionati({ uuid: {} }, deps)).toEqual([])
    })

    it('scarta le voci senza nome e normalizza le altre', () => {
        const partner = partnerConvenzionati([
            null,
            { url: 'https://x.example' },
            { name: ' Trattoria ', url: 'https://trattoria.example', discount: '10%', description: 'Sul conto', how_to_use: 'Mostra la tessera', logo: 'https://cdn/logo.png' },
        ], deps)

        expect(partner).toEqual([{
            name: 'Trattoria',
            url: 'https://trattoria.example',
            discount: '10%',
            description: 'Sul conto',
            howToUse: 'Mostra la tessera',
            logo: 'https://cdn/logo.png',
            initial: 'T',
        }])
    })

    it('un link non http(s) non diventa un collegamento', () => {
        const [partner] = partnerConvenzionati([{ name: 'Bar', url: 'javascript:alert(1)' }], deps)

        expect(partner.url).toBeNull()
        expect(partner.logo).toBeNull()
    })
})
