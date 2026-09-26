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

    it('porta il focus sul primo h1 del contenuto e non ripete il titolo nella regione', () => {
        const router = routerFinto();
        annunciaIlCambioDiPagina(router);
        document.getElementById('link').focus();

        // Il componente nuovo e' gia' montato quando scatta il timer.
        document.getElementById('contenuto').innerHTML = '<h1 id="titolo">News</h1><h1>Secondo</h1>';
        document.title = 'News — Savino Del Bene Volley';
        router.naviga('/news');
        vi.runAllTimers();

        const titolo = document.getElementById('titolo');
        expect(document.activeElement).toBe(titolo);
        expect(titolo.getAttribute('tabindex')).toBe('-1');
        // Un annuncio solo: lo legge il focus, la regione resta muta.
        expect(document.querySelector('[data-annuncio-pagina]').textContent).toBe('');
    });

    it('non tocca il tabindex di un h1 che ne ha gia\' uno', () => {
        const router = routerFinto();
        annunciaIlCambioDiPagina(router);

        document.getElementById('contenuto').innerHTML = '<h1 id="titolo" tabindex="0">News</h1>';
        router.naviga('/news');
        vi.runAllTimers();

        expect(document.getElementById('titolo').getAttribute('tabindex')).toBe('0');
        expect(document.activeElement.id).toBe('titolo');
    });

    it('ripiega sul main e legge il titolo se l\'h1 non prende il focus', () => {
        const router = routerFinto();
        annunciaIlCambioDiPagina(router);

        document.getElementById('contenuto').innerHTML = '<h1 id="titolo">News</h1>';
        const titolo = document.getElementById('titolo');
        // Come un titolo con display:none: focus() non ha effetto.
        titolo.focus = () => {};
        document.title = 'News — Savino Del Bene Volley';
        router.naviga('/news');
        vi.runAllTimers();

        expect(document.activeElement.id).toBe('contenuto');
        expect(document.querySelector('[data-annuncio-pagina]').textContent).toBe('News — Savino Del Bene Volley');
    });

    it('senza h1 porta il focus al contenuto e legge il titolo nuovo', () => {
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
