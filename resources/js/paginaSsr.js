/**
 * Il rendering SSR (ssr.js, `php artisan inertia:start-ssr`): rende in HTML la
 * pagina iniziale, cosi' titolo, H1 e testi principali stanno nella risposta
 * e non arrivano solo dopo il JavaScript (audit SEO/ADV del 20/10/2026).
 *
 * Qui gira solo cio' che serve a disegnare: niente misurazione, banner,
 * diagnostica o ascolti del router, che partono nel browser (app.js). I
 * componenti che toccano window, document o localStorage lo fanno in
 * onMounted o dietro `typeof window`.
 *
 * Il server serve tutte le richieste nello stesso processo: `route()` e'
 * globale nei componenti, quindi la configurazione di Ziggy della richiesta
 * (indirizzo, lingua) sta in un AsyncLocalStorage, che segue la richiesta
 * attraverso gli `await` del rendering. Una variabile globale riassegnata a
 * ogni richiesta avrebbe dato a una pagina i link di un'altra.
 */
import { AsyncLocalStorage } from 'node:async_hooks';
import { createInertiaApp } from '@inertiajs/vue3';
import { renderToString } from '@vue/server-renderer';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, h } from 'vue';
import createDOMPurify from 'dompurify';
import { JSDOM } from 'jsdom';
import { usaIlPurificatore } from './Composables/useSanitize.js';
import { route as ziggyRoute } from '../../vendor/tightenco/ziggy';
import { installaLeTraduzioni, routeNellaLingua, titoloDellaScheda } from './Support/appInertia.js';

// Un DOM solo per tutto il processo: a DOMPurify serve per analizzare l'HTML
// della redazione (useSanitize), non per disegnare.
usaIlPurificatore(createDOMPurify(new JSDOM('').window));

const appName = import.meta.env.VITE_APP_NAME || 'Savino Del Bene Volley';
const richiesta = new AsyncLocalStorage();

globalThis.route = (...argomenti) => {
    const corrente = richiesta.getStore();

    if (!corrente) {
        throw new Error('route() chiamata fuori da una richiesta SSR');
    }

    return corrente.route(...argomenti);
};

/**
 * `route()` di una richiesta: la configurazione di Ziggy arriva nella prop
 * `ziggy` (HandleInertiaRequests, solo per le pagine caricate per intero).
 */
export function routeDellaPagina(page) {
    const ziggy = {
        ...page.props.ziggy,
        location: new URL(page.props.ziggy?.location ?? page.url, page.props.ziggy?.url ?? 'http://localhost'),
    };
    const originale = (name, params, absolute, config = ziggy) => ziggyRoute(name, params, absolute, config);

    return routeNellaLingua(
        originale,
        typeof page.props.locale === 'string' ? page.props.locale : '',
        (nome) => Boolean(ziggy.routes?.[nome]),
    );
}

export function rendiLaPagina(page) {
    const route = routeDellaPagina(page);

    return richiesta.run({ route }, () => createInertiaApp({
        page,
        render: renderToString,
        title: (title) => titoloDellaScheda(title, page.props, appName),
        resolve: (name) => resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
        setup({ App, props, plugin }) {
            const app = createSSRApp({ render: () => h(App, props) }).use(plugin);

            app.config.globalProperties.route = route;
            installaLeTraduzioni(app, page.props.locale);

            return app;
        },
    }));
}

