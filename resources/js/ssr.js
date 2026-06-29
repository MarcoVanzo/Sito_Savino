import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Savino Del Bene Volley';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        title: (title) => `${title} - ${appName}`,
        resolve: (name) =>
            resolvePageComponent(
                `./Pages/${name}.vue`,
                import.meta.glob('./Pages/**/*.vue'),
            ),
        setup({ App, props, plugin }) {
            const app = createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .use(ZiggyVue, {
                    ...page.props.ziggy,
                    location: new URL(page.props.ziggy.location),
                });
                
            const originalRoute = app.config.globalProperties.route;
            app.config.globalProperties.route = (name, params, absolute, config) => {
                const locale = props.initialPage.props.locale;
                if (locale && locale !== 'it' && name && !name.startsWith(locale + '.')) {
                    const localizedName = `${locale}.${name}`;
                    if (page.props.ziggy.routes[localizedName] || (typeof originalRoute().has === 'function' && originalRoute().has(localizedName))) {
                        return originalRoute(localizedName, params, absolute, config);
                    }
                }
                return originalRoute(name, params, absolute, config);
            };

            return app;
        },
    }),
);
