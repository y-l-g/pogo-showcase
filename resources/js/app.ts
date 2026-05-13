import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import ui from '@nuxt/ui/vue-plugin';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import PersistentLayout from './layouts/PersistentLayout.vue';
import { configureEcho } from "@laravel/echo-vue";
import Pusher from 'pusher-js';

configureEcho({
    broadcaster: "reverb",
    key: 'pogo-key',
    wsHost: import.meta.env.VITE_POGO_HOST || window.location.hostname,
    wsPort: import.meta.env.VITE_POGO_PORT || 80,
    wssPort: import.meta.env.VITE_POGO_WSS_PORT || 443,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
    client: new Pusher('pogo-key', {
        cluster: 'mt1',
        wsHost: import.meta.env.VITE_POGO_HOST || window.location.hostname,
        wsPort: import.meta.env.VITE_POGO_PORT || 80,
        wssPort: import.meta.env.VITE_POGO_WSS_PORT || 443,
        forceTLS: false,
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        channelAuthorization: {
            endpoint: "/pogo/auth",
            transport: "ajax",
        },
    })
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
