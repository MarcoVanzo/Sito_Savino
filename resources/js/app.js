import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createTranslations } from './i18n/index.js';
import { initAnalytics, trackPageView } from './analytics.js';
import { initMetaPixel, trackPageView as trackPixelPageView } from './meta-pixel.js';

const appName = import.meta.env.VITE_APP_NAME || 'Savino Del Bene Volley';

createInertiaApp({
    // Le pagine che usano useOgMeta arrivano qui con il nome del sito già in
    // coda: senza questo controllo il titolo diventava
    // "Shop Ufficiale — Savino Del Bene Volley - Savino Del Bene Volley".
    title: (title) => {
        if (!title) return appName;

        return title.includes(appName) ? title : `${title} - ${appName}`;
    },
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        const originalRoute = window.route;
        window.route = (name, params, absolute, config) => {
            const condivisa = props.initialPage.props.locale;
            const locale = typeof condivisa === 'string' ? condivisa : '';
            if (locale && locale !== 'it' && name && !name.startsWith(locale + '.')) {
                const localizedName = `${locale}.${name}`;
                if (window.Ziggy?.routes[localizedName] || typeof originalRoute().has === 'function' && originalRoute().has(localizedName)) {
                    return originalRoute(localizedName, params, absolute, config);
                }
            }
            return originalRoute(name, params, absolute, config);
        };
        app.config.globalProperties.route = window.route;

        // i18n: register $t as global translation function
        app.config.globalProperties.$t = function (key, params = {}) {
            const locale = this.$page?.props?.locale || props.initialPage.props.locale || 'it';
            return createTranslations(locale)(key, params);
        };
        if (typeof window !== 'undefined') {
            window.$t = function (key, params = {}) {
                const page = window.document.getElementById('app')?.__vue_app__?.config.globalProperties.$page;
                const locale = page?.props?.locale || props.initialPage.props.locale || 'it';
                return createTranslations(locale)(key, params);
            };
        }

        // Misurazione del traffico. Il consenso salvato si rilegge qui e non
        // dentro il banner: il banner si monta solo quando c'è da chiedere
        // qualcosa, mentre chi ha già accettato va misurato da subito.
        const measurementId = props.initialPage.props.siteSettings?.analytics?.ga4_measurement_id;

        if (measurementId) {
            let consent;

            try {
                consent = JSON.parse(localStorage.getItem('cookie-consent-v2') || '{}').analytics === true;
            } catch {
                // Valore illeggibile: si riparte dal non consenso e il banner
                // tornerà a chiedere.
                consent = false;
            }

            initAnalytics(measurementId, consent);

            // Senza questo, in una SPA GA4 attribuirebbe tutto il traffico alla
            // pagina d'ingresso: è proprio la misura pagina per pagina che serve.
            // Il rinvio di un tick serve a leggere il titolo già aggiornato.
            router.on('navigate', () => window.setTimeout(trackPageView, 0));
        }

        // Meta Pixel: misura il funnel pubblicitario, non il traffico. Vive
        // accanto a GA4 ma è indipendente — uno può essere configurato e l'altro no.
        const analytics = props.initialPage.props.siteSettings?.analytics ?? {};

        if (analytics.meta_pixel_id) {
            let marketingConsent;

            try {
                marketingConsent = JSON.parse(localStorage.getItem('cookie-consent-v2') || '{}').marketing === true;
            } catch {
                marketingConsent = false;
            }

            initMetaPixel(analytics.meta_pixel_id, {
                needsConsent: analytics.meta_pixel_requires_consent === true,
                hasConsent: marketingConsent,
            });

            router.on('navigate', () => window.setTimeout(trackPixelPageView, 0));
        }

        return app.mount(el);
    },
    progress: {
        color: '#C5A55A',
    },
});
