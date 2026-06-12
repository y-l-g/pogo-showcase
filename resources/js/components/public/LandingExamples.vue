<script setup lang="ts">
import { chat, pogo, queue, scheduler } from '@/routes/showcase';

const examples = [
    {
        key: 'chat',
        title: 'Realtime chat',
        description:
            'Public, private, and presence channels running on the Pogo websocket server.',
        icon: 'i-lucide-messages-square',
        to: chat().url,
        cta: 'Open chat demo',
    },
    {
        key: 'pogo',
        title: 'Pogo jobs',
        description:
            'Dispatch independent request-scoped jobs, then await the results before responding.',
        icon: 'i-lucide-workflow',
        to: pogo().url,
        cta: 'Compare API fetches',
    },
    {
        key: 'queue',
        title: 'Queue board',
        description:
            'Send a visual batch through the native in-memory queue and watch workers move jobs.',
        icon: 'i-lucide-list-todo',
        to: queue().url,
        cta: 'Open queue demo',
    },
    {
        key: 'scheduler',
        title: 'Scheduler pulse',
        description:
            'Poll the latest scheduled run without cron or php artisan schedule:run.',
        icon: 'i-lucide-clock',
        to: scheduler().url,
        cta: 'Open scheduler',
    },
] as const;

const chatMessages = [
    {
        user: 'Public',
        content: 'Message broadcast to every visitor.',
        align: 'start',
        color: 'neutral',
    },
    {
        user: 'Private',
        content: 'Authenticated channel with signed auth.',
        align: 'end',
        color: 'primary',
    },
    {
        user: 'Presence',
        content: 'Online users and typing whispers.',
        align: 'start',
        color: 'neutral',
    },
] as const;

const pogoCalls = [
    {
        title: 'Current weather',
        icon: 'i-lucide-thermometer',
        detail: 'temperature and wind',
    },
    {
        title: 'Daily forecast',
        icon: 'i-lucide-calendar-days',
        detail: 'high, low, rain',
    },
    {
        title: 'Air quality',
        icon: 'i-lucide-wind',
        detail: 'AQI and particles',
    },
    {
        title: 'Elevation',
        icon: 'i-lucide-mountain',
        detail: 'Open-Meteo elevation',
    },
] as const;

const queueColumns = [
    {
        label: 'Queued',
        icon: 'i-lucide-list-ordered',
        jobs: [
            {
                label: 'Generate thumbnail',
                detail: 'Media pipeline',
                icon: 'i-lucide-image',
            },
            {
                label: 'Send receipt',
                detail: 'Transactional email',
                icon: 'i-lucide-mail',
            },
        ],
    },
    {
        label: 'Running',
        icon: 'i-lucide-loader-circle',
        jobs: [
            {
                label: 'Aggregate analytics',
                detail: 'Event rollup',
                icon: 'i-lucide-chart-no-axes-column',
            },
        ],
    },
    {
        label: 'Completed',
        icon: 'i-lucide-circle-check',
        jobs: [
            {
                label: 'Create invoice',
                detail: 'PDF rendering',
                icon: 'i-lucide-file-text',
            },
            {
                label: 'Deliver webhook',
                detail: 'Partner callback',
                icon: 'i-lucide-radio-tower',
            },
        ],
    },
    {
        label: 'Failed',
        icon: 'i-lucide-circle-alert',
        jobs: [],
    },
] as const;
</script>

