import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import ui from '@nuxt/ui/vue-plugin';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ChannelAuthorizationHandler } from 'pusher-js';
import Pusher from 'pusher-js';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import PersistentLayout from './layouts/PersistentLayout.vue';

const pogoHost = import.meta.env.VITE_POGO_HOST || window.location.hostname;
const pogoAppId = import.meta.env.VITE_POGO_APP_ID || 'pogo-app';
const pogoWsPort = Number(import.meta.env.VITE_POGO_PORT || 80);
const pogoWssPort = Number(import.meta.env.VITE_POGO_WSS_PORT || 443);
const pogoForceTls = window.location.protocol === 'https:';
const pogoTransports = ['ws'] as ['ws'];
const csrfToken = document
    .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
    ?.getAttribute('content');
const pogoAuthHeaders = {
    'X-Requested-With': 'XMLHttpRequest',
    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
};
const pogoChannelAuthorization: ChannelAuthorizationHandler = async (
    { socketId, channelName },
    callback,
) => {
    try {
        const response = await fetch('/pogo/auth', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                ...pogoAuthHeaders,
                Accept: 'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                socket_id: socketId,
                channel_name: channelName,
            }),
        });

        if (!response.ok) {
            throw new Error(
                `Pogo channel authorization failed (${response.status})`,
            );
        }

        const authData = (await response.json()) as {
            auth: string;
            channel_data?: string;
        };

        callback(null, {
            auth: authData.auth,
            ...(authData.channel_data
                ? { channel_data: authData.channel_data }
                : {}),
        });
    } catch (error) {
        callback(
            error instanceof Error
                ? error
                : new Error('Pogo channel authorization failed'),
            null,
        );
    }
};
const pogoClient = new Pusher(pogoAppId, {
    cluster: 'mt1',
    wsHost: pogoHost,
    wsPort: pogoWsPort,
    wssPort: pogoWssPort,
    forceTLS: pogoForceTls,
    enableStats: false,
    enabledTransports: pogoTransports,
    channelAuthorization: {
        customHandler: pogoChannelAuthorization,
    },
    userAuthentication: {
        endpoint: '/pogo/user-auth',
        transport: 'ajax',
        headers: pogoAuthHeaders,
    },
});

configureEcho({
    broadcaster: 'pusher',
    key: pogoAppId,
    cluster: 'mt1',
    wsHost: pogoHost,
    wsPort: pogoWsPort,
    wssPort: pogoWssPort,
    forceTLS: pogoForceTls,
    enabledTransports: pogoTransports,
    enableStats: false,
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
