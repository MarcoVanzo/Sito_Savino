import { describe, expect, it } from 'vitest';
import { contatoriDelPalmares, nomeCompleto } from './paginaAtleta.js';

describe('paginaAtleta', () => {
    it('compone nome e cognome senza spazi in più', () => {
        expect(nomeCompleto({ first_name: 'Ekaterina', last_name: 'Antropova' })).toBe('Ekaterina Antropova');
        expect(nomeCompleto({ first_name: '', last_name: 'Antropova' })).toBe('Antropova');
        expect(nomeCompleto(null)).toBe('');
    });

    it('mostra solo i contatori del palmarès con un numero', () => {
        expect(contatoriDelPalmares({ totals: { club: 3, individual: 1 } })).toEqual([
            { key: 'club', value: 3 },
            { key: 'individual', value: 1 },
        ]);
        expect(contatoriDelPalmares(null)).toEqual([]);
    });
});
