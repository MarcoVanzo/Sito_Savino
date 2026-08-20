import { describe, expect, it } from 'vitest'
import { navItemPadding, navShowsSeparators, pickNavFontSize } from './useHeaderNavFit.js'

// Larghezza del testo delle nove voci di primo livello misurata a 13px sul sito reale:
// il riferimento su cui il composable scala tutto il resto.
const TESTO_A_13PX = 793
const VOCI = 9

describe('pickNavFontSize', () => {
    it('sceglie il corpo pieno quando lo spazio abbonda', () => {
        expect(pickNavFontSize(TESTO_A_13PX, VOCI, 1400)).toBe(13)
    })

    it('rimpicciolisce invece di far sbordare le voci', () => {
        const scelto = pickNavFontSize(TESTO_A_13PX, VOCI, 700)

        expect(scelto).toBeLessThan(13)
        expect(scelto * TESTO_A_13PX / 13 + VOCI * navItemPadding(scelto) * 2).toBeLessThanOrEqual(700)
    })

    it('restituisce null quando nemmeno il corpo minimo ci sta: si passa al drawer', () => {
        expect(pickNavFontSize(TESTO_A_13PX, VOCI, 400)).toBeNull()
    })

    it('non decide nulla senza etichette da misurare', () => {
        expect(pickNavFontSize(0, 0, 1400)).toBeNull()
        expect(pickNavFontSize(TESTO_A_13PX, 0, 1400)).toBeNull()
    })

    it('lo spazio disponibile è rispettato anche contando i separatori', () => {
        // Testo e padding a 13px entrerebbero esatti: sono i separatori a sfondare.
        const spazioSenzaSeparatori = TESTO_A_13PX + VOCI * navItemPadding(13) * 2

        expect(navShowsSeparators(13)).toBe(true)
        expect(pickNavFontSize(TESTO_A_13PX, VOCI, spazioSenzaSeparatori)).toBeLessThan(13)
    })
})

describe('navItemPadding', () => {
    it('stringe il padding insieme al testo', () => {
        expect(navItemPadding(13)).toBeGreaterThan(navItemPadding(11))
        expect(navItemPadding(11)).toBeGreaterThan(navItemPadding(9))
    })
})

describe('navShowsSeparators', () => {
    it('toglie i separatori quando le voci sono già compresse', () => {
        expect(navShowsSeparators(11)).toBe(false)
        expect(navShowsSeparators(9)).toBe(false)
    })
})
