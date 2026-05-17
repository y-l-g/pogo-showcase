<script setup lang="ts">
import Scheduler from '@/components/showcase/scheduler.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { scheduler } from '@/routes/showcase';
import type { BreadcrumbItem } from '@nuxt/ui';

type SchedulerData = {
    color: string;
    count: number;
    last_run: string;
};

defineProps<{
    schedulerData: SchedulerData;
}>();

const breadcrumbs = [
    {
        label: 'Scheduler',
        to: scheduler().url,
    },
] satisfies BreadcrumbItem[];
</script>

<template>
    <AppLayout :breadcrumbs>
        <template #body>
            <Scheduler :scheduler-data="schedulerData" />
        </template>
    </AppLayout>
</template>
