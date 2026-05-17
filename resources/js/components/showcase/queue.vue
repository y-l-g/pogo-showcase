<script setup lang="ts">
import { reset, run } from '@/routes/showcase/queue';
import { useForm, usePoll } from '@inertiajs/vue3';
import { computed } from 'vue';

type JobStatus = 'queued' | 'running' | 'completed' | 'failed';

type QueueJob = {
    id: string;
    label: string;
    detail: string;
    duration_ms: number;
    icon: string;
    status: JobStatus;
    worker_lane?: number | null;
    result?: string | null;
    error?: string | null;
};

type QueueBatch = {
    id?: string | null;
    status: 'idle' | 'active' | 'finished';
    total: number;
    queued: number;
    running: number;
    completed: number;
    failed: number;
    jobs: QueueJob[];
};

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

const props = defineProps<{
    queueAvailable: boolean;
    queueStats: QueueStats;
    workerCount: number;
    batch: QueueBatch;
}>();

usePoll(
    1000,
    {
        only: ['queueAvailable', 'queueStats', 'workerCount', 'batch'],
    },
    {
        keepAlive: true,
    },
);

const runForm = useForm({});
const resetForm = useForm({});

const isActive = computed(() => props.batch.status === 'active');

const columns: Array<{
    status: JobStatus;
    label: string;
    icon: string;
    color: 'neutral' | 'info' | 'success' | 'error';
}> = [
    {
        status: 'queued',
        label: 'Queued',
        icon: 'i-lucide-list-ordered',
        color: 'neutral',
    },
    {
        status: 'running',
        label: 'Running',
        icon: 'i-lucide-loader-circle',
        color: 'info',
    },
    {
        status: 'completed',
        label: 'Completed',
        icon: 'i-lucide-circle-check',
        color: 'success',
    },
    {
        status: 'failed',
        label: 'Failed',
        icon: 'i-lucide-circle-alert',
        color: 'error',
    },
];

const jobsFor = (status: JobStatus) =>
    props.batch.jobs.filter((job) => job.status === status);

const metricCards = computed(() => [
    {
        label: 'Depth',
        value: props.queueStats.current_depth,
        icon: 'i-lucide-gauge',
    },
    {
        label: 'Enqueued',
        value: props.queueStats.enqueued,
        icon: 'i-lucide-arrow-down-to-line',
    },
    {
        label: 'Dispatched',
        value: props.queueStats.dispatched,
        icon: 'i-lucide-send',
    },
    {
        label: 'Workers',
        value: props.workerCount,
        icon: 'i-lucide-cpu',
    },
]);

const issueCount = computed(
    () =>
        props.queueStats.dropped_full +
        props.queueStats.dropped_payload_too_large +
        props.queueStats.dropped_shutdown +
        props.queueStats.send_errors,
);

const submitRun = () => {
    runForm.submit(run(), {
        preserveScroll: true,
    });
};

