// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { rendiLaPagina, routeDellaPagina } from './paginaSsr.js';

/**
 * Il rendering del server SSR, senza aprire la porta: una pagina vera
 * (Error) con route() e $t della richiesta.
 */
// Le rotte vere sono quelle di config/ziggy.php: qui ogni nome risponde con
// un indirizzo uguale al nome, tranne le due che il test controlla.
const rotte = new Proxy({
    home: { uri: '/', methods: ['GET', 'HEAD'] },
    'en.home': { uri: 'en', methods: ['GET', 'HEAD'] },
}, {
    get: (bersaglio, nome) => bersaglio[nome] ?? (typeof nome === 'string' ? { uri: nome.replace(/\./g, '/'), methods: ['GET', 'HEAD'] } : undefined),
    has: () => true,
    getOwnPropertyDescriptor: (bersaglio, nome) => ({ configurable: true, enumerable: true, value: rotte[nome] }),
});

const ziggy = (location) => ({
    url: 'https://savinodelbenevolley.it',
    port: null,
    defaults: {},
    location,
    routes: rotte,
});

const pagina = (locale, location) => ({
    component: 'Error',
    url: location,
    version: null,
    props: { locale, status: 404, messaggio: null, errors: {}, ziggy: ziggy(location) },
});

describe('paginaSsr', () => {
    it('rende la pagina con il JSON in uno script e il corpo nel contenitore', async () => {
        const { head, body } = await rendiLaPagina(pagina('it', 'https://savinodelbenevolley.it/che-non-esiste'));

        expect(body).toContain('<script data-page="app" type="application/json">');
        expect(body).toMatch(/<div id="app">.*Pagina non Trovata/s);
        expect(head.join('')).toContain('Savino Del Bene Volley');
    });

    it('route() usa la lingua della richiesta', async () => {
        const { body } = await rendiLaPagina(pagina('en', 'https://savinodelbenevolley.it/en/missing'));

        expect(body).toContain('href="https://savinodelbenevolley.it/en"');
        expect(body).toContain('Page Not Found');
    });

    it('due richieste in parallelo tengono ciascuna i suoi link', async () => {
        const [it_, en] = await Promise.all([
            rendiLaPagina(pagina('it', 'https://savinodelbenevolley.it/a')),
            rendiLaPagina(pagina('en', 'https://savinodelbenevolley.it/en/b')),
        ]);

        expect(it_.body).toContain('href="https://savinodelbenevolley.it/"');
        expect(it_.body).not.toContain('/en"');
        expect(en.body).toContain('href="https://savinodelbenevolley.it/en"');
    });

    it('route() di una pagina e\' quella della sua configurazione', () => {
        const route = routeDellaPagina(pagina('en', 'https://savinodelbenevolley.it/en/b'));

        expect(route('home', {}, false)).toBe('/en');
    });

    it('sanifica l\'HTML anche sul server', async () => {
        const { useSanitize } = await import('./Composables/useSanitize.js');

        expect(useSanitize().sanitize('<p onclick="x()">ciao</p><script>alert(1)</script>')).toBe('<p>ciao</p>');
    });
});
