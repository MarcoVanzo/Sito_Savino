import { describe, it, expect } from 'vitest';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

import italiano from './it.json';
import inglese from './en.json';

const ROOT = 'resources/js';

/**
 * Una chiave assente non fa rumore: `$t()` restituisce la chiave stessa, che
 * finisce a schermo in maiuscolo per via del `text-transform` dei titoli.
 * È successo togliendo i testi di ripiego (`$t('x') || 'Testo'`) dai componenti:
 * la pagina Safeguarding mostrava "SOCIETA.SAFEGUARDING_DOCUMENTS_HEADER" al
 * posto dell'intestazione, e i dati societari in Contatti erano tutti così.
 *
 * Questo test guarda le chiavi scritte per esteso — le uniche verificabili
 * staticamente — in entrambe le lingue.
 */
function sourceFiles(dir = ROOT, found = []) {
    for (const entry of readdirSync(dir)) {
        const path = join(dir, entry);

        if (statSync(path).isDirectory()) {
            sourceFiles(path, found);
        } else if ((entry.endsWith('.vue') || entry.endsWith('.js')) && !entry.endsWith('.test.js')) {
            found.push(path);
        }
    }

    return found;
}

function has(dictionary, key) {
    return key.split('.').reduce((node, part) => (node && typeof node === 'object' ? node[part] : undefined), dictionary) !== undefined;
}

function usedKeys() {
    const found = new Map();

    for (const file of sourceFiles()) {
        const source = readFileSync(file, 'utf8');
        // Solo le chiavi letterali: `$t('palmares.group_' + categoria)` è
        // composta a runtime e qui si vedrebbe come un prefisso monco.
        const matches = source.matchAll(/\$t\(\s*'([a-z0-9_.]+)'\s*[),]/gi);

        for (const match of matches) {
            if (!found.has(match[1])) {
                found.set(match[1], file);
            }
        }
    }

    return found;
}

describe('chiavi di traduzione usate nei componenti', () => {
    const keys = usedKeys();

    it('trova le chiavi da controllare', () => {
        expect(keys.size).toBeGreaterThan(100);
    });

    it('esistono tutte in italiano', () => {
        const missing = [...keys].filter(([key]) => !has(italiano, key)).map(([key, file]) => `${key} (${file})`);

        expect(missing).toEqual([]);
    });

    it('esistono tutte in inglese', () => {
        const missing = [...keys].filter(([key]) => !has(inglese, key)).map(([key, file]) => `${key} (${file})`);

        expect(missing).toEqual([]);
    });
});
