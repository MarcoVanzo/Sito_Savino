import { describe, it, expect } from 'vitest';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

const ROOT = 'resources/js';

function vueFiles(dir = ROOT, found = []) {
    for (const entry of readdirSync(dir)) {
        const path = join(dir, entry);
        if (statSync(path).isDirectory()) {
            vueFiles(path, found);
        } else if (entry.endsWith('.vue')) {
            found.push(path);
        }
    }
    return found;
}

/**
 * I file caricati dal pannello stanno sul disco configurato: in produzione è
 * Spaces, non la cartella pubblica del server. Comporre "/storage/{percorso}"
 * nel template dà un indirizzo che funziona solo in locale — l'URL lo prepara
 * il backend (App\Support\CmsFile) e il componente si limita a usarlo.
 */
describe('percorsi dei file caricati dal CMS', () => {
    it('nessun componente costruisce a mano un percorso /storage', () => {
        const offenders = [];

        for (const file of vueFiles()) {
            const source = readFileSync(file, 'utf8');

            if (/["'`]\/storage\/\$\{/.test(source) || /["'`]\/storage\/["'`]\s*\+/.test(source)) {
                offenders.push(file);
            }
        }

        expect(offenders).toEqual([]);
    });
});
