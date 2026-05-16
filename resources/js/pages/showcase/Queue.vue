<script setup lang="ts">
import Queue from '@/components/showcase/queue.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { queue } from '@/routes/showcase';
import type { BreadcrumbItem } from '@nuxt/ui';

type QueueStats = {
    enqueued: number;
    dispatched: number;
    dropped_full: number;
    dropped_payload_too_large: number;
    dropped_shutdown: number;
    send_errors: number;
    current_depth: number;
    max_message_bytes?: number | null;
    driver_ready: boolean;
};

type QueueBatch = {
    id?: string | null;
    status: 'idle' | 'active' | 'finished';
    total: number;
    queued: number;
    running: number;
    completed: number;
    failed: number;
    jobs: Array<{
        id: string;
        label: string;
        detail: string;
        duration_ms: number;
        icon: string;
        status: 'queued' | 'running' | 'completed' | 'failed';
        worker_lane?: number | null;
        result?: string | null;
        error?: string | null;
    }>;
};

defineProps<{
    queueAvailable: boolean;
    queueStats: QueueStats;
    workerCount: number;
    batch: QueueBatch;
}>();

const breadcrumbs = [
    {
        label: 'Queue',
        to: queue().url,
    },
] satisfies BreadcrumbItem[];
</script>

<template>
    <AppLayout :breadcrumbs>
        <template #body>
            <Queue
                :queue-available="queueAvailable"
                :queue-stats="queueStats"
                :worker-count="workerCount"
                :batch="batch"
            />
        </template>
    </AppLayout>
</template>
