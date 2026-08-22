import { describe, it, expect } from 'vitest';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const PAGINE = 'resources/js/Pages/Public';

/**
 * I contenuti delle pagine arrivano dal pannello, punto.
 *
 * Scrivere `cd.hero_title || $t('sezione.hero_title')` sembra prudente e
 * invece nasconde il guasto: online si legge un testo che in redazione non
 * esiste, e nessuno si accorge che il campo è vuoto. È così che la pagina
 * Sponsor è rimasta senza la copia inglese per giorni — `/en/sponsor`
 * mostrava "Diventa Partner" — e che `title-sponsor` sembrava a posto con
 * `content_data` ridotto a una stringa vuota.
 *
 * I valori iniziali di un template stanno in
 * `database/data/page_template_defaults.php`, non nel componente: da lì
 * finiscono in archivio e la redazione li trova e li cambia.
 */
function componenti(dir = PAGINE, trovati = []) {
    for (const voce of readdirSync(dir)) {
        const percorso = join(dir, voce);

        if (statSync(percorso).isDirectory()) {
            componenti(percorso, trovati);
        } else if (voce.endsWith('.vue')) {
            trovati.push(percorso);
        }
    }

    return trovati;
}

describe('i contenuti delle pagine vengono dal backend', () => {
    const files = componenti();

    it('trova i componenti da controllare', () => {
        expect(files.length).toBeGreaterThan(20);
    });

    // Ripiegare su un'altra fonte del backend va bene — `cd.contact_email ||
    // contact.email` legge comunque le impostazioni. Quello che non va è
    // ripiegare su un testo che vive nel codice: una traduzione o una stringa
    // scritta lì.
    it.each(files)('%s non ripiega su testi scritti nel codice', (file) => {
        const sorgente = readFileSync(file, 'utf8');
        const ripieghi = [...sorgente.matchAll(/cd\.[a-zA-Z_0-9]+\s*(?:\|\||\?\?)\s*(?:\$t\('[a-z0-9_.]+'\)|'[^']*')/g)];

        expect(ripieghi.map((m) => m[0])).toEqual([]);
    });
});
