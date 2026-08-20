import { describe, expect, it } from 'vitest'
import { collapseCategories, seasonYear } from './collapseCategories.js'

const cat = (slug, count) => ({ slug, count })

describe('collapseCategories', () => {
    const venti = Array.from({ length: 20 }, (_, i) => cat(`cat-${i}`, 20 - i))

    it('mostra le più usate entro il limite', () => {
        const { visible, hiddenCount } = collapseCategories(venti, { limit: 8 })

        expect(visible).toHaveLength(8)
        expect(visible[0].slug).toBe('cat-0')
        expect(hiddenCount).toBe(12)
    })

    it('ordina per utilizzo, non per posizione', () => {
        const { visible } = collapseCategories(
            [cat('rara', 1), cat('frequente', 99)],
            { limit: 1 },
        )

        expect(visible[0].slug).toBe('frequente')
    })

    it('la categoria attiva resta visibile anche se poco usata', () => {
        const { visible, hiddenCount } = collapseCategories(venti, {
            limit: 8,
            activeSlug: 'cat-19',
        })

        expect(visible).toHaveLength(9)
        expect(visible.at(-1).slug).toBe('cat-19')
        expect(hiddenCount).toBe(11)
    })

    it('con showAll restituisce tutto', () => {
        const { visible, hiddenCount } = collapseCategories(venti, { showAll: true })

        expect(visible).toHaveLength(20)
        expect(hiddenCount).toBe(0)
    })

    it('sotto il limite non nasconde nulla', () => {
        const { visible, hiddenCount } = collapseCategories([cat('a', 1), cat('b', 2)], { limit: 8 })

        expect(visible).toHaveLength(2)
        expect(hiddenCount).toBe(0)
    })

    it('delle categorie di stagione tiene in vista solo l\'annata piu\' recente', () => {
        const { visible, hiddenCount } = collapseCategories([
            { slug: 'notizie', name: 'Notizie', count: 85 },
            { slug: 'serie-a1-2023-2024', name: 'Serie A1 2023/2024', count: 154 },
            { slug: 'serie-a1-2025-2026', name: 'Serie A1 2025/2026', count: 109 },
            { slug: 'serie-a1-2021-2022', name: 'Serie A1 2021/2022', count: 101 },
        ], { limit: 8 })

        expect(visible.map(c => c.slug)).toEqual(['serie-a1-2025-2026', 'notizie'])
        expect(hiddenCount).toBe(2)
    })

    it('l\'annata vecchia resta visibile se e\' quella aperta', () => {
        const { visible } = collapseCategories([
            { slug: 'notizie', name: 'Notizie', count: 85 },
            { slug: 'serie-a1-2025-2026', name: 'Serie A1 2025/2026', count: 109 },
            { slug: 'serie-a1-2021-2022', name: 'Serie A1 2021/2022', count: 101 },
        ], { limit: 8, activeSlug: 'serie-a1-2021-2022' })

        expect(visible.map(c => c.slug)).toContain('serie-a1-2021-2022')
    })
})

describe('seasonYear', () => {
    it('riconosce l\'anno di apertura dallo slug e dal nome', () => {
        expect(seasonYear({ slug: 'serie-a1-2024-2025', name: 'Serie A1 2024/2025' })).toBe(2024)
        expect(seasonYear({ slug: 'cev-cup', name: 'CEV Cup 2022/2023' })).toBe(2022)
        expect(seasonYear({ slug: 'coppa-italia-2022-23', name: 'Coppa Italia 2022-23' })).toBe(2022)
    })

    it('le categorie sempre valide non hanno annata', () => {
        expect(seasonYear({ slug: 'notizie', name: 'Notizie' })).toBeNull()
        expect(seasonYear({ slug: 'sponsor', name: 'Sponsor' })).toBeNull()
    })

    it('regge il nome per lingua', () => {
        expect(seasonYear({ slug: 'x', name: { it: 'Serie A1 2023/2024', en: 'Serie A1 2023/2024' } })).toBe(2023)
    })
})