const submitReset = () => {
    resetForm.submit(reset(), {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 sm:p-6">
        <div
            class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
        >
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <UBadge
                        :color="queueAvailable ? 'success' : 'warning'"
                        variant="subtle"
                    >
                        {{
                            queueAvailable
                                ? 'Pogo queue ready'
                                : 'Pogo queue unavailable'
                        }}
                    </UBadge>
                    <UBadge
                        :color="batch.status === 'active' ? 'info' : 'neutral'"
                        variant="subtle"
                    >
                        {{ batch.status }}
                    </UBadge>
                </div>
                <h1 class="text-2xl font-semibold tracking-normal">
                    Queue job board
                </h1>
            </div>

            <div class="flex flex-wrap gap-2">
                <UButton
                    icon="i-lucide-play"
                    :loading="runForm.processing"
                    :disabled="
                        runForm.processing || isActive || !queueAvailable
                    "
                    @click="submitRun"
                >
                    Run batch
                </UButton>
                <UButton
                    color="neutral"
                    variant="subtle"
                    icon="i-lucide-rotate-ccw"
                    :loading="resetForm.processing"
                    :disabled="resetForm.processing || batch.total === 0"
                    @click="submitReset"
                >
                    Reset
                </UButton>
            </div>
        </div>

        <UAlert
            v-if="!queueAvailable"
            color="warning"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            title="Pogo queue is not loaded in this runtime"
            description="Start the FrankenPHP binary compiled with the Pogo queue module to dispatch this visual batch."
        />

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="metric in metricCards"
                :key="metric.label"
                class="rounded-lg border border-default bg-elevated/30 p-4"
            >
                <div class="mb-3 flex items-center justify-between gap-3">
                    <p class="text-sm text-muted">{{ metric.label }}</p>
                    <UIcon :name="metric.icon" class="size-5 text-muted" />
                </div>
                <p class="text-2xl font-semibold">{{ metric.value }}</p>
            </div>
        </div>

        <UAlert
            v-if="issueCount > 0"
            color="error"
            variant="subtle"
            icon="i-lucide-circle-alert"
            :title="`${issueCount} queue issue${issueCount === 1 ? '' : 's'}`"
            :description="`${queueStats.dropped_full} full, ${queueStats.dropped_payload_too_large} payload, ${queueStats.dropped_shutdown} shutdown, ${queueStats.send_errors} send errors.`"
        />

        <section class="grid min-h-[28rem] gap-4 xl:grid-cols-4">
            <div
                v-for="column in columns"
                :key="column.status"
                class="flex min-h-80 flex-col rounded-lg border border-default bg-muted/20"
            >
                <div
                    class="flex items-center justify-between gap-3 border-b border-default px-4 py-3"
                >
                    <div class="flex items-center gap-2">
                        <UIcon :name="column.icon" class="size-5" />
                        <h2 class="font-medium">{{ column.label }}</h2>
                    </div>
                    <UBadge :color="column.color" variant="subtle">
                        {{ jobsFor(column.status).length }}
                    </UBadge>
                </div>

                <TransitionGroup
                    name="queue-card"
                    tag="div"
                    class="flex flex-1 flex-col gap-3 p-3"
                >
                    <div
                        v-for="job in jobsFor(column.status)"
                        :key="job.id"
                        class="rounded-lg border border-default bg-default p-4 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-md bg-elevated"
                                >
                                    <UIcon :name="job.icon" class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate font-medium">
                                        {{ job.label }}
                                    </p>
                                    <p class="text-sm text-muted">
                                        {{ job.detail }}
                                    </p>
                                </div>
                            </div>
                            <UIcon
                                v-if="job.status === 'running'"
                                name="i-lucide-loader-circle"
                                class="size-5 animate-spin text-info"
                            />
                        </div>

                        <div
                            class="mt-4 flex flex-wrap items-center gap-2 text-xs"
                        >
                            <UBadge color="neutral" variant="outline">
                                {{ (job.duration_ms / 1000).toFixed(1) }}s
                            </UBadge>
                            <UBadge
                                v-if="job.worker_lane"
                                color="info"
                                variant="subtle"
                            >
                                worker {{ job.worker_lane }}
                            </UBadge>
                        </div>

                        <p
                            v-if="job.result || job.error"
                            class="mt-3 text-sm"
                            :class="job.error ? 'text-error' : 'text-muted'"
                        >
                            {{ job.error ?? job.result }}
                        </p>
                    </div>

                    <div
                        v-if="jobsFor(column.status).length === 0"
                        :key="`${column.status}-empty`"
                        class="flex flex-1 items-center justify-center rounded-lg border border-dashed border-default p-6 text-sm text-muted"
                    >
                        Empty
                    </div>
                </TransitionGroup>
            </div>
        </section>
    </div>
</template>

<style scoped>
.queue-card-move,
.queue-card-enter-active,
.queue-card-leave-active {
    transition:
        opacity 180ms ease,
        transform 180ms ease;
}

.queue-card-enter-from,
.queue-card-leave-to {
    opacity: 0;
    transform: translateY(6px);
}
</style>
