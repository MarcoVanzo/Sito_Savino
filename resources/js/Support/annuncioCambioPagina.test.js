import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { annunciaIlCambioDiPagina } from './annuncioCambioPagina.js';

function routerFinto() {
    const ascoltatori = [];
    return {
        on: (_evento, fn) => { ascoltatori.push(fn); return () => {}; },
        naviga: (url) => ascoltatori.forEach((fn) => fn({ detail: { page: { url } } })),
    };
}

describe('annunciaIlCambioDiPagina', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        document.body.innerHTML = '<main id="contenuto" tabindex="-1"></main><button id="link">x</button>';
        document.title = 'Home';
    });

    afterEach(() => vi.useRealTimers());

    it('porta il focus al contenuto e legge il titolo nuovo', () => {
        const router = routerFinto();
        annunciaIlCambioDiPagina(router);
        document.getElementById('link').focus();

        document.title = 'News — Savino Del Bene Volley';
        router.naviga('/news');
        vi.runAllTimers();

        expect(document.activeElement.id).toBe('contenuto');
        expect(document.querySelector('[data-annuncio-pagina]').textContent).toBe('News — Savino Del Bene Volley');
    });

    it('non sposta il focus se cambia solo la query (filtri, paginazione)', () => {
        const router = routerFinto();
        annunciaIlCambioDiPagina(router);
        router.naviga('/news');
        vi.runAllTimers();

        document.getElementById('link').focus();
        router.naviga('/news?page=2');
        vi.runAllTimers();

        expect(document.activeElement.id).toBe('link');
    });

    it('annuncia anche una pagina con lo stesso titolo della precedente', () => {
        const router = routerFinto();
        annunciaIlCambioDiPagina(router);
        const regione = document.querySelector('[data-annuncio-pagina]');

        router.naviga('/news/uno');
        vi.runAllTimers();
        expect(regione.textContent).toBe('Home');

        router.naviga('/news/due');
        expect(regione.textContent).toBe('');
        vi.runAllTimers();
        expect(regione.textContent).toBe('Home');
    });
});
