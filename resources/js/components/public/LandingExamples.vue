<script setup lang="ts">
import { useEchoPublic } from '@laravel/echo-vue';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';

type LandingChatMessage = {
    id: string;
    name: string;
    content: string;
    timestamp: string;
};

type PulsePayload = {
    ran_at: string;
    server_second: number;
};

type ParallelResult = {
    mode: 'pogo_parallel' | 'php_fallback';
    elapsed_ms: number;
    jobs: Array<{
        label: string;
        duration_ms: number;
    }>;
};

type QueueStatus = 'queued' | 'running' | 'completed';

const chatForm = reactive({
    name: 'Visitor',
    content: 'Hello from the landing page',
});
const chatMessages = ref<LandingChatMessage[]>([
    {
        id: 'demo-message',
        name: 'Pogo',
        content:
            'Send a message. If websockets are running, other visitors receive it.',
        timestamp: new Date().toISOString(),
    },
]);
const chatSending = ref(false);
const chatBroadcasted = ref<boolean | null>(null);

const pulse = ref({
    count: 0,
    ranAt: 'waiting',
    serverSecond: 0,
});
const pulseRunning = ref(false);
const pulseError = ref<string | null>(null);
let pulseTimer: ReturnType<typeof setInterval> | null = null;

const parallel = ref<ParallelResult | null>(null);
const parallelRunning = ref(false);
const parallelError = ref<string | null>(null);

const queueJobs = ref([
    {
        id: 'receipt',
        label: 'Send receipt',
        detail: 'Queued',
        status: 'queued' as QueueStatus,
    },
    {
        id: 'thumbnail',
        label: 'Generate thumbnail',
        detail: 'Queued',
        status: 'queued' as QueueStatus,
    },
    {
        id: 'webhook',
        label: 'Deliver webhook',
        detail: 'Queued',
        status: 'queued' as QueueStatus,
    },
]);
const queueRunning = ref(false);
const queueTimers: Array<ReturnType<typeof setTimeout>> = [];

const snippets = {
    chat: `// Vue
useEchoPublic('landing.chat', '.message.sent', addMessage)
await fetch('/examples/chat/message', { method: 'POST', body })

// Laravel
event(new LandingChatMessage($message));`,
    pulse: `// Vue
setInterval(async () => {
  pulse.value = await fetch('/examples/pulse').then(r => r.json())
}, 1000)

// Laravel
return ['ran_at' => now()->format('H:i:s')];`,
    parallel: `// Laravel
$handles = [];
foreach ($tasks as $task) {
  $handles[] = $pogo->dispatch(WaitJob::class, $task);
}

$results = array_map(fn ($h) => $pogo->await($h), $handles);`,
    queue: `// Laravel
dispatch(new SendReceipt())
  ->onConnection('pogo')
  ->onQueue('default');

// The board polls job status and moves cards.`,
} as const;

const queueColumns = computed(() =>
    (['queued', 'running', 'completed'] as const).map((status) => ({
        status,
        label:
            status === 'queued'
                ? 'Queued'
                : status === 'running'
                  ? 'Running'
                  : 'Completed',
        jobs: queueJobs.value.filter((job) => job.status === status),
    })),
);

const chatStatus = computed(() => {
    if (chatBroadcasted.value === null) {
        return 'Local message list is ready.';
    }

    return chatBroadcasted.value
        ? 'Broadcast sent on landing.chat.'
        : 'Saved locally. Start the Pogo websocket server to broadcast.';
});

if (typeof window !== 'undefined') {
    useEchoPublic(
        'landing.chat',
        '.message.sent',
        (message: LandingChatMessage) => {
            addChatMessage(message);
            chatBroadcasted.value = true;
        },
    );
}

const csrfToken = () =>
    typeof document === 'undefined'
        ? ''
        : (document
              .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
              ?.getAttribute('content') ?? '');

const postJson = async <T,>(url: string, body: Record<string, string> = {}) => {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });

    if (!response.ok) {
        throw new Error(`Request failed with HTTP ${response.status}`);
    }

    return (await response.json()) as T;
};

