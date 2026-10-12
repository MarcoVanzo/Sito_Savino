import { describe, it, expect } from 'vitest';
import { contenutoInOnda, scartoDellOrologio, statoPiuRecente } from './cicloDellaCornice.js';

const t = (hhmmss) => Date.parse(`2026-10-18T${hhmmss}+02:00`);
const cornice = (id, durata = 15) => ({ id, url: `https://cdn/c${id}.png`, durata });

const base = {
    versione: 3,
    stato: 'in_onda',
    dalle: null,
    fino_alle: null,
    ciclo: { attivo: true, inizio: '2026-10-18T17:05:00+02:00', fine: '2026-10-18T20:00:00+02:00', ogni_minuti: 10, durata_secondi: 15 },
    contenuto: null,
    playlist: [cornice(1), cornice(2), cornice(3)],
};

const oggi = '2026-10-18';

describe('cicloDellaCornice', () => {
    it('prima dell\'inizio del ciclo nessuno sponsor', () => {
        expect(contenutoInOnda(base, t('17:04:59'), oggi)).toBeNull();
    });

    it('il ciclo k mostra la k-esima cornice per i secondi decisi', () => {
        expect(contenutoInOnda(base, t('17:05:00'), oggi).id).toBe(1);
        expect(contenutoInOnda(base, t('17:05:14'), oggi).id).toBe(1);
        expect(contenutoInOnda(base, t('17:05:15'), oggi)).toBeNull();
        expect(contenutoInOnda(base, t('17:15:03'), oggi).id).toBe(2);
        expect(contenutoInOnda(base, t('17:25:03'), oggi).id).toBe(3);
        // la rotazione ricomincia
        expect(contenutoInOnda(base, t('17:35:03'), oggi).id).toBe(1);
    });

    it('nessun ciclo comincia dopo la fine', () => {
        expect(contenutoInOnda(base, t('19:55:05'), oggi).id).toBeDefined();
        expect(contenutoInOnda(base, t('20:05:05'), oggi)).toBeNull();
    });

    it('ciclo sospeso o sponsor spenti: niente', () => {
        expect(contenutoInOnda({ ...base, ciclo: { ...base.ciclo, attivo: false } }, t('17:05:01'), oggi)).toBeNull();
        expect(contenutoInOnda({ ...base, stato: 'inattiva' }, t('17:05:01'), oggi)).toBeNull();
    });

    it('lo spot della regia prevale sul ciclo e finisce da solo', () => {
        const pausa = { ...base, stato: 'pausa', dalle: '2026-10-18T17:08:00+02:00', fino_alle: '2026-10-18T17:11:00+02:00', contenuto: cornice(9) };

        expect(contenutoInOnda(pausa, t('17:09:00'), oggi).id).toBe(9);
        // finita la pausa torna il ciclo (17:11 è fuori dalla finestra del ciclo 0)
        expect(contenutoInOnda(pausa, t('17:11:00'), oggi)).toBeNull();
        expect(contenutoInOnda(pausa, t('17:15:01'), oggi).id).toBe(2);
    });

    it('nella pausa tra i set le cornici ruotano dalla stessa ora per tutti', () => {
        const pausa = { ...base, stato: 'pausa', dalle: '2026-10-18T17:08:00+02:00', fino_alle: '2026-10-18T17:11:00+02:00' };

        expect(contenutoInOnda(pausa, t('17:08:00'), oggi).id).toBe(1);
        expect(contenutoInOnda(pausa, t('17:08:15'), oggi).id).toBe(2);
        expect(contenutoInOnda(pausa, t('17:08:44'), oggi).id).toBe(3);
        expect(contenutoInOnda(pausa, t('17:08:45'), oggi).id).toBe(1);
    });

    it('le cornici scadute escono dalla rotazione', () => {
        const stato = { ...base, playlist: [cornice(1), { ...cornice(2), al: '2026-10-17' }] };

        expect(contenutoInOnda(stato, t('17:15:01'), oggi).id).toBe(1);
    });

    it('lo scarto dell\'orologio usa il punto di mezzo della richiesta', () => {
        expect(scartoDellOrologio(10_500, 1_000, 2_000)).toBe(9_000);
        expect(scartoDellOrologio(NaN, 1_000, 2_000)).toBe(0);
    });

    it('un JSON con versione più bassa o rotto non sostituisce quello che c\'è', () => {
        expect(statoPiuRecente(base, { ...base, versione: 2 })).toBe(base);
        expect(statoPiuRecente(base, { nulla: true })).toBe(base);
        expect(statoPiuRecente(base, { ...base, versione: 4 }).versione).toBe(4);
        expect(statoPiuRecente(null, base)).toBe(base);
    });
});
