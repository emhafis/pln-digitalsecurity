import './bootstrap';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

let isReloading = false;
function reloadForStaleResponse(event) {
    if (isReloading) return;
    isReloading = true;
    event.preventDefault();
    const responseUrl = event?.detail?.response?.request?.responseURL;
    window.location.href = responseUrl || window.location.href;
}

router.on('invalid', (event) => {
    const status = event?.detail?.response?.status;
    if ([404, 409, 419, 500, 503].includes(status)) {
        reloadForStaleResponse(event);
    }
});

router.on('exception', (event) => {
    const status = event?.detail?.response?.status;
    if ([404, 409, 419].includes(status)) {
        reloadForStaleResponse(event);
    }
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#0ea5e9',
    },
});
