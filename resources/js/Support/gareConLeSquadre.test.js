import { describe, expect, it } from 'vitest'
import { gareConLeSquadre } from './gareConLeSquadre.js'

const squadre = {
    7: { name: 'Savino Del Bene Volley', logo: 'https://cdn.example/7.png', isOwn: true },
    9: { name: 'Il Bisonte Firenze', logo: null, isOwn: false },
}

describe('gareConLeSquadre', () => {
    it('rimette sulla gara nome, logo e appartenenza delle due squadre', () => {
        const [gara] = gareConLeSquadre([{ id: 1, homeTeam: 9, awayTeam: 7, isOwn: true }], squadre)

        expect(gara).toMatchObject({
            id: 1,
            isOwn: true,
            home: 'Il Bisonte Firenze',
            away: 'Savino Del Bene Volley',
            homeLogo: null,
            awayLogo: 'https://cdn.example/7.png',
            homeIsOwn: false,
            awayIsOwn: true,
        })
    })

    it('una squadra mancante diventa un nome vuoto senza logo', () => {
        const [gara] = gareConLeSquadre([{ id: 2, awayTeam: 404 }], squadre)

        expect(gara).toMatchObject({ home: '', away: '', homeLogo: null, awayLogo: null, homeIsOwn: false, awayIsOwn: false })
    })

    it('senza gare o senza squadre non va in errore', () => {
        expect(gareConLeSquadre(undefined, squadre)).toEqual([])
        expect(gareConLeSquadre([{ id: 3, homeTeam: 7 }], undefined)[0].home).toBe('')
    })
})
