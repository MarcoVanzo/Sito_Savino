import { describe, expect, it } from 'vitest'
import { classificaClubRace } from './clubRaceStandings.js'

describe('classificaClubRace', () => {
    it('senza dati dal CMS non inventa nulla', () => {
        expect(classificaClubRace(undefined)).toEqual([])
        expect(classificaClubRace({ a: 1 })).toEqual([])
        expect(classificaClubRace([])).toEqual([])
    })

    it('ordina per punti e assegna la posizione', () => {
        const classifica = classificaClubRace([
            { club: 'Nottolini Volley', points: '12' },
            { club: 'Fusion Team Volley', points: 30 },
            { club: 'Vola Valley', points: '18,5' },
        ])

        expect(classifica.map(r => [r.position, r.club, r.points])).toEqual([
            [1, 'Fusion Team Volley', 30],
            [2, 'Vola Valley', 18.5],
            [3, 'Nottolini Volley', 12],
        ])
    })

    it('a parita\' di punti decide il nome', () => {
        const classifica = classificaClubRace([
            { club: 'Zeta', points: 10 },
            { club: 'Alfa', points: 10 },
        ])

        expect(classifica.map(r => r.club)).toEqual(['Alfa', 'Zeta'])
    })

    it('scarta le righe senza societa\' e legge zero dove il punteggio manca', () => {
        const classifica = classificaClubRace([
            { club: '', points: 99 },
            null,
            { club: 'Lupi Santa Croce' },
            { club: 'La Spezia', points: 'abc' },
        ])

        expect(classifica.map(r => [r.club, r.points])).toEqual([
            ['La Spezia', 0],
            ['Lupi Santa Croce', 0],
        ])
    })
})
