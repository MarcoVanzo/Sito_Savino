import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Savino Del Bene Volley';

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
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

        return app.mount(el);
    },
    progress: {
        color: '#C5A55A',
    },
});