<template>
    <UPageSection
        title="Use cases"
        description="Open the same chat, job, queue, and scheduler demos used in the dashboard."
        :ui="{
            container: 'max-w-7xl',
            title: 'text-center',
            description: 'mx-auto max-w-2xl text-center',
        }"
    >
        <div class="grid gap-6 lg:gap-8">
            <div v-for="example in examples" :key="example.key">
                <div
                    class="overflow-hidden rounded-lg border border-default bg-elevated/30"
                >
                    <div class="grid gap-0 lg:grid-cols-[0.88fr_1.12fr]">
                        <div
                            class="flex flex-col justify-between gap-8 border-b border-default p-6 sm:p-8 lg:border-r lg:border-b-0"
                        >
                            <div class="space-y-4">
                                <div
                                    class="flex size-12 items-center justify-center rounded-lg bg-primary/10 text-primary"
                                >
                                    <UIcon
                                        :name="example.icon"
                                        class="size-6"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <h2 class="text-2xl font-semibold">
                                        {{ example.title }}
                                    </h2>
                                    <p class="max-w-xl text-muted">
                                        {{ example.description }}
                                    </p>
                                </div>
                            </div>

                            <UButton
                                :to="example.to"
                                :label="example.cta"
                                icon="i-lucide-arrow-right"
                                trailing
                                class="w-fit"
                            />
                        </div>

                        <div class="min-h-80 p-4 sm:p-6">
                            <div
                                v-if="example.key === 'chat'"
                                class="flex h-full min-h-72 flex-col rounded-lg border border-default bg-default"
                            >
                                <div
                                    class="flex items-center gap-2 border-b border-default px-4 py-3"
                                >
                                    <UBadge color="neutral" variant="subtle">
                                        Public
                                    </UBadge>
                                    <UBadge color="neutral" variant="subtle">
                                        Private
                                    </UBadge>
                                    <UBadge color="primary" variant="subtle">
                                        Presence
                                    </UBadge>
                                    <UButton
                                        color="neutral"
                                        variant="subtle"
                                        icon="i-lucide-users"
                                        label="3"
                                        size="sm"
                                        class="ml-auto"
                                    />
                                </div>

                                <div
                                    class="flex flex-1 flex-col justify-center gap-4 p-5"
                                >
                                    <div
                                        v-for="message in chatMessages"
                                        :key="message.user"
                                        class="flex flex-col gap-1"
                                        :class="
                                            message.align === 'end'
                                                ? 'items-end'
                                                : 'items-start'
                                        "
                                    >
                                        <span class="px-1 text-xs text-muted">
                                            {{ message.user }}
                                        </span>
                                        <UBadge
                                            :color="message.color"
                                            :label="message.content"
                                            class="max-w-72 text-left whitespace-normal"
                                        />
                                    </div>
                                </div>

                                <div class="border-t border-default p-4">
                                    <UInput
                                        model-value="Type a message..."
                                        readonly
                                        icon="i-lucide-message-square"
                                        class="w-full"
                                    />
                                </div>
                            </div>

                            <div
                                v-else-if="example.key === 'pogo'"
                                class="grid h-full min-h-72 gap-4 xl:grid-cols-2"
                            >
                                <div
                                    class="flex flex-col rounded-lg border border-default bg-default"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3 border-b border-default px-4 py-3"
                                    >
                                        <div>
                                            <h3 class="font-medium">
                                                Standard PHP
                                            </h3>
                                            <p class="text-sm text-muted">
                                                One after another
                                            </p>
                                        </div>
                                        <UBadge
                                            color="neutral"
                                            variant="subtle"
                                        >
                                            sequential
                                        </UBadge>
                                    </div>
                                    <div class="grid gap-3 p-4 sm:grid-cols-2">
                                        <div
                                            v-for="call in pogoCalls"
                                            :key="`standard-${call.title}`"
                                            class="rounded-lg border border-default p-3"
                                        >
                                            <div
                                                class="mb-2 flex items-center gap-2"
                                            >
                                                <UIcon
                                                    :name="call.icon"
                                                    class="size-4"
                                                />
                                                <h4 class="text-sm font-medium">
                                                    {{ call.title }}
                                                </h4>
                                            </div>
                                            <p class="text-xs text-muted">
                                                {{ call.detail }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="flex flex-col rounded-lg border border-primary/40 bg-primary/5"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3 border-b border-primary/20 px-4 py-3"
                                    >
                                        <div>
                                            <h3 class="font-medium">
                                                Pogo parallel
                                            </h3>
                                            <p class="text-sm text-muted">
                                                Dispatched together
                                            </p>
                                        </div>
                                        <UBadge
                                            color="primary"
                                            variant="subtle"
                                        >
                                            external_api
                                        </UBadge>
                                    </div>
                                    <div class="grid gap-3 p-4 sm:grid-cols-2">
                                        <div
                                            v-for="call in pogoCalls"
                                            :key="`pogo-${call.title}`"
                                            class="rounded-lg border border-primary/20 bg-default/70 p-3"
                                        >
                                            <div
                                                class="mb-2 flex items-center gap-2"
                                            >
                                                <UIcon
                                                    :name="call.icon"
                                                    class="size-4"
                                                />
                                                <h4 class="text-sm font-medium">
                                                    {{ call.title }}
                                                </h4>
                                            </div>
                                            <p class="text-xs text-muted">
                                                {{ call.detail }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else-if="example.key === 'queue'"
                                class="grid h-full min-h-72 gap-4 sm:grid-cols-2"
                            >
                                <div
                                    v-for="column in queueColumns"
                                    :key="column.label"
                                    class="flex min-h-56 flex-col rounded-lg border border-default bg-muted/20"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3 border-b border-default px-4 py-3"
                                    >
                                        <div class="flex items-center gap-2">
                                            <UIcon
                                                :name="column.icon"
                                                class="size-5"
                                            />
                                            <h3 class="font-medium">
                                                {{ column.label }}
                                            </h3>
                                        </div>
                                        <UBadge
                                            color="neutral"
                                            variant="subtle"
                                        >
                                            {{ column.jobs.length }}
                                        </UBadge>
                                    </div>

                                    <div class="flex flex-1 flex-col gap-3 p-3">
                                        <div
                                            v-for="job in column.jobs"
                                            :key="job.label"
                                            class="rounded-lg border border-default bg-default p-4"
                                        >
                                            <div class="flex items-start gap-3">
                                                <div
                                                    class="flex size-10 shrink-0 items-center justify-center rounded-md bg-elevated"
                                                >
                                                    <UIcon
                                                        :name="job.icon"
                                                        class="size-5"
                                                    />
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="font-medium">
                                                        {{ job.label }}
                                                    </p>
                                                    <p
                                                        class="text-sm text-muted"
                                                    >
                                                        {{ job.detail }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div
                                            v-if="column.jobs.length === 0"
                                            class="flex flex-1 items-center justify-center rounded-lg border border-dashed border-default p-6 text-sm text-muted"
                                        >
                                            Empty
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="flex h-full min-h-72 items-center justify-center rounded-lg bg-gray-50 p-6 dark:bg-gray-900"
                            >
                                <UCard
                                    class="w-full max-w-sm border-2 text-center"
                                    style="border-color: #22c55e"
                                >
                                    <template #header>
                                        <h3
                                            class="flex items-center justify-center gap-2 text-xl font-bold"
                                        >
                                            <UIcon
                                                name="i-lucide-clock"
                                                class="size-6"
                                            />
                                            Pogo Scheduler
                                        </h3>
                                    </template>

                                    <div
                                        class="flex flex-col items-center justify-center gap-4 py-6"
                                    >
                                        <div
                                            class="flex size-32 items-center justify-center rounded-full bg-green-500 text-2xl font-bold text-white"
                                        >
                                            12
                                        </div>

                                        <div class="space-y-1">
                                            <p
                                                class="text-sm text-gray-500 dark:text-gray-400"
                                            >
                                                Last run:
                                            </p>
                                            <UBadge
                                                size="lg"
                                                color="primary"
                                                variant="subtle"
                                            >
                                                10 seconds ago
                                            </UBadge>
                                        </div>
                                    </div>

                                    <template #footer>
                                        <p class="text-xs text-gray-400">
                                            Automatic update via Inertia polling
                                        </p>
                                    </template>
                                </UCard>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </UPageSection>
</template>
