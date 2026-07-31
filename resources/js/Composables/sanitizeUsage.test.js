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
 * Il contenuto del CMS finisce in `v-html`: l'unica cosa che lo rende sicuro è
 * passare da `useSanitize()`. Questi due controlli tengono la regola
 * verificabile a colpo d'occhio invece che a memoria.
 */
describe('uso di v-html', () => {
    it('nessun v-html senza sanificazione', () => {
        // Anche dove il valore è innocuo — le etichette del paginator sono
        // generate da Laravel — si passa comunque da sanitize(): un'eccezione
        // "tanto è sicuro" costringe chi rilegge a rifare l'indagine ogni volta,
        // ed è così che ne passa una vera.
        const offenders = [];

        for (const file of vueFiles()) {
            const source = readFileSync(file, 'utf8');
            const matches = source.match(/v-html="([^"]+)"/g) ?? [];

            for (const match of matches) {
                const expression = match.slice('v-html="'.length, -1);
                if (!/^sanitize\(|^safe|Text$/.test(expression)) {
                    offenders.push(`${file}: ${match}`);
                }
            }
        }

        expect(offenders).toEqual([]);
    });

    it('DOMPurify si usa solo dentro useSanitize', () => {
        // In Auctions/Show.vue esisteva una seconda configurazione DOMPurify,
        // con un'allowlist diversa, senza l'hook che forza rel="noopener" e con
        // un ramo che restituiva l'HTML NON sanificato quando manca il DOM.
        // Due sanificatori divergenti sono peggio di uno solo permissivo:
        // nessuno sa quale si applica dove.
        const offenders = vueFiles().filter((file) =>
            readFileSync(file, 'utf8').includes('dompurify'),
        );

        expect(offenders).toEqual([]);
    });
});
