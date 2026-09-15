import { describe, expect, it } from 'vitest'
import { blocchiTicketing } from './ticketingBlocks.js'

const deps = {
    t: (key) => `[${key}]`,
    safeUrl: (url) => (typeof url === 'string' && url.startsWith('https://') ? url : null),
}

describe('blocchiTicketing', () => {
    it('senza contenuti dal CMS nessun blocco compare', () => {
        expect(blocchiTicketing(undefined, deps)).toEqual({ feature: null, benefits: [], phases: [], giftCard: null })
        expect(blocchiTicketing({}, deps)).toEqual({ feature: null, benefits: [], phases: [], giftCard: null })
    })

    it('lo spazio in evidenza compare con il solo testo o la sola grafica', () => {
        expect(blocchiTicketing({ feature_text: 'Biglietti dal 1° ottobre' }, deps).feature).toMatchObject({ hasText: true, image: null })
        expect(blocchiTicketing({ feature_image: 'https://cdn.example/believe.jpg' }, deps).feature).toMatchObject({ hasText: false, image: 'https://cdn.example/believe.jpg' })
        expect(blocchiTicketing({ feature_button_url: 'https://vivaticket.example' }, deps).feature).toBeNull()
    })

    it('il pulsante dello spazio in evidenza vuole un link valido e ha un testo di riserva', () => {
        const { feature } = blocchiTicketing({ feature_title: 'Biglietti', feature_button_url: 'javascript:alert(1)' }, deps)
        expect(feature.buttonUrl).toBeNull()

        const con = blocchiTicketing({ feature_title: 'Biglietti', feature_button_url: 'https://vivaticket.example' }, deps).feature
        expect(con.buttonUrl).toBe('https://vivaticket.example')
        expect(con.buttonText).toBe('[ticketing.feature_cta]')
    })

    it('i vantaggi accettano sia le voci del Repeater sia le stringhe nude e scartano le vuote', () => {
        const { benefits } = blocchiTicketing({ benefits: [{ text: 'Sconto 10%' }, 'Prelazione', { text: '  ' }, null] }, deps)
        expect(benefits).toEqual(['Sconto 10%', 'Prelazione'])
        expect(blocchiTicketing({ benefits: 'no' }, deps).benefits).toEqual([])
    })

    it('le fasi senza titolo non compaiono', () => {
        const { phases } = blocchiTicketing({ phases: [{ title: 'Prelazione', period: 'Dal 28/7', description: 'Scrivi a ticketing@' }, { period: 'x' }, 'no'] }, deps)
        expect(phases).toEqual([{ title: 'Prelazione', period: 'Dal 28/7', description: 'Scrivi a ticketing@' }])
    })

    it('la gift card compare solo con testo, titolo o grafica e ha etichette di riserva', () => {
        expect(blocchiTicketing({ gift_card_url: 'https://vivaticket.example/vivacard' }, deps).giftCard).toBeNull()

        const { giftCard } = blocchiTicketing({ gift_card_text: 'Scegli l\'importo', gift_card_url: 'https://vivaticket.example/vivacard' }, deps)
        expect(giftCard).toEqual({
            title: '[ticketing.gift_card_label]',
            text: 'Scegli l\'importo',
            image: null,
            url: 'https://vivaticket.example/vivacard',
            buttonText: '[ticketing.gift_card_cta]',
        })
    })
})
