import { describe, expect, it } from 'vitest'
import { metodoPredefinito } from './metodiDiPagamento.js'

describe('metodoPredefinito', () => {
    it('con un solo metodo lo seleziona', () => {
        expect(metodoPredefinito([{ value: 'paypal', label: 'PayPal' }])).toBe('paypal')
    })

    it('con piu metodi lascia scegliere', () => {
        expect(metodoPredefinito([{ value: 'stripe' }, { value: 'paypal' }])).toBe('')
    })

    it('senza metodi non inventa niente', () => {
        expect(metodoPredefinito([])).toBe('')
        expect(metodoPredefinito(undefined)).toBe('')
        expect(metodoPredefinito(null)).toBe('')
    })

    it('ignora le voci senza valore', () => {
        expect(metodoPredefinito([null, { label: 'rotto' }, { value: 'paypal' }])).toBe('paypal')
    })
})
