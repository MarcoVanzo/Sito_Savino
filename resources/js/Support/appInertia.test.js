import { describe, expect, it, vi } from 'vitest';
import { installaLeTraduzioni, routeNellaLingua, titoloDellaScheda } from './appInertia.js';

describe('appInertia', () => {
    it('il titolo del server vince su quello della pagina', () => {
        expect(titoloDellaScheda('Shop', { seo: { title: 'Shop Ufficiale | Savino Del Bene Volley' } })).toBe('Shop Ufficiale | Savino Del Bene Volley');
    });

    it('senza titolo del server aggiunge il nome del sito una volta sola', () => {
        expect(titoloDellaScheda('Shop', {})).toBe('Shop - Savino Del Bene Volley');
        expect(titoloDellaScheda('Shop — Savino Del Bene Volley', {})).toBe('Shop — Savino Del Bene Volley');
        expect(titoloDellaScheda('', {})).toBe('Savino Del Bene Volley');
    });

    it('in inglese route() sceglie la rotta en. quando esiste', () => {
        const originale = vi.fn((nome) => `/${nome}`);
        const route = routeNellaLingua(originale, 'en', (nome) => nome === 'en.contatti');

        expect(route('contatti')).toBe('/en.contatti');
        expect(route('shop')).toBe('/shop');
        expect(route('en.contatti')).toBe('/en.contatti');
    });

    it('in italiano route() resta quella data', () => {
        const route = routeNellaLingua((nome) => nome, 'it', () => true);

        expect(route('contatti')).toBe('contatti');
    });

    it('$t legge la lingua della pagina corrente', () => {
        const app = { config: { globalProperties: {} } };
        installaLeTraduzioni(app, 'it');
        const $t = app.config.globalProperties.$t;

        expect($t.call({ $page: { props: { locale: 'en' } } }, 'error.404_title')).toBe('Page Not Found');
        expect($t.call({}, 'error.404_title')).toBe('Pagina non Trovata');
    });
});
