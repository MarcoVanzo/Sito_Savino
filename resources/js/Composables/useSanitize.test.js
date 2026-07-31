import { describe, it, expect } from 'vitest';
import { useSanitize } from './useSanitize.js';

const { sanitize } = useSanitize();

/**
 * Il contenuto delle pagine editoriali arriva dal CMS e finisce in `v-html`.
 * Questa allowlist è ciò che impedisce a quel contenuto di eseguire codice:
 * i test bloccano sia gli allargamenti accidentali sia le restrizioni che
 * romperebbero l'editor (grassetti, tabelle, immagini).
 */
describe('useSanitize', () => {
    it('conserva il markup redazionale legittimo', () => {
        const html =
            '<h2>Titolo</h2><p><strong>Grassetto</strong> e <em>corsivo</em></p>' +
            '<ul><li>Voce</li></ul><table><tr><td colspan="2">Cella</td></tr></table>';
        const clean = sanitize(html);

        expect(clean).toContain('<h2>Titolo</h2>');
        expect(clean).toContain('<strong>Grassetto</strong>');
        expect(clean).toContain('<li>Voce</li>');
        expect(clean).toContain('colspan="2"');
    });

    it('rimuove gli script', () => {
        const clean = sanitize('<p>Testo</p><script>alert(1)</script>');

        expect(clean).toContain('<p>Testo</p>');
        expect(clean).not.toContain('script');
        expect(clean).not.toContain('alert');
    });

    it('rimuove gli handler inline', () => {
        const clean = sanitize('<img src="x" onerror="alert(1)"><p onclick="alert(2)">Ciao</p>');

        expect(clean).not.toContain('onerror');
        expect(clean).not.toContain('onclick');
        expect(clean).toContain('Ciao');
    });

    it('rimuove iframe e object, che non sono nell\'allowlist', () => {
        const clean = sanitize('<iframe src="https://esempio.it"></iframe><object data="x"></object>');

        expect(clean).not.toContain('iframe');
        expect(clean).not.toContain('object');
    });

    it('rimuove gli attributi data-*', () => {
        // ALLOW_DATA_ATTR è false: gli attributi data-* alimentano comportamenti
        // JS del sito e non devono essere pilotabili dal contenuto.
        const clean = sanitize('<div data-controller="admin">Testo</div>');

        expect(clean).not.toContain('data-controller');
        expect(clean).toContain('Testo');
    });

    it('aggiunge rel="noopener noreferrer" ai link target="_blank"', () => {
        // Senza rel, la pagina aperta può manipolare quella di partenza
        // tramite window.opener.
        const clean = sanitize('<a href="https://esempio.it" target="_blank">Link</a>');

        expect(clean).toContain('rel="noopener noreferrer"');
    });

    it('restituisce stringa vuota sui valori assenti', () => {
        expect(sanitize('')).toBe('');
        expect(sanitize(null)).toBe('');
        expect(sanitize(undefined)).toBe('');
    });
});
