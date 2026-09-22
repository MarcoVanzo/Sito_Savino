import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import DichiarazioneCookie from './DichiarazioneCookie.vue';

// Il componente legge la lingua dalle props della pagina Inertia, che in un
// test non esiste: basta dire in che lingua siamo.
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { locale: 'it' } }),
}));

/**
 * L'elenco dei cookie in fondo alla Cookie Policy.
 *
 * È la parte che i gestori del consenso a pagamento vendono come "scansione
 * automatica", e qui arriva dal file che la scansione settimanale aggiorna.
 * Quello che va provato non è la grafica: è che la pagina dica il vero anche
 * quando il file è vuoto, quando un cookie non si sa spiegare, e che il
 * visitatore possa tornare sulle proprie scelte da qui.
 */
function dichiarazione(categorie, aggiornataIl = '2026-09-22') {
    return { aggiornata_il: aggiornataIl, categorie, in_regola: true };
}

function categoria(chiave, cookie = [], host = []) {
    return { chiave, cookie, host };
}

describe('DichiarazioneCookie', () => {
    it('senza niente da dichiarare non disegna la sezione', () => {
        // Non un titolo con sotto il vuoto: finché la scansione non ha
        // prodotto un elenco, della dichiarazione non si scrive niente.
        const vuota = mount(DichiarazioneCookie, { props: { dichiarazione: dichiarazione([]) } });

        expect(vuota.find('section').exists()).toBe(false);
    });

    it('senza la prop non va in errore', () => {
        expect(() => mount(DichiarazioneCookie)).not.toThrow();
    });

    it('elenca i cookie trovati, con fornitore, scopo e durata', () => {
        const pagina = mount(DichiarazioneCookie, {
            props: {
                dichiarazione: dichiarazione([
                    categoria('statistiche', [
                        { nome: '_ga', fornitore: 'Google', scopo: 'Distingue i visitatori', durata: '2 anni' },
                    ]),
                ]),
            },
        });

        const righe = pagina.findAll('tbody tr');

        expect(righe).toHaveLength(1);
        expect(righe[0].text()).toContain('_ga');
        expect(righe[0].text()).toContain('Google');
        expect(righe[0].text()).toContain('Distingue i visitatori');
        expect(righe[0].text()).toContain('2 anni');
    });

    it('un cookie che non si sa spiegare compare lo stesso', () => {
        // È il punto della dichiarazione fatta in casa: un cookie che non
        // sappiamo nominare è un fatto da dichiarare, non da nascondere. Al
        // posto dello scopo mancante si legge che non lo conosciamo.
        const pagina = mount(DichiarazioneCookie, {
            props: {
                dichiarazione: dichiarazione([
                    categoria('non classificati', [{ nome: 'sconosciuto', fornitore: null, scopo: null, durata: null }]),
                ]),
            },
        });

        const riga = pagina.find('tbody tr');

        expect(riga.text()).toContain('sconosciuto');
        expect(riga.text()).not.toBe('');
        // Il titolo della categoria arriva dalle traduzioni: se la chiave non
        // fosse tradotta si leggerebbe la chiave nuda.
        expect(pagina.find('h3').text()).not.toBe('cookie_declaration.categories.non classificati');
    });

    it('elenca gli host di terze parti sotto la loro categoria', () => {
        const pagina = mount(DichiarazioneCookie, {
            props: {
                dichiarazione: dichiarazione([
                    categoria('marketing', [], [{ host: 'connect.facebook.net' }, { host: 'www.facebook.com' }]),
                ]),
            },
        });

        expect(pagina.text()).toContain('connect.facebook.net, www.facebook.com');
    });

    it('una categoria di soli host non disegna una tabella vuota', () => {
        const pagina = mount(DichiarazioneCookie, {
            props: { dichiarazione: dichiarazione([categoria('marketing', [], [{ host: 'www.facebook.com' }])]) },
        });

        expect(pagina.find('table').exists()).toBe(false);
    });

    it('dice di quando è la fotografia', () => {
        const pagina = mount(DichiarazioneCookie, {
            props: { dichiarazione: dichiarazione([categoria('necessari', [{ nome: 'XSRF-TOKEN' }])], '2026-09-22') },
        });

        expect(pagina.text()).toContain(new Date('2026-09-22').toLocaleDateString());
    });

    it('una data che non si sa leggere si mostra com\'è', () => {
        // Meglio una data strana che nessuna data: dice comunque che il file
        // è stato prodotto, e che qualcosa nel formato è cambiato.
        const pagina = mount(DichiarazioneCookie, {
            props: { dichiarazione: dichiarazione([categoria('necessari', [{ nome: 'XSRF-TOKEN' }])], 'lunedì') },
        });

        expect(pagina.text()).toContain('lunedì');
    });

    it('senza data non lascia in giro un "aggiornata il" senza seguito', () => {
        const pagina = mount(DichiarazioneCookie, {
            props: { dichiarazione: dichiarazione([categoria('necessari', [{ nome: 'XSRF-TOKEN' }])], null) },
        });

        expect(pagina.text()).not.toContain('Aggiornata');
    });

    it('da qui si riaprono le preferenze', async () => {
        // È l'unico modo che il visitatore ha di cambiare idea leggendo la
        // Cookie Policy: il banner è caricato in differita dal layout e
        // ascolta questo evento, non una chiamata diretta.
        const ascoltatore = vi.fn();
        window.addEventListener('preferenze-cookie:apri', ascoltatore);

        const pagina = mount(DichiarazioneCookie, {
            props: { dichiarazione: dichiarazione([categoria('necessari', [{ nome: 'XSRF-TOKEN' }])]) },
        });

        await pagina.find('button').trigger('click');

        expect(ascoltatore).toHaveBeenCalledOnce();

        window.removeEventListener('preferenze-cookie:apri', ascoltatore);
    });
});
