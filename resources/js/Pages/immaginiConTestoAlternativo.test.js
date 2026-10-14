import { describe, it, expect } from 'vitest';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

/**
 * Ogni `<img>` del sito esce con l'attributo `alt`, anche quando il dato
 * manca.
 *
 * La regola `vuejs-accessibility/alt-text` di ESLint vede solo che l'attributo
 * e' scritto nel template: `:alt="post.title"` la soddisfa, ma con il titolo
 * vuoto o assente Vue toglie l'attributo dall'HTML. L'audit SEO del 14/10/2026
 * contava cosi' 92 immagini senza alt su 179 in home. Qui si guarda cosa c'e'
 * dentro il binding: un valore che puo' mancare deve passare da
 * `testoAlternativo()` (`@/Support/testoAlternativo.js`) o finire con `|| ''`.
 */
const RADICE = 'resources/js';

function componenti(dir = RADICE, trovati = []) {
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

/** I tag `<img ...>` del template, con le virgolette rispettate (`=>` negli handler). */
function tagImg(sorgente) {
    const template = sorgente.replace(/<script[\s\S]*?<\/script>/g, '').replace(/<!--[\s\S]*?-->/g, '');
    const tag = [];
    let da = 0;

    while ((da = template.indexOf('<img', da)) !== -1) {
        if (!/[\s/>]/.test(template[da + 4] ?? '')) {
            da += 4;
            continue;
        }

        let i = da + 4;
        let virgolette = null;

        for (; i < template.length; i++) {
            const c = template[i];

            if (virgolette) {
                if (c === virgolette) virgolette = null;
            } else if (c === '"' || c === "'") {
                virgolette = c;
            } else if (c === '>') {
                break;
            }
        }

        tag.push(template.slice(da, i + 1));
        da = i + 1;
    }

    return tag;
}

const LETTERALE = /^(?:'[^']*'|`[^`]*`)$/;
const SEMPRE_STRINGA = [
    /^testo\w*\(.*\)$/s,
    /^\$t\(.*\)$/s,
    /\|\|\s*''$/,
    /\?\?\s*''$/,
    /\|\|\s*\$t\([^()]*\)$/,
    /\|\|\s*`[^`]*`$/,
    /\.join\('[^']*'\)$/,
];

/** Il problema dell'alt di un tag, o null se va bene. */
function problemaDellAlt(tag) {
    const statico = tag.match(/\salt="([^"]*)"/);
    const legato = tag.match(/\s(?::|v-bind:)alt="([^"]*)"/);

    if (legato) {
        const espressione = legato[1].trim();

        return LETTERALE.test(espressione) || SEMPRE_STRINGA.some((r) => r.test(espressione))
            ? null
            : `:alt="${espressione}" puo' essere vuoto: usare testoAlternativo(...)`;
    }

    return statico ? null : 'senza alt';
}

describe('le immagini hanno sempre un testo alternativo', () => {
    const files = componenti();

    it('trova i componenti con immagini', () => {
        expect(files.filter((f) => tagImg(readFileSync(f, 'utf8')).length > 0).length).toBeGreaterThan(30);
    });

    it('riconosce i binding che possono sparire', () => {
        expect(problemaDellAlt('<img :src="a" :alt="post.title" />')).not.toBeNull();
        expect(problemaDellAlt('<img :src="a" />')).toBe('senza alt');
        expect(problemaDellAlt('<img :src="a" :alt="testoAlternativo(post.image_alt, post.title)" />')).toBeNull();
        expect(problemaDellAlt('<img :src="a" :alt="post.title || \'\'" @error="(e) => x(e)" />')).toBeNull();
        expect(problemaDellAlt('<img src="a" alt="" />')).toBeNull();
    });

    it.each(files)('%s', (file) => {
        const problemi = tagImg(readFileSync(file, 'utf8'))
            .map((tag) => [problemaDellAlt(tag), tag.replace(/\s+/g, ' ').slice(0, 140)])
            .filter(([problema]) => problema !== null);

        expect(problemi).toEqual([]);
    });
});
