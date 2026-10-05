import { beforeEach, describe, expect, it, vi } from 'vitest';

const init = vi.fn();
vi.mock('@sentry/vue', () => ({ init }));
const ricarica = vi.hoisted(() => ({ inCorso: false }));
vi.mock('./Support/ricaricaDopoIlRilascio.js', () => ({ ricaricaInCorso: () => ricarica.inCorso }));

const { avviaLaDiagnostica, opzioniDiSentry, ripulisciIndirizzo, TUNNEL, vieneDaCodiceIniettato } =
    await import('./diagnostica.js');

describe('diagnostica', () => {
    beforeEach(() => init.mockClear());

    it('senza DSN non carica niente', async () => {
        expect(await avviaLaDiagnostica({}, { dsn: null })).toBe(false);
        expect(await avviaLaDiagnostica({}, undefined)).toBe(false);
        expect(init).not.toHaveBeenCalled();
    });

    it('con il DSN avvia Sentry passando dal tunnel del sito', async () => {
        const app = {};

        expect(
            await avviaLaDiagnostica(app, {
                dsn: 'https://k@o1.ingest.de.sentry.io/2',
                environment: 'production',
            }),
        ).toBe(true);

        const opzioni = init.mock.calls[0][0];
        expect(opzioni.app).toBe(app);
        expect(opzioni.tunnel).toBe(TUNNEL);
        expect(opzioni.environment).toBe('production');
    });

    it('non manda dati personali né segnali di sessione', () => {
        // È ciò che l'informativa promette: niente IP, niente sessioni, e
        // quindi nessun consenso da chiedere.
        const opzioni = opzioniDiSentry({
            app: {},
            dsn: 'x',
            environment: 'test',
            origine: 'https://sito.test',
        });

        expect(opzioni.sendDefaultPii).toBe(false);
        expect(opzioni.integrations([{ name: 'BrowserSession' }, { name: 'Breadcrumbs' }])).toEqual(
            [{ name: 'Breadcrumbs' }],
        );
    });

    it('ascolta solo gli script del sito', () => {
        // Estensioni, GA4 e Pixel non sono guasti nostri, e ogni issue nuova
        // diventa un'email in allarmi@.
        const opzioni = opzioniDiSentry({
            app: {},
            dsn: 'x',
            environment: 'test',
            origine: 'https://sito.test',
        });

        expect(opzioni.allowUrls).toEqual(['https://sito.test']);
    });

    it('non manda token ed email contenuti negli indirizzi', () => {
        expect(ripulisciIndirizzo('https://sito.test/reset-password/abc123?email=a%40b.it')).toBe(
            'https://sito.test/reset-password/[nascosto]',
        );
        expect(ripulisciIndirizzo('/shop/checkout/conferma/9f8e?token=PAYPAL&PayerID=X')).toBe(
            '/shop/checkout/conferma/[nascosto]',
        );
        expect(ripulisciIndirizzo('/en/shop/order/tok/receipt')).toBe('/en/shop/order/[nascosto]/receipt');
        expect(ripulisciIndirizzo('/shop/checkout/asta/uuid-1/annullato')).toBe('/shop/checkout/asta/[nascosto]/annullato');
        expect(ripulisciIndirizzo('/verify-email/12/0123456789abcdef0123456789abcdef')).toBe('/verify-email/[nascosto]');
        // Il «riprova» è una POST col token dell'ordine: finisce nei breadcrumb XHR.
        expect(ripulisciIndirizzo('/shop/checkout/retry/9f8e-uuid')).toBe('/shop/checkout/retry/[nascosto]');
        expect(ripulisciIndirizzo('/news/una-notizia')).toBe('/news/una-notizia');
    });

    it('ripulisce URL della pagina e breadcrumb prima di spedire', () => {
        const opzioni = opzioniDiSentry({ app: {}, dsn: 'x', environment: 'test', origine: 'https://sito.test' });

        const evento = opzioni.beforeSend({
            request: { url: 'https://sito.test/reset-password/abc?email=x', query_string: 'email=x', headers: { Referer: 'r' } },
        });
        expect(evento.request).toEqual({ url: 'https://sito.test/reset-password/[nascosto]', headers: {} });

        const briciola = opzioni.beforeBreadcrumb({ category: 'navigation', data: { from: '/shop/ordine/t/ricevuta', to: '/?p=1' } });
        expect(briciola.data).toEqual({ from: '/shop/ordine/[nascosto]/ricevuta', to: '/' });
    });

    it('non allega le props dei componenti Vue', () => {
        const opzioni = opzioniDiSentry({ app: {}, dsn: 'x', environment: 'test', origine: 'https://sito.test' });

        expect(opzioni.attachProps).toBe(false);

        const evento = opzioni.beforeSend({
            contexts: { vue: { componentName: 'Checkout', propsData: { email: 'a@b.it' } } },
        });
        expect(evento.contexts.vue).toEqual({ componentName: 'Checkout' });
    });

    it('scarta gli errori di codice iniettato nella pagina, non i nostri', () => {
        // Sentry, 3/10/2026: RangeError «dalla riga 190» di una notizia il cui
        // HTML ha 91 righe, da Chrome iOS su un IP DigitalOcean.
        const pagina = '/news/una-notizia';
        const iniettato = {
            exception: {
                values: [
                    {
                        stacktrace: {
                            frames: [
                                { filename: pagina, lineno: 226 },
                                { filename: pagina, lineno: 190 },
                            ],
                        },
                    },
                ],
            },
        };
        const nostro = {
            exception: {
                values: [
                    {
                        stacktrace: {
                            frames: [
                                { filename: pagina },
                                { filename: 'https://sito.test/build/assets/app-OT06btD8.js?v=1' },
                            ],
                        },
                    },
                ],
            },
        };

        expect(vieneDaCodiceIniettato(iniettato)).toBe(true);
        expect(vieneDaCodiceIniettato(nostro)).toBe(false);
        expect(vieneDaCodiceIniettato({ exception: { values: [{}] } })).toBe(false);
        expect(vieneDaCodiceIniettato({ message: 'x' })).toBe(false);

        const opzioni = opzioniDiSentry({
            app: {},
            dsn: 'x',
            environment: 'test',
            origine: 'https://sito.test',
        });
        expect(opzioni.beforeSend(iniettato)).toBeNull();
        expect(opzioni.beforeSend(nostro)).toBe(nostro);
    });

    it('scarta gli errori mentre la pagina si ricarica dopo un rilascio', () => {
        const opzioni = opzioniDiSentry({ app: {}, dsn: 'x', environment: 'test', origine: 'https://sito.test' });
        const evento = { exception: { values: [{ stacktrace: { frames: [{ filename: 'https://sito.test/build/assets/vendor.js' }] } }] } };
        expect(opzioni.beforeSend(evento)).toBe(evento);
        ricarica.inCorso = true;
        expect(opzioni.beforeSend(evento)).toBeNull();
        ricarica.inCorso = false;
    });
});
