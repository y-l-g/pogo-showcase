<script setup lang="ts">
import Pogo from '@/components/showcase/pogo.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { pogo } from '@/routes/showcase';
import type { BreadcrumbItem } from '@nuxt/ui';

defineProps<{
    pogoAvailable: boolean;
    poolSizes: Record<string, number>;
    demoResults?: any;
}>();

const breadcrumbs = [
    {
        label: 'Pogo',
        to: pogo().url,
    },
] satisfies BreadcrumbItem[];
</script>

<template>
    <AppLayout :breadcrumbs>
        <template #body>
            <Pogo
                :pogo-available="pogoAvailable"
                :pool-sizes="poolSizes"
                :demo-results="demoResults"
            />
        </template>
    </AppLayout>
</template>
