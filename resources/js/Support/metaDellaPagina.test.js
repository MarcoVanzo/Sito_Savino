import { describe, expect, it } from 'vitest';
import { metaDellaPagina, titoloDellaPagina } from './metaDellaPagina.js';

const ripiego = {
    title: 'Sito Ufficiale — Savino Del Bene Volley',
    description: 'Descrizione del client',
    image: '/images/logo.png',
    type: 'website',
};

describe('metaDellaPagina', () => {
    it('usa il titolo del server così com\'è', () => {
        const props = { seo: { title: 'Savino Del Bene Volley — Sito Ufficiale', description: 'Dal server', image: null, type: 'website' } };

        expect(titoloDellaPagina(props)).toBe('Savino Del Bene Volley — Sito Ufficiale');
        expect(metaDellaPagina(props, ripiego).title).toBe('Savino Del Bene Volley — Sito Ufficiale');
        expect(metaDellaPagina(props, ripiego).description).toBe('Dal server');
    });

    it('tiene l\'immagine della pagina se il server non ne ha una', () => {
        const props = { seo: { title: 'Notizia | Savino Del Bene Volley', description: '', image: null, type: 'article' } };

        const meta = metaDellaPagina(props, ripiego);

        expect(meta.image).toBe('/images/logo.png');
        expect(meta.description).toBe('Descrizione del client');
        expect(meta.type).toBe('article');
    });

    it('senza meta del server ripiega su quelli della pagina', () => {
        expect(titoloDellaPagina({ seo: null })).toBeNull();
        expect(titoloDellaPagina(undefined)).toBeNull();
        expect(metaDellaPagina({}, ripiego)).toBe(ripiego);
    });
});
