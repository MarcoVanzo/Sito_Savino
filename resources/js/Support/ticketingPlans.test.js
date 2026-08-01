import { describe, expect, it } from 'vitest'
import { mapCmsPlans } from './ticketingPlans.js'

const deps = {
    t: (key) => `[${key}]`,
    // stessa semantica di useSafeUrl: valida solo gli URL http(s)
    safeUrl: (url) => (typeof url === 'string' && url.startsWith('https://') ? url : null),
}

describe('mapCmsPlans', () => {
    it('senza piani dal CMS non inventa nulla', () => {
        expect(mapCmsPlans(undefined, deps)).toEqual([])
        expect(mapCmsPlans(null, deps)).toEqual([])
        expect(mapCmsPlans('non-array', deps)).toEqual([])
        expect(mapCmsPlans([], deps)).toEqual([])
    })

    it('scarta le voci vuote', () => {
        const plans = mapCmsPlans([null, {}, { name: 'Gold', price: '199' }], deps)

        expect(plans).toHaveLength(1)
        expect(plans[0].name).toBe('Gold')
    })

    it('senza cta_url valido il piano non ha pulsante', () => {
        const [senza, conJavascript, con] = mapCmsPlans([
            { name: 'Base', price: '99' },
            { name: 'Gold', price: '199', cta_url: 'javascript:alert(1)' },
            { name: 'Vip', price: '499', cta_url: 'https://biglietti.example/vip' },
        ], deps)

        expect(senza.ctaUrl).toBeNull()
        expect(conJavascript.ctaUrl).toBeNull()
        expect(con.ctaUrl).toBe('https://biglietti.example/vip')
    })

    it('riempie i campi mancanti con le etichette tradotte', () => {
        const [plan] = mapCmsPlans([{ price: '15' }], deps)

        expect(plan.name).toBe('[ticketing.plan_default_name]')
        expect(plan.period).toBe('[ticketing.period_season]')
        expect(plan.cta).toBe('[ticketing.buy_cta]')
        expect(plan.features).toEqual([])
        expect(plan.highlight).toBe(false)
    })
})
