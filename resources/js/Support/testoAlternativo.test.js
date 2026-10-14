import { describe, it, expect } from 'vitest';
import { testoAlternativo } from './testoAlternativo.js';

describe('testoAlternativo', () => {
    it('prende il primo candidato non vuoto, senza spazi ai lati', () => {
        expect(testoAlternativo('', '  ', ' Titolo della notizia ', 'altro')).toBe('Titolo della notizia');
        expect(testoAlternativo('Scritto dalla redazione', 'Titolo')).toBe('Scritto dalla redazione');
    });

    it('senza candidati validi restituisce una stringa vuota, mai undefined', () => {
        expect(testoAlternativo()).toBe('');
        expect(testoAlternativo(null, undefined, {}, [])).toBe('');
    });

    it('accetta un numero', () => {
        expect(testoAlternativo(null, 2026)).toBe('2026');
    });
});