const getJson = async <T,>(url: string) => {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
        },
    });

    if (!response.ok) {
        throw new Error(`Request failed with HTTP ${response.status}`);
    }

    return (await response.json()) as T;
};

const addChatMessage = (message: LandingChatMessage) => {
    if (chatMessages.value.some((item) => item.id === message.id)) {
        return;
    }

    chatMessages.value = [...chatMessages.value.slice(-3), message];
};

const sendChatMessage = async () => {
    if (chatSending.value || chatForm.content.trim() === '') {
        return;
    }

    chatSending.value = true;

    try {
        const payload = await postJson<{
            message: LandingChatMessage;
            broadcasted: boolean;
        }>('/examples/chat/message', {
            name: chatForm.name,
            content: chatForm.content,
        });

        chatBroadcasted.value = payload.broadcasted;
        addChatMessage(payload.message);
        chatForm.content = '';
    } finally {
        chatSending.value = false;
    }
};

const fetchPulse = async () => {
    try {
        const payload = await getJson<PulsePayload>('/examples/pulse');
        pulse.value = {
            count: pulse.value.count + 1,
            ranAt: payload.ran_at,
            serverSecond: payload.server_second,
        };
        pulseError.value = null;
    } catch (error) {
        pulseError.value =
            error instanceof Error ? error.message : 'Pulse request failed.';
    }
};

const startPulse = () => {
    if (pulseTimer) {
        return;
    }

    pulseRunning.value = true;
    void fetchPulse();
    pulseTimer = setInterval(() => void fetchPulse(), 1000);
};

const stopPulse = () => {
    if (pulseTimer) {
        clearInterval(pulseTimer);
    }

    pulseTimer = null;
    pulseRunning.value = false;
};

const runParallel = async () => {
    parallelRunning.value = true;
    parallelError.value = null;

    try {
        parallel.value = await postJson<ParallelResult>('/examples/parallel');
    } catch (error) {
        parallelError.value =
            error instanceof Error ? error.message : 'Parallel request failed.';
    } finally {
        parallelRunning.value = false;
    }
};

const resetQueue = () => {
    queueTimers.splice(0).forEach((timer) => clearTimeout(timer));
    queueRunning.value = false;
    queueJobs.value = queueJobs.value.map((job) => ({
        ...job,
        detail: 'Queued',
        status: 'queued',
    }));
};

const runQueue = () => {
    resetQueue();
    queueRunning.value = true;

    queueJobs.value.forEach((job, index) => {
        queueTimers.push(
            setTimeout(
                () => {
                    job.status = 'running';
                    job.detail = 'Worker running';
                },
                350 + index * 450,
            ),
            setTimeout(
                () => {
                    job.status = 'completed';
                    job.detail = 'Finished';
                },
                1200 + index * 450,
            ),
        );
    });

    queueTimers.push(
        setTimeout(() => {
            queueRunning.value = false;
        }, 2600),
    );
};

onBeforeUnmount(() => {
    stopPulse();
    queueTimers.splice(0).forEach((timer) => clearTimeout(timer));
});
</script>

