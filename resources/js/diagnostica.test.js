import { beforeEach, describe, expect, it, vi } from 'vitest';

const init = vi.fn();
vi.mock('@sentry/vue', () => ({ init }));

const { avviaLaDiagnostica, opzioniDiSentry, TUNNEL } = await import('./diagnostica.js');

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
});
