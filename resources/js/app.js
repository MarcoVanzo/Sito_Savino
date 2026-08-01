import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { createTranslations } from './i18n/index.js';

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
            const locale = props.initialPage.props.locale;
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

        return app.mount(el);
    },
    progress: {
        color: '#C5A55A',
    },
});
