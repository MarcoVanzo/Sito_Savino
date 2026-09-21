import { describe, expect, it } from 'vitest'
import { costoDiSpedizione } from './spedizione.js'

// La zona dell'Italia come sta in archivio: 7,50 € di tariffa base e
// spedizione gratuita da 100 €.
const italia = {
    flat_rate: '7.50',
    free_threshold: '100.00',
    weight_rates: [],
}

const conFasce = {
    ...italia,
    weight_rates: [
        { max_weight: 2, rate: 5.9 },
        { max_weight: 5, rate: 7.5 },
        { max_weight: null, rate: 14.9 },
    ],
}

describe('costoDiSpedizione', () => {
    it('senza zona non costa nulla', () => {
        expect(costoDiSpedizione(null, { subtotale: 50, peso: 3 })).toBe(0)
        expect(costoDiSpedizione(undefined)).toBe(0)
    })

    it('senza fasce vale la tariffa base, qualunque sia il peso', () => {
        expect(costoDiSpedizione(italia, { subtotale: 30, peso: 0 })).toBe(7.5)
        expect(costoDiSpedizione(italia, { subtotale: 30, peso: 40 })).toBe(7.5)
    })

    it('la soglia della spedizione gratuita viene prima delle fasce', () => {
        expect(costoDiSpedizione(conFasce, { subtotale: 100, peso: 12 })).toBe(0)
        expect(costoDiSpedizione(conFasce, { subtotale: 99.99, peso: 12 })).toBe(14.9)
    })

    it('prende la prima fascia che contiene il peso', () => {
        expect(costoDiSpedizione(conFasce, { subtotale: 20, peso: 0.4 })).toBe(5.9)
        expect(costoDiSpedizione(conFasce, { subtotale: 20, peso: 2 })).toBe(5.9)
        expect(costoDiSpedizione(conFasce, { subtotale: 20, peso: 2.1 })).toBe(7.5)
        expect(costoDiSpedizione(conFasce, { subtotale: 20, peso: 5 })).toBe(7.5)
    })

    it('l\'ultima fascia senza limite prende tutto il resto', () => {
        expect(costoDiSpedizione(conFasce, { subtotale: 20, peso: 5.1 })).toBe(14.9)
        expect(costoDiSpedizione(conFasce, { subtotale: 20, peso: 300 })).toBe(14.9)
    })

    it('con le fasce tutte chiuse e il collo oltre l\'ultima torna la tariffa base', () => {
        const zona = { ...italia, weight_rates: [{ max_weight: 2, rate: 5.9 }] }

        expect(costoDiSpedizione(zona, { subtotale: 20, peso: 9 })).toBe(7.5)
    })

    it('riordina le fasce compilate fuori ordine nel pannello', () => {
        const zona = {
            ...italia,
            weight_rates: [
                { max_weight: null, rate: 14.9 },
                { max_weight: 5, rate: 7.5 },
                { max_weight: 2, rate: 5.9 },
            ],
        }

        expect(costoDiSpedizione(zona, { subtotale: 20, peso: 1 })).toBe(5.9)
        expect(costoDiSpedizione(zona, { subtotale: 20, peso: 4 })).toBe(7.5)
        expect(costoDiSpedizione(zona, { subtotale: 20, peso: 8 })).toBe(14.9)
    })

    it('ignora le righe senza tariffa, che non sono fasce', () => {
        const zona = {
            ...italia,
            weight_rates: [{ max_weight: 2 }, { max_weight: null, rate: '9.90' }],
        }

        expect(costoDiSpedizione(zona, { subtotale: 20, peso: 1 })).toBe(9.9)
    })

    it('regge un `weight_rates` che non è un elenco', () => {
        expect(costoDiSpedizione({ ...italia, weight_rates: null }, { subtotale: 20, peso: 3 })).toBe(7.5)
        expect(costoDiSpedizione({ ...italia, weight_rates: 'no' }, { subtotale: 20, peso: 3 })).toBe(7.5)
    })

    it('una zona senza soglia non regala mai la spedizione', () => {
        const zona = { flat_rate: '29.90', free_threshold: null, weight_rates: [] }

        expect(costoDiSpedizione(zona, { subtotale: 5000, peso: 1 })).toBe(29.9)
    })
})
