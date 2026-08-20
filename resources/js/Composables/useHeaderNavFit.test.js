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

/**
 * Le voci del menu principale non sono solo testo: la barra si dimensiona
 * sullo spazio che le resta accanto ai loghi, e con nove voci sta gia' al
 * corpo piu' piccolo ammesso.
 *
 * E' successo davvero: rinominando "SDB Youth" in "Savino Del Bene Youth" il
 * testo passava da 793 a 895 pixel e il menu spariva dentro il pannello a
 * scomparsa su qualunque schermo, anche a 1900px. Le misure qui sotto sono
 * quelle vere, prese in produzione con la sonda di `measureLabels`.
 */
describe('lo spazio dell\'header e\' gia\' al limite', () => {
    // Nove voci, spazio residuo misurato a 1900px di viewport.
    const VOCI_MENU = 9
    const SPAZIO_A_1900 = 657

    it('con le etichette attuali il menu entra, ma solo al corpo piu\' piccolo', () => {
        const corpo = pickNavFontSize(793, VOCI_MENU, SPAZIO_A_1900)

        expect(corpo).not.toBeNull()
        expect(corpo).toBe(9)
    })

    it('un centinaio di pixel in piu\' e il menu sparisce nel drawer', () => {
        expect(pickNavFontSize(895, VOCI_MENU, SPAZIO_A_1900)).toBeNull()
    })

    /**
     * Il margine che resta prima di perdere il menu. Se questo test inizia a
     * fallire, qualcuno ha allungato un'etichetta o allargato il blocco loghi:
     * misurare prima di allargare ancora.
     */
    it('il margine residuo e\' meno di cento pixel di testo', () => {
        const soglia = [...Array(400).keys()]
            .map(i => 793 + i)
            .find(larghezza => pickNavFontSize(larghezza, VOCI_MENU, SPAZIO_A_1900) === null)

        expect(soglia - 793).toBeLessThan(100)
    })
})
