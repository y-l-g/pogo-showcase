<script setup lang="ts">
import Chat from '@/components/showcase/chat.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { chat } from '@/routes/showcase';
import type { BreadcrumbItem } from '@nuxt/ui';

const breadcrumbs = [
    {
        label: 'Chat',
        to: chat().url,
    },
] satisfies BreadcrumbItem[];
</script>

<template>
    <AppLayout :breadcrumbs>
        <template #body>
            <Chat />
        </template>
    </AppLayout>
</template>
