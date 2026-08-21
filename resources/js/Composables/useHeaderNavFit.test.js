import { describe, expect, it } from 'vitest'
import { navItemPadding, navShowsSeparators, pickNavFontSize, shopButtonWidth } from './useHeaderNavFit.js'

// Larghezza del testo delle sette voci di primo livello misurata a 13px sul sito
// reale: il riferimento su cui il composable scala tutto il resto. Sono sette e non
// otto perche' il negozio ha lasciato la barra ed e' il pulsante pieno della testata.
const TESTO_A_13PX = 560
const VOCI = 7

describe('pickNavFontSize', () => {
    it('sceglie il corpo pieno quando lo spazio abbonda', () => {
        expect(pickNavFontSize(TESTO_A_13PX, VOCI, 1400)).toBe(16)
    })

    it('rimpicciolisce invece di far sbordare le voci', () => {
        const scelto = pickNavFontSize(TESTO_A_13PX, VOCI, 620)

        expect(scelto).toBeLessThan(13)
        expect(scelto * TESTO_A_13PX / 13 + VOCI * navItemPadding(scelto) * 2).toBeLessThanOrEqual(620)
    })

    it('restituisce null quando nemmeno il corpo minimo ci sta: si passa al drawer', () => {
        expect(pickNavFontSize(TESTO_A_13PX, VOCI, 300)).toBeNull()
    })

    it('non decide nulla senza etichette da misurare', () => {
        expect(pickNavFontSize(0, 0, 1400)).toBeNull()
        expect(pickNavFontSize(TESTO_A_13PX, 0, 1400)).toBeNull()
    })

    it('lo spazio disponibile è rispettato anche contando i separatori', () => {
        // Testo e padding a 15px entrerebbero esatti: sono i separatori a sfondare.
        const testoA15 = TESTO_A_13PX * 15 / 13
        const spazioSenzaSeparatori = testoA15 + VOCI * navItemPadding(15) * 2

        expect(navShowsSeparators(15)).toBe(true)
        expect(pickNavFontSize(TESTO_A_13PX, VOCI, spazioSenzaSeparatori)).toBeLessThan(15)
    })
})

describe('navItemPadding', () => {
    it('stringe il padding insieme al testo', () => {
        expect(navItemPadding(16)).toBeGreaterThan(navItemPadding(13))
        expect(navItemPadding(13)).toBeGreaterThan(navItemPadding(11))
        expect(navItemPadding(11)).toBeGreaterThan(navItemPadding(9))
    })
})

describe('navShowsSeparators', () => {
    it('toglie i separatori quando le voci sono già compresse', () => {
        expect(navShowsSeparators(13)).toBe(false)
        expect(navShowsSeparators(11)).toBe(false)
    })
})

describe('shopButtonWidth', () => {
    it('senza voce evidenziata il pulsante non occupa spazio', () => {
        expect(shopButtonWidth(0)).toBe(0)
    })

    /**
     * Misure vere a 13px: "Shop" e' 41px di testo, "Shop Ufficiale" 121. La seconda
     * costa alla barra una ottantina di pixel, cioe' un corpo intero per le voci.
     */
    it('l\'etichetta lunga si paga sul menu', () => {
        expect(shopButtonWidth(121) - shopButtonWidth(41)).toBeGreaterThan(80)
    })
})

/**
 * Le voci del menu principale non sono solo testo: la barra si dimensiona
 * sullo spazio che le resta accanto ai loghi, e i loghi ora sono grandi.
 *
 * E' successo davvero: rinominando "SDB Youth" in "Savino Del Bene Youth" il
 * testo cresceva di un centinaio di pixel e il menu spariva dentro il pannello a
 * scomparsa. Le misure qui sotto sono quelle vere, prese con la sonda di
 * `measureLabels` su un viewport da 1512px (il portatile piu' diffuso in
 * redazione), con il marchio corporate alla dimensione attuale.
 */
describe('lo spazio dell\'header e\' gia\' al limite', () => {
    const SPAZIO_A_1512 = 731

    it('con le etichette attuali il menu entra a corpo leggibile', () => {
        expect(pickNavFontSize(TESTO_A_13PX, VOCI, SPAZIO_A_1512)).toBe(13)
    })

    it('una voce in piu\' costa un corpo di testo', () => {
        // Con "Summer Camp" pubblicata le voci sono otto e 112px piu' larghe.
        expect(pickNavFontSize(TESTO_A_13PX + 112, VOCI + 1, SPAZIO_A_1512)).toBe(12)
    })

    /**
     * Il margine che resta prima di perdere un corpo di testo. Se questo test
     * inizia a fallire, qualcuno ha allungato un'etichetta o allargato il blocco
     * loghi: misurare prima di allargare ancora.
     */
    it('il margine residuo e\' meno di cinquanta pixel di testo', () => {
        const soglia = [...Array(400).keys()]
            .map(i => TESTO_A_13PX + i)
            .find(larghezza => pickNavFontSize(larghezza, VOCI, SPAZIO_A_1512) < 13)

        expect(soglia - TESTO_A_13PX).toBeLessThan(50)
    })
})
