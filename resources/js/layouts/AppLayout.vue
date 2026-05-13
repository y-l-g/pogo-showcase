<script setup lang="ts">
import UserMenu from '@/components/layout/UserMenu.vue';
import { useAuthPage } from '@/composables/useAuthPage';
import { chat, scheduler } from '@/routes/showcase';
import type { BreadcrumbItem, NavigationMenuItem } from '@nuxt/ui';
import { ref } from 'vue';

defineProps<{
    breadcrumbs?: BreadcrumbItem[];
}>();

const open = ref(false);
const page = useAuthPage();

const links: NavigationMenuItem[] = [
    {
        label: 'Chat',
        icon: 'i-lucide-cat',
        to: chat().url,
        active: page.url === chat().url,
        onSelect: () => {
            open.value = false;
        },
    },
    {
        label: 'Scheduler',
        icon: 'i-lucide-clock',
        to: scheduler().url,
        active: page.url === scheduler().url,
        onSelect: () => {
            open.value = false;
        },
    },
];

const bottomlinks: NavigationMenuItem[] = [
    {
        label: 'Github Repo',
        icon: 'i-lucide-folder',
        to: 'https://github.com/y-l-g/websocket',
        target: '_blank',
    },
    {
        label: 'Documentation',
        icon: 'i-lucide-info',
        to: 'https://doc.saasterkit.com',
        target: '_blank',
    },
];
</script>

<template>
    <UDashboardGroup unit="rem" storage="local">
        <UDashboardSidebar
            id="default"
            v-model:open="open"
            collapsible
            resizable
            class="bg-elevated/25"
            :ui="{ footer: 'lg:border-t lg:border-default' }"
        >
            <template #header="{ collapsed }">
                <UButton
                    icon="i-lucide-hamburger"
                    color="neutral"
                    size="xl"
                    variant="link"
                    :square="collapsed"
                    class="data-[state=open]:bg-elevated"
                    :class="[!collapsed && 'py-2']"
                    :to="chat().url"
                    ><span v-if="!collapsed"
                        ><span class="text-default">Pogo</span>showcase</span
                    ></UButton
                >
            </template>

            <template #default="{ collapsed }">
                <UNavigationMenu
                    :collapsed="collapsed"
                    :items="links"
                    orientation="vertical"
                    tooltip
                    popover
                />

                <UNavigationMenu
                    :collapsed="collapsed"
                    :items="bottomlinks"
                    orientation="vertical"
                    tooltip
                    class="mt-auto"
                />
            </template>
        </UDashboardSidebar>
        <UDashboardPanel resizable>
            <template #header>
                <UDashboardNavbar :ui="{ right: 'gap-3' }">
                    <template #leading>
                        <UDashboardSidebarCollapse
                            as="button"
                            :disabled="false"
                        />
                        <UBreadcrumb :items="breadcrumbs" />
                    </template>

                    <template #right>
                        <UColorModeButton
                            color="neutral"
                            as="button"
                            :disabled="false"
                        ></UColorModeButton>
                        <UserMenu
                    /></template>
                </UDashboardNavbar>
                <UDashboardToolbar
                    v-if="$slots.toolbar || $slots['toolbar-left']"
                    ><slot name="toolbar"></slot
                    ><template #left><slot name="toolbar-left"></slot></template
                ></UDashboardToolbar>
            </template>
            <template #body> <slot name="body"></slot></template>
        </UDashboardPanel>
    </UDashboardGroup>
</template>
