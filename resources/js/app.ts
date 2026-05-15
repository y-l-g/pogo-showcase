import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import ui from '@nuxt/ui/vue-plugin';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import Pusher from 'pusher-js';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import PersistentLayout from './layouts/PersistentLayout.vue';

const pogoHost = import.meta.env.VITE_POGO_HOST || window.location.hostname;
const pogoWsPort = Number(import.meta.env.VITE_POGO_PORT || 80);
const pogoWssPort = Number(import.meta.env.VITE_POGO_WSS_PORT || 443);
const pogoForceTls = window.location.protocol === 'https:';
const pogoTransports = (pogoForceTls ? ['wss'] : ['ws']) as ['wss'] | ['ws'];
const csrfToken = document
    .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
    ?.getAttribute('content');
const pogoAuthHeaders = {
    'X-Requested-With': 'XMLHttpRequest',
    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
};
const pogoClient = new Pusher('pogo-key', {
    cluster: 'mt1',
    wsHost: pogoHost,
    wsPort: pogoWsPort,
    wssPort: pogoWssPort,
    forceTLS: pogoForceTls,
    disableStats: true,
    enabledTransports: pogoTransports,
    channelAuthorization: {
        endpoint: '/pogo/auth',
        transport: 'ajax',
        headers: pogoAuthHeaders,
    },
    userAuthentication: {
        endpoint: '/pogo/user-auth',
        transport: 'ajax',
        headers: pogoAuthHeaders,
    },
});

configureEcho({
    broadcaster: 'pusher',
    key: 'pogo-key',
    cluster: 'mt1',
    wsHost: pogoHost,
    wsPort: pogoWsPort,
    wssPort: pogoWssPort,
    forceTLS: pogoForceTls,
    enabledTransports: pogoTransports,
    disableStats: true,
    client: pogoClient,
});

createInertiaApp({
    resolve: (name) => {
        const page = resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        );
        page.then((module) => {
            module.default.layout = PersistentLayout;
        });
        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ui)
            .mount(el);
    },
    progress: false,
});
