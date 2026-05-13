<script setup lang="ts">
import { login, logout, register } from '@/routes';
import { chat } from '@/routes/showcase';
import { router, usePage } from '@inertiajs/vue3';
import { DrawerProps } from '@nuxt/ui';
import { useBreakpoints } from '@vueuse/core';
import IBiTwitterX from '~icons/bi/twitter-x';
import ILucideGithub from '~icons/lucide/github';

const page = usePage();

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';
const appUrl = import.meta.env.VITE_APP_URL;

useSeoMeta({
    ogTitle: appName,
    ogImage: `${appUrl}/seo_image.jpg`,
    twitterImage: `${appUrl}/seo_image.jpg`,
    twitterCard: 'summary_large_image',
});

const breakpoints = useBreakpoints({
    xs: 410,
});

const isXs = breakpoints.smallerOrEqual('xs');

const drawerMenuProps: DrawerProps = {
    direction: 'top',
};
</script>

<template>
    <UHeader
        to="/"
        mode="drawer"
        :menu="drawerMenuProps"
        :ui="{
            toggle: 'hidden',
        }"
    >
        <template #title>
            <UButton
                variant="link"
                size="xl"
                icon="i-lucide-hamburger"
                class="cursor-pointer"
                ><span v-if="!isXs"
                    ><span class="text-default">Po</span>go</span
                ></UButton
            >
        </template>
        <template #right>
            <UColorModeButton
                variant="link"
                size="lg"
                as="button"
                :disabled="false"
            />

            <UTooltip text="Open on GitHub" :kbds="['meta', 'G']">
                <UButton
                    color="neutral"
                    variant="link"
                    to="https://github.com/y-l-g/websocket"
                    target="_blank"
                    :icon="ILucideGithub"
                    aria-label="GitHub"
                />
            </UTooltip>
            <template v-if="!page.props.user">
                <UButton
                    label="Login"
                    :to="login().url"
                    variant="link"
                    color="neutral"
                />
                <UButton label="Register" :to="register().url" variant="soft" />
            </template>
            <template v-else>
                <UButton
                    label="Chat"
                    :to="chat().url"
                    variant="link"
                    color="neutral"
                />
                <UButton
                    label="Log Out"
                    :onclick="() => router.post(logout().url)"
                    variant="soft"
                    color="neutral"
                />
            </template>
        </template>
    </UHeader>

    <main><slot></slot></main>

    <UFooter>
        <template #left>
            <p class="text-sm text-muted">
                {{ new Date().getFullYear() }}
                Pogo Showcase by
                <a href="https://y-l.fr">YL</a>
            </p>
        </template>
        <template #right>
            <UButton
                :icon="IBiTwitterX"
                color="neutral"
                variant="link"
                to="https://x.com/_y_l_g_"
                target="_blank"
                aria-label="X" />
            <UButton
                to="https://github.com/y-l-g/websocket"
                target="_blank"
                color="neutral"
                variant="link"
                :icon="ILucideGithub"
                aria-label="GitHub Repository" /></template
    ></UFooter>
</template>
