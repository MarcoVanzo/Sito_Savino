import { describe, expect, it } from 'vitest'
import { societaAffiliate } from './societaAffiliate.js'

const deps = {
    ordine: ['main', 'official', 'affiliated'],
    etichetta: (livello) => `[${livello}]`,
    safeUrl: (url) => (typeof url === 'string' && url.startsWith('https://') ? url : null),
}

describe('societaAffiliate', () => {
    it('senza elenco non c\'e\' nessun gruppo', () => {
        expect(societaAffiliate(undefined, deps)).toEqual([])
        expect(societaAffiliate([], deps)).toEqual([])
    })

    it('raggruppa nell\'ordine dei livelli, non in quello dell\'elenco', () => {
        const gruppi = societaAffiliate([
            { name: 'Volley Appennino', tier: 'affiliated', url: 'https://volleyappennino.it', logo: '/storage/a.png' },
            { name: 'Nottolini', tier: 'official' },
            { name: 'Savino Del Bene S.p.A.', tier: 'main' },
        ], deps)

        expect(gruppi.map(g => g.key)).toEqual(['main', 'official', 'affiliated'])
        expect(gruppi[0].clubs[0].name).toBe('Savino Del Bene S.p.A.')
        expect(gruppi[2].clubs[0]).toEqual({
            name: 'Volley Appennino',
            tier: 'affiliated',
            logo: '/storage/a.png',
            url: 'https://volleyappennino.it',
        })
    })

    it('i livelli vuoti non compaiono e un livello sconosciuto finisce fra le affiliate', () => {
        const gruppi = societaAffiliate([{ name: 'Lupi S. Croce', tier: 'sponsor-tecnico' }], deps)

        expect(gruppi).toHaveLength(1)
        expect(gruppi[0].key).toBe('affiliated')
    })

    it('scarta le voci senza nome e i link non sicuri', () => {
        const gruppi = societaAffiliate([
            { name: '   ', tier: 'main' },
            { name: 'CTT Monsummano', tier: 'official', url: 'javascript:alert(1)' },
        ], deps)

        expect(gruppi).toHaveLength(1)
        expect(gruppi[0].clubs[0].url).toBeNull()
    })
})
