import { describe, expect, it } from 'vitest'
import { collapseCategories } from './collapseCategories.js'

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
})
