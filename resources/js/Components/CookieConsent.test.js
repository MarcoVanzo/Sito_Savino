import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { leggiIlConsenso, salvaIlConsenso, mettiInAttesa, consensoInAttesa, CHIAVE_CONSENSO } from '../consenso.js';

const VERSIONE = '2026-09-22';

const aggiornaStatistiche = vi.fn();
const aggiornaMarketing = vi.fn();

// I due che accendono e spengono davvero la misurazione. Sono mock perché
// GA4 e il Pixel non si caricano in un test, ma *che* vengano chiamati, e con
// quale valore, è esattamente ciò che questi test devono provare: fino a
// settembre 2026 il banner raccoglieva una scelta che poi non pilotava nulla.
vi.mock('../analytics.js', () => ({ updateAnalyticsConsent: (...a) => aggiornaStatistiche(...a) }));
vi.mock('../meta-pixel.js', () => ({ updateMarketingConsent: (...a) => aggiornaMarketing(...a) }));

// Le props della pagina Inertia, che i test cambiano: la versione
// dell'informativa arriva da lì, e va potuta togliere.
const pagina = vi.hoisted(() => ({ props: {} }));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => pagina,
    Link: { template: '<a><slot /></a>' },
}));

import CookieConsent from './CookieConsent.vue';

/** Se il pannello delle caselle è aperto: `v-show` spegne con `display: none`. */
function pannelloAperto(banner) {
    let elemento = banner.find('#cookie-analytics').element;

    while (elemento && elemento.tagName) {
        if (elemento.style.display === 'none') {
            return false;
        }

        elemento = elemento.parentElement;
    }

    return true;
}

function montaIlBanner() {
    return mount(CookieConsent, {
        global: {
            mocks: { $t: (chiave) => chiave, route: (nome) => `/${nome}` },
        },
    });
}

/**
 * Il banner dei cookie.
 *
 * Non è un componente come gli altri: è il punto in cui una scelta del
 * visitatore diventa il comportamento del sito. Un difetto qui non si vede —
 * la pagina resta identica — e vale un tracker che parte senza consenso.
 *
 * La logica della lettura e della scrittura sta in `consenso.js` (§21 del
 * CLAUDE.md) e qui NON è simulata: si prova il giro vero, dal click al
 * `localStorage`. Simulati sono solo i due tracker e la chiamata al registro.
 */
