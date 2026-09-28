import { describe, expect, it } from 'vitest';
import { DURATA_IN_CORSO_MS, tempoMancante } from './conteggioAllaPartita.js';

const INIZIO = '2026-10-04T20:30:00+02:00';
const inizioMs = new Date(INIZIO).getTime();

describe('tempoMancante', () => {
    it('scompone giorni, ore e minuti', () => {
        const adesso = inizioMs - (2 * 24 * 60 + 3 * 60 + 15) * 60 * 1000;

        expect(tempoMancante(INIZIO, adesso)).toEqual({ stato: 'prima', giorni: 2, ore: 3, minuti: 15 });
    });

    it('arrotonda i minuti per eccesso a ridosso del fischio', () => {
        expect(tempoMancante(INIZIO, inizioMs - 30 * 1000)).toEqual({ stato: 'prima', giorni: 0, ore: 0, minuti: 1 });
    });

    it('dice in corso per tre ore dall\'inizio, poi niente', () => {
        expect(tempoMancante(INIZIO, inizioMs)).toEqual({ stato: 'in_corso' });
        expect(tempoMancante(INIZIO, inizioMs + DURATA_IN_CORSO_MS - 1)).toEqual({ stato: 'in_corso' });
        expect(tempoMancante(INIZIO, inizioMs + DURATA_IN_CORSO_MS)).toEqual({ stato: 'nessuno' });
    });

    it('senza una data valida non mostra niente', () => {
        expect(tempoMancante(null)).toEqual({ stato: 'nessuno' });
        expect(tempoMancante('non è una data')).toEqual({ stato: 'nessuno' });
    });
});
