import { describe, expect, it } from 'vitest';
import { datiDaInviare, descrittoDa, descrizioniScelte, mostraLaLista, righeSelezionabili } from './righeDiRecesso.js';

const righe = [
    { id: 1, descrizione: '1 × Maglia home (M)', personalizzata: false },
    { id: 2, descrizione: '1 × Maglia away (L) + Firma della giocatrice', personalizzata: true },
    { id: 3, descrizione: '2 × Sciarpa', personalizzata: false },
];

describe('righeDiRecesso', () => {
    it('parte con tutte le righe restituibili, mai con le personalizzate', () => {
        expect(righeSelezionabili(righe)).toEqual([1, 3]);
        expect(righeSelezionabili(null)).toEqual([]);
    });

    it('mostra la lista solo per il numero con cui la pagina è stata aperta', () => {
        expect(mostraLaLista(righe, 'SDB-1', ' SDB-1 ')).toBe(true);
        expect(mostraLaLista(righe, 'SDB-1', 'SDB-2')).toBe(false);
        expect(mostraLaLista(null, 'SDB-1', 'SDB-1')).toBe(false);
        expect(mostraLaLista([], 'SDB-1', 'SDB-1')).toBe(false);
    });

    it('nel riepilogo non entra una riga personalizzata anche se scelta', () => {
        expect(descrizioniScelte(righe, [2, 3])).toEqual(['2 × Sciarpa']);
    });

    it('senza lista non manda righe né token', () => {
        const dati = { nome: 'Maria', articoli: 'una maglia', righe: [1], token: 'abc' };

        expect(datiDaInviare(dati, false)).toEqual({ nome: 'Maria', articoli: 'una maglia', righe: null, token: null });
        expect(datiDaInviare(dati, true)).toEqual({ nome: 'Maria', articoli: '', righe: [1], token: 'abc' });
    });

    it('aria-describedby unisce errore e aiuto', () => {
        expect(descrittoDa('err', 'aiuto')).toBe('err aiuto');
        expect(descrittoDa(undefined, 'aiuto')).toBe('aiuto');
        expect(descrittoDa(null, undefined)).toBeUndefined();
    });
});
