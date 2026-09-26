/**
 * Quello che serve per montare una pagina Inertia intera in un test, senza
 * server: un `useForm` finto ma fedele (errori, setError, clearErrors, post),
 * `usePage` con le props che il test decide, le traduzioni vere e `route()`.
 *
 * Il `vi.mock('@inertiajs/vue3', …)` resta nel file di test (vitest lo
 * solleva in cima al modulo): qui c'e' solo la fabbrica da passargli.
 *
 * Solo per i test: sta fuori dalla copertura (vitest.config.js).
 */
import { reactive } from 'vue';
import axe from 'axe-core';
import { createTranslations } from '@/i18n/index.js';

export const pagina = reactive({ url: '/', component: 'Test', props: { locale: 'it', auth: { user: null }, flash: {}, siteSettings: {}, navigation: [] } });

/** Le chiamate a `form.post`: il test le legge per sapere se l'ordine e' partito. */
export const invii = [];

export function useFormFinto(iniziali) {
    const form = reactive({
        ...iniziali,
        errors: {},
        processing: false,
        get hasErrors() {
            return Object.keys(this.errors).length > 0;
        },
        setError(campo, messaggio) {
            if (typeof campo === 'object') {
                Object.assign(this.errors, campo);
            } else {
                this.errors[campo] = messaggio;
            }
            return this;
        },
        clearErrors(...campi) {
            if (campi.length === 0) {
                Object.keys(this.errors).forEach((c) => delete this.errors[c]);
            } else {
                campi.forEach((c) => delete this.errors[c]);
            }
            return this;
        },
        post(url, opzioni = {}) {
            invii.push({ url, dati: { ...form }, opzioni });
        },
    });

    return form;
}

/** Il modulo `@inertiajs/vue3` finto, da restituire dentro `vi.mock`. */
export function inertiaFinto() {
    return {
        usePage: () => pagina,
        useForm: (iniziali) => useFormFinto(iniziali),
        router: { visit: () => {}, on: () => () => {}, reload: () => {}, post: () => {} },
        Head: { name: 'Head', render: () => null },
        Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
    };
}

export const route = (nome, parametro) => `/${nome}${parametro && typeof parametro !== 'object' ? `/${parametro}` : ''}`;

/** Le opzioni globali di `mount`: traduzioni italiane vere e `route`. */
export function opzioniGlobali() {
    globalThis.route = route;

    return {
        mocks: { $t: createTranslations('it'), route, $page: pagina },
        stubs: {
            // Il layout porta header, footer e banner: qui interessa la pagina.
            PublicLayout: { template: '<main><slot /></main>' },
            teleport: true,
        },
    };
}

/**
 * axe-core sul DOM montato. Il contrasto resta fuori: jsdom non calcola gli
 * stili, e quello lo misura la scansione con il browser vero
 * (scripts/scansione-accessibilita.mjs).
 */
export async function violazioniAxe(elemento) {
    const esito = await axe.run(elemento, {
        runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] },
        rules: { 'color-contrast': { enabled: false } },
    });

    return esito.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.html.slice(0, 120)).join(' | ')}`);
}

// Quello che jsdom non ha e le pagine usano: matchMedia (menu, riduzione del
// movimento) e scrollTo (cambio di passo del checkout).
if (typeof window !== 'undefined') {
    window.matchMedia ??= (query) => ({ matches: false, media: query, addEventListener() {}, removeEventListener() {}, addListener() {}, removeListener() {} });
    window.scrollTo = () => {};
}

// jsdom non ha CSS.escape, che il lettore virtuale usa per risolvere gli
// id di aria-describedby e aria-labelledby.
if (typeof globalThis.CSS === 'undefined' || typeof globalThis.CSS.escape !== 'function') {
    globalThis.CSS = { ...(globalThis.CSS ?? {}), escape: (valore) => String(valore).replace(/[^a-zA-Z0-9_-]/g, (c) => `\\${c}`) };
}

/**
 * Legge la pagina dall'inizio alla fine come farebbe uno screen reader con la
 * lettura continua (@guidepup/virtual-screen-reader, sull'albero di
 * accessibilita' del DOM) e restituisce le frasi pronunciate, in ordine.
 */
export async function leggiTutto(contenitore = document.body, massimo = 400) {
    const { virtual } = await import('@guidepup/virtual-screen-reader');
    await virtual.start({ container: contenitore });

    try {
        for (let i = 0; i < massimo; i++) {
            await virtual.next();
            const ultima = await virtual.lastSpokenPhrase();
            if (ultima === 'end of document') break;
        }

        return await virtual.spokenPhraseLog();
    } finally {
        await virtual.stop();
    }
}