<template>
    <UPageSection
        title="Working examples"
        description="Tiny demos you can run here. Each one includes the code that matters."
        :ui="{
            container: 'max-w-7xl',
            title: 'text-center',
            description: 'mx-auto max-w-2xl text-center',
        }"
    >
        <div class="grid gap-6 lg:gap-8">
            <section class="rounded-lg border border-default bg-elevated/30">
                <div class="grid gap-0 lg:grid-cols-[1fr_0.9fr]">
                    <div class="space-y-5 p-5 sm:p-6">
                        <div class="space-y-2">
                            <div
                                class="flex size-11 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <UIcon
                                    name="i-lucide-messages-square"
                                    class="size-5"
                                />
                            </div>
                            <h2 class="text-2xl font-semibold">Real chat</h2>
                            <p class="text-muted">
                                Send a public message. With the websocket server
                                running, every connected browser receives it.
                            </p>
                        </div>

                        <div
                            class="flex min-h-48 flex-col gap-3 rounded-lg border border-default bg-default p-4"
                        >
                            <div class="flex flex-1 flex-col gap-3">
                                <div
                                    v-for="message in chatMessages"
                                    :key="message.id"
                                    class="rounded-lg border border-default p-3"
                                >
                                    <p class="text-sm font-medium">
                                        {{ message.name }}
                                    </p>
                                    <p class="text-sm text-muted">
                                        {{ message.content }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="grid gap-2 sm:grid-cols-[9rem_1fr_auto]"
                            >
                                <UInput
                                    v-model="chatForm.name"
                                    aria-label="Name"
                                    autocomplete="off"
                                />
                                <UInput
                                    v-model="chatForm.content"
                                    aria-label="Message"
                                    autocomplete="off"
                                    @keydown.enter="sendChatMessage"
                                />
                                <UButton
                                    :loading="chatSending"
                                    icon="i-lucide-send"
                                    @click="sendChatMessage"
                                >
                                    Send
                                </UButton>
                            </div>
                            <p class="text-xs text-muted" aria-live="polite">
                                {{ chatStatus }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="border-t border-default p-5 sm:p-6 lg:border-t-0 lg:border-l"
                    >
                        <pre
                            class="h-full rounded-lg bg-gray-950 p-4 text-xs leading-relaxed break-words whitespace-pre-wrap text-gray-100"
                        ><code>{{ snippets.chat }}</code></pre>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-default bg-elevated/30">
                <div class="grid gap-0 lg:grid-cols-[1fr_0.9fr]">
                    <div class="space-y-5 p-5 sm:p-6">
                        <div class="space-y-2">
                            <div
                                class="flex size-11 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <UIcon name="i-lucide-clock" class="size-5" />
                            </div>
                            <h2 class="text-2xl font-semibold">Pulse</h2>
                            <p class="text-muted">
                                A tiny poller hits Laravel every second and
                                updates the counter from the response.
                            </p>
                        </div>

                        <div
                            class="flex flex-col gap-4 rounded-lg border border-default bg-default p-5 sm:flex-row sm:items-center"
                        >
                            <div
                                class="flex size-28 shrink-0 items-center justify-center rounded-full bg-primary text-3xl font-semibold text-white"
                            >
                                {{ pulse.count }}
                            </div>
                            <div class="min-w-0 flex-1 space-y-2">
                                <p class="text-sm text-muted">
                                    Last server hit
                                </p>
                                <p class="text-2xl font-semibold">
                                    {{ pulse.ranAt }}
                                </p>
                                <p class="text-sm text-muted">
                                    Server second: {{ pulse.serverSecond }}
                                </p>
                                <p
                                    v-if="pulseError"
                                    class="text-sm text-error"
                                    aria-live="polite"
                                >
                                    {{ pulseError }}
                                </p>
                            </div>
                            <UButton
                                color="neutral"
                                variant="subtle"
                                :icon="
                                    pulseRunning
                                        ? 'i-lucide-pause'
                                        : 'i-lucide-play'
                                "
                                @click="
                                    pulseRunning ? stopPulse() : startPulse()
                                "
                            >
                                {{ pulseRunning ? 'Pause' : 'Start' }}
                            </UButton>
                        </div>
                    </div>

                    <div
                        class="border-t border-default p-5 sm:p-6 lg:border-t-0 lg:border-l"
                    >
                        <pre
                            class="h-full rounded-lg bg-gray-950 p-4 text-xs leading-relaxed break-words whitespace-pre-wrap text-gray-100"
                        ><code>{{ snippets.pulse }}</code></pre>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-default bg-elevated/30">
                <div class="grid gap-0 lg:grid-cols-[1fr_0.9fr]">
                    <div class="space-y-5 p-5 sm:p-6">
                        <div class="space-y-2">
                            <div
                                class="flex size-11 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <UIcon
                                    name="i-lucide-workflow"
                                    class="size-5"
                                />
                            </div>
                            <h2 class="text-2xl font-semibold">
                                Parallel work
                            </h2>
                            <p class="text-muted">
                                Run three independent backend tasks. Pogo uses
                                workers when available, otherwise PHP runs the
                                same tasks locally.
                            </p>
                        </div>

                        <div
                            class="rounded-lg border border-default bg-default p-5"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div>
                                    <p class="text-sm text-muted">Mode</p>
                                    <p class="font-semibold">
                                        {{
                                            parallel?.mode === 'pogo_parallel'
                                                ? 'Pogo parallel'
                                                : parallel?.mode ===
                                                    'php_fallback'
                                                  ? 'PHP fallback'
                                                  : 'Not run yet'
                                        }}
                                    </p>
                                </div>
                                <UButton
                                    :loading="parallelRunning"
                                    icon="i-lucide-play"
                                    @click="runParallel"
                                >
                                    Run tasks
                                </UButton>
                            </div>

                            <div
                                class="mt-5 grid gap-3 sm:grid-cols-3"
                                aria-live="polite"
                            >
                                <div
                                    v-for="job in parallel?.jobs ?? []"
                                    :key="job.label"
                                    class="rounded-lg border border-default p-3"
                                >
                                    <p class="font-medium">{{ job.label }}</p>
                                    <p class="text-sm text-muted">
                                        {{ job.duration_ms }}ms task
                                    </p>
                                </div>
                            </div>

                            <p v-if="parallel" class="mt-4 text-sm text-muted">
                                Finished in {{ parallel.elapsed_ms }}ms.
                            </p>
                            <p
                                v-if="parallelError"
                                class="mt-4 text-sm text-error"
                            >
                                {{ parallelError }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="border-t border-default p-5 sm:p-6 lg:border-t-0 lg:border-l"
                    >
                        <pre
                            class="h-full rounded-lg bg-gray-950 p-4 text-xs leading-relaxed break-words whitespace-pre-wrap text-gray-100"
                        ><code>{{ snippets.parallel }}</code></pre>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-default bg-elevated/30">
                <div class="grid gap-0 lg:grid-cols-[1fr_0.9fr]">
                    <div class="space-y-5 p-5 sm:p-6">
                        <div class="space-y-2">
                            <div
                                class="flex size-11 items-center justify-center rounded-lg bg-primary/10 text-primary"
                            >
                                <UIcon
                                    name="i-lucide-list-todo"
                                    class="size-5"
                                />
                            </div>
                            <h2 class="text-2xl font-semibold">Queue board</h2>
                            <p class="text-muted">
                                Run the tiny board and watch jobs move from
                                queued to running to completed.
                            </p>
                        </div>

                        <div
                            class="rounded-lg border border-default bg-default p-4"
                        >
                            <div class="mb-4 flex flex-wrap gap-2">
                                <UButton
                                    :loading="queueRunning"
                                    icon="i-lucide-play"
                                    @click="runQueue"
                                >
                                    Run batch
                                </UButton>
                                <UButton
                                    color="neutral"
                                    variant="subtle"
                                    icon="i-lucide-rotate-ccw"
                                    @click="resetQueue"
                                >
                                    Reset
                                </UButton>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div
                                    v-for="column in queueColumns"
                                    :key="column.status"
                                    class="min-h-44 rounded-lg border border-default bg-muted/20 p-3"
                                >
                                    <div
                                        class="mb-3 flex items-center justify-between gap-2"
                                    >
                                        <h3 class="font-medium">
                                            {{ column.label }}
                                        </h3>
                                        <UBadge
                                            color="neutral"
                                            variant="subtle"
                                        >
                                            {{ column.jobs.length }}
                                        </UBadge>
                                    </div>

                                    <div class="space-y-2">
                                        <div
                                            v-for="job in column.jobs"
                                            :key="job.id"
                                            class="rounded-lg border border-default bg-default p-3"
                                        >
                                            <p class="font-medium">
                                                {{ job.label }}
                                            </p>
                                            <p class="text-sm text-muted">
                                                {{ job.detail }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="border-t border-default p-5 sm:p-6 lg:border-t-0 lg:border-l"
                    >
                        <pre
                            class="h-full rounded-lg bg-gray-950 p-4 text-xs leading-relaxed break-words whitespace-pre-wrap text-gray-100"
                        ><code>{{ snippets.queue }}</code></pre>
                    </div>
                </div>
            </section>
        </div>
    </UPageSection>
</template>