describe('CookieConsent', () => {
    beforeEach(() => {
        localStorage.clear();
        aggiornaStatistiche.mockClear();
        aggiornaMarketing.mockClear();
        window.axios = { post: vi.fn().mockResolvedValue({ data: {} }) };
        globalThis.route = (nome) => `/${nome}`;
        pagina.props = { consensoCookie: { versione: VERSIONE }, locale: 'it' };
        // Le scelte scadono dopo dodici mesi (consenso.js): con l'orologio vero,
        // i consensi datati settembre 2026 di questi test scadrebbero da soli
        // nel settembre 2027. Si ferma solo Date, non i timer del banner.
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-09-25T12:00:00Z'));
    });

    afterEach(() => {
        delete globalThis.route;
        vi.useRealTimers();
    });

    it('la X chiude il banner rifiutando', async () => {
        const banner = montaIlBanner();
        await flushPromises();

        await banner.find('button[aria-label="cookie.close_reject"]').trigger('click');

        expect(leggiIlConsenso(VERSIONE)).toMatchObject({ scelto: true, statistiche: false, marketing: false });
        expect(aggiornaStatistiche).toHaveBeenLastCalledWith(false);
        expect(aggiornaMarketing).toHaveBeenLastCalledWith(false);
        expect(banner.text()).not.toContain('cookie.title');
    });

    it('una scelta più vecchia di dodici mesi si richiede, e intanto non misura', async () => {
        salvaIlConsenso({ statistiche: true, marketing: true, versione: VERSIONE, data: '2025-09-01T10:00:00.000Z' });

        const banner = montaIlBanner();
        await flushPromises();

        expect(banner.text()).toContain('cookie.title');
        expect(aggiornaStatistiche).toHaveBeenCalledWith(false);
        expect(aggiornaMarketing).toHaveBeenCalledWith(false);
    });

    it('a chi non ha ancora scelto si mostra', async () => {
        const banner = montaIlBanner();
        await flushPromises();

        expect(banner.text()).toContain('cookie.title');
    });

    it('a chi ha già scelto non si ripropone, e resta il modo di tornarci', async () => {
        salvaIlConsenso({ statistiche: true, marketing: false, versione: VERSIONE });

        const banner = montaIlBanner();
        await flushPromises();

        expect(banner.text()).not.toContain('cookie.title');
        // L'icona in basso a sinistra: senza, la scelta sarebbe irrevocabile.
        expect(banner.find('button[aria-label="cookie.manage_aria"]').exists()).toBe(true);
    });

    it('"accetta tutto" accende davvero le due misurazioni', async () => {
        const banner = montaIlBanner();
        await flushPromises();

        await banner.findAll('button').find((b) => b.text() === 'cookie.accept_all').trigger('click');
        await flushPromises();

        expect(aggiornaStatistiche).toHaveBeenCalledWith(true);
        expect(aggiornaMarketing).toHaveBeenCalledWith(true);

        const salvato = leggiIlConsenso(VERSIONE);
        expect(salvato.scelto).toBe(true);
        expect(salvato.statistiche).toBe(true);
        expect(salvato.marketing).toBe(true);
    });

    it('"rifiuta tutto" le spegne, e resta scritto che una scelta c\'è stata', async () => {
        const banner = montaIlBanner();
        await flushPromises();

        await banner.findAll('button').find((b) => b.text() === 'cookie.reject_all').trigger('click');
        await flushPromises();

        expect(aggiornaStatistiche).toHaveBeenCalledWith(false);
        expect(aggiornaMarketing).toHaveBeenCalledWith(false);

        // Rifiutare è una scelta: il banner non deve tornare a chiedere.
        const salvato = leggiIlConsenso(VERSIONE);
        expect(salvato.scelto).toBe(true);
        expect(salvato.statistiche).toBe(false);
        expect(banner.text()).not.toContain('cookie.title');
    });

    it('le caselle si possono scegliere una per una', async () => {
        const banner = montaIlBanner();
        await flushPromises();

        await banner.find('#cookie-analytics').setValue(true);
        await banner.findAll('button').find((b) => b.text() === 'cookie.save_preferences').trigger('click');
        await flushPromises();

        expect(aggiornaStatistiche).toHaveBeenCalledWith(true);
        expect(aggiornaMarketing).toHaveBeenCalledWith(false);
        expect(leggiIlConsenso(VERSIONE).marketing).toBe(false);
    });

    it('"personalizza" apre e richiude il pannello', async () => {
        const banner = montaIlBanner();
        await flushPromises();

        const personalizza = banner.findAll('button').find((b) => b.text() === 'cookie.customize');

        expect(pannelloAperto(banner)).toBe(false);

        await personalizza.trigger('click');
        expect(pannelloAperto(banner)).toBe(true);

        await personalizza.trigger('click');
        expect(pannelloAperto(banner)).toBe(false);
    });

    it('con un\'informativa nuova ferma i tracker e torna a chiedere', async () => {
        // È il punto per cui la versione esiste: senza, aggiungere un tracker
        // basterebbe a coprirlo con un sì dato mesi prima su un'informativa
        // che non lo nominava.
        salvaIlConsenso({ statistiche: true, marketing: true, versione: '2026-01-01' });

        const banner = montaIlBanner();
        await flushPromises();

        expect(aggiornaStatistiche).toHaveBeenCalledWith(false);
        expect(aggiornaMarketing).toHaveBeenCalledWith(false);
        expect(banner.text()).toContain('cookie.title');

        // Le caselle si riaprono come le aveva lasciate: si chiede di
        // confermare, non si riparte da zero.
        expect(banner.find('#cookie-analytics').element.checked).toBe(true);
    });

    it('chi ha già scelto sulla versione in corso non viene disturbato', async () => {
        salvaIlConsenso({ statistiche: false, marketing: false, versione: VERSIONE });

        montaIlBanner();
        await flushPromises();

        // Nessuno dei due viene toccato al caricamento: a decidere se partono
        // è `app.js`, che legge lo stesso valore.
        expect(aggiornaStatistiche).not.toHaveBeenCalled();
        expect(aggiornaMarketing).not.toHaveBeenCalled();
    });

    it('il riferimento che torna dal registro si riscrive nel browser', async () => {
        // Senza, alla scelta successiva il visitatore aprirebbe una seconda
        // storia invece di aggiungere una riga alla propria.
        window.axios.post.mockResolvedValue({
            data: { riferimento: 'abc-123', registrato_il: '2026-09-22T10:00:00.000Z' },
        });

        const banner = montaIlBanner();
        await flushPromises();

        await banner.findAll('button').find((b) => b.text() === 'cookie.accept_all').trigger('click');
        await flushPromises();

        expect(leggiIlConsenso(VERSIONE).riferimento).toBe('abc-123');
        expect(leggiIlConsenso(VERSIONE).data).toBe('2026-09-22T10:00:00.000Z');
    });

    it('se il registro non risponde, la scelta vale lo stesso', async () => {
        window.axios.post.mockRejectedValue(new Error('rete assente'));

        const banner = montaIlBanner();
        await flushPromises();

        await banner.findAll('button').find((b) => b.text() === 'cookie.accept_all').trigger('click');
        await flushPromises();

        // Il sito si comporta come il visitatore ha chiesto; è solo la prova a
        // essere rimasta indietro, e aspetta il caricamento successivo.
        expect(aggiornaStatistiche).toHaveBeenCalledWith(true);
        expect(leggiIlConsenso(VERSIONE).statistiche).toBe(true);
        expect(consensoInAttesa()).not.toBeNull();
    });

    it('una registrazione rimasta indietro si completa al caricamento dopo', async () => {
        salvaIlConsenso({ statistiche: true, marketing: false, versione: VERSIONE });
        mettiInAttesa({ statistiche: true, marketing: false, riferimento: null });

        montaIlBanner();
        await flushPromises();

        expect(window.axios.post).toHaveBeenCalledWith('/consenso-cookie.registra', {
            statistiche: true, marketing: false, riferimento: null,
        });
        expect(consensoInAttesa()).toBeNull();
    });

    it('senza niente in attesa non chiama il registro per conto suo', async () => {
        salvaIlConsenso({ statistiche: true, marketing: false, versione: VERSIONE });

        montaIlBanner();
        await flushPromises();

        expect(window.axios.post).not.toHaveBeenCalled();
    });

    it('si riapre da qualunque punto del sito', async () => {
        salvaIlConsenso({ statistiche: true, marketing: false, versione: VERSIONE });

        const banner = montaIlBanner();
        await flushPromises();

        // Lo usano il footer e il pulsante della Cookie Policy: il banner è
        // caricato in differita, quindi un ref sul componente non basterebbe.
        window.dispatchEvent(new CustomEvent('preferenze-cookie:apri'));
        await flushPromises();

        expect(banner.text()).toContain('cookie.title');
        // Si riapre già sul pannello delle caselle: chi torna qui vuole
        // cambiare qualcosa, non rileggere l'introduzione.
        expect(pannelloAperto(banner)).toBe(true);
        expect(banner.find('#cookie-analytics').element.checked).toBe(true);
    });

    it('smontato, non resta in ascolto', async () => {
        const togli = vi.spyOn(window, 'removeEventListener');

        const banner = montaIlBanner();
        await flushPromises();
        banner.unmount();

        // Il banner si monta e si smonta a ogni navigazione Inertia: un
        // ascoltatore lasciato indietro si somma a ogni pagina visitata.
        expect(togli).toHaveBeenCalledWith('preferenze-cookie:apri', expect.any(Function));

        togli.mockRestore();
    });

    it('il riferimento della scelta si legge nelle impostazioni, con la data', async () => {
        // È il numero che il visitatore cita se ci scrive per chiedere conto
        // del proprio consenso.
        salvaIlConsenso({
            statistiche: true, marketing: false, versione: VERSIONE,
            riferimento: 'abc-123', data: '2026-09-22T10:00:00.000Z',
        });

        const banner = montaIlBanner();
        await flushPromises();

        window.dispatchEvent(new CustomEvent('preferenze-cookie:apri'));
        await flushPromises();

        expect(banner.text()).toContain('abc-123');
        expect(banner.text()).toContain(new Date('2026-09-22T10:00:00.000Z').toLocaleString('it-IT'));
    });

    it('una data illeggibile non stampa "Invalid Date" accanto al riferimento', async () => {
        localStorage.setItem(CHIAVE_CONSENSO, JSON.stringify({
            necessary: true, statistiche: true, marketing: false,
            versione: VERSIONE, riferimento: 'abc-123', data: 'lunedì',
        }));

        const banner = montaIlBanner();
        await flushPromises();

        window.dispatchEvent(new CustomEvent('preferenze-cookie:apri'));
        await flushPromises();

        expect(banner.text()).toContain('abc-123');
        expect(banner.text()).not.toContain('Invalid Date');
    });

    it('senza la versione fra le props il banner resta usabile', async () => {
        // `consensoCookie` arriva da un middleware. Se un giorno non arrivasse
        // su una pagina, il banner deve continuare a raccogliere la scelta:
        // senza versione attesa, una scelta salvata vale comunque.
        pagina.props = { locale: 'it' };
        salvaIlConsenso({ statistiche: true, marketing: false, versione: VERSIONE });

        const banner = montaIlBanner();
        await flushPromises();

        expect(banner.text()).not.toContain('cookie.title');
        expect(aggiornaStatistiche).not.toHaveBeenCalled();
    });

    it('in inglese la data del consenso si legge lo stesso', async () => {
        // `it-IT` ed `en-GB` scrivono le date allo stesso modo, quindi da qui
        // non si distingue quale delle due il componente abbia usato: si
        // prova che la pagina inglese mostri una data, non un campo vuoto.
        pagina.props = { consensoCookie: { versione: VERSIONE }, locale: 'en' };
        salvaIlConsenso({
            statistiche: true, marketing: false, versione: VERSIONE,
            riferimento: 'abc-123', data: '2026-09-22T10:00:00.000Z',
        });

        const banner = montaIlBanner();
        await flushPromises();
        window.dispatchEvent(new CustomEvent('preferenze-cookie:apri'));
        await flushPromises();

        expect(banner.text()).toContain(new Date('2026-09-22T10:00:00.000Z').toLocaleString('en-GB'));
    });
});
