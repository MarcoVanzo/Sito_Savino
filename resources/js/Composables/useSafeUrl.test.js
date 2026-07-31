import { describe, it, expect } from 'vitest';
import { safeUrl } from './useSafeUrl.js';

/**
 * `safeUrl` è l'unica cosa che sta fra un campo URL del CMS e un href nel DOM.
 * Un redattore (o chiunque ottenga accesso al pannello) che ci scrive
 * `javascript:...` otterrebbe esecuzione di codice nella pagina di ogni
 * visitatore.
 */
describe('safeUrl', () => {
    it('lascia passare gli URL assoluti sicuri', () => {
        expect(safeUrl('https://legavolleyfemminile.it')).toBe('https://legavolleyfemminile.it');
        expect(safeUrl('http://esempio.it/pagina')).toBe('http://esempio.it/pagina');
        expect(safeUrl('mailto:info@savinodelbenevolley.it')).toBe(
            'mailto:info@savinodelbenevolley.it',
        );
    });

    it('lascia passare percorsi interni e ancore', () => {
        expect(safeUrl('/squadra')).toBe('/squadra');
        expect(safeUrl('#calendario')).toBe('#calendario');
    });

    it('blocca javascript: in ogni forma', () => {
        expect(safeUrl('javascript:alert(1)')).toBeUndefined();
        // Maiuscole e spazi interni: `new URL` normalizza lo schema, quindi il
        // controllo regge anche su queste varianti.
        expect(safeUrl('JavaScript:alert(1)')).toBeUndefined();
        expect(safeUrl('  javascript:alert(1)  ')).toBeUndefined();
    });

    it('blocca data: e altri schemi non previsti', () => {
        expect(safeUrl('data:text/html,<script>alert(1)</script>')).toBeUndefined();
        expect(safeUrl('vbscript:msgbox(1)')).toBeUndefined();
        expect(safeUrl('file:///etc/passwd')).toBeUndefined();
    });

    it('tratta //host come assoluto, non come percorso interno', () => {
        // Un protocol-relative URL porta fuori dal sito: se passasse dal ramo
        // "inizia con /" diventerebbe un link esterno mascherato da interno.
        expect(safeUrl('//esempio.it/pagina')).toBe('//esempio.it/pagina');
    });

    it('restituisce il fallback su valori non utilizzabili', () => {
        expect(safeUrl(null, '/')).toBe('/');
        expect(safeUrl(undefined, '/')).toBe('/');
        expect(safeUrl('', '/')).toBe('/');
        expect(safeUrl('   ', '/')).toBe('/');
        expect(safeUrl(42, '/')).toBe('/');
        expect(safeUrl({}, '/')).toBe('/');
    });

    it('senza fallback esplicito restituisce undefined', () => {
        // Serve a poter omettere del tutto l'attributo href invece di
        // renderizzare un link rotto.
        expect(safeUrl('javascript:alert(1)')).toBeUndefined();
    });
});
