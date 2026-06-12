import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import ui from '@nuxt/ui/vue-plugin';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import PersistentLayout from './layouts/PersistentLayout.vue';

const reverbForceTls =
    (import.meta.env.VITE_REVERB_SCHEME ||
        (window.location.protocol === 'https:' ? 'https' : 'http')) === 'https';
const reverbPort = Number(
    import.meta.env.VITE_REVERB_PORT || (reverbForceTls ? 443 : 80),
);

configureEcho({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY || 'pogo-app',
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: reverbForceTls,
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
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
