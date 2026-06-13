<script setup lang="ts">
import { intent, ping, progress, raw } from '@/routes/showcase/upload';
import { computed, onBeforeUnmount, ref } from 'vue';

type UploadStatus = {
    ready: boolean;
    store: string;
    active_uploads: number;
    accepted: number;
    completed: number;
    failed: number;
    size_limit_failures: number;
    content_type_failures: number;
    bytes_received: number;
    max_upload_bytes: number;
    worker_event_failures: number;
};

type UploadProgress = {
    upload_id: string;
    state: string;
    bytes_received: number;
    max_bytes: number;
    started_at?: string;
};

type UploadEvent = {
    type: 'completed' | 'failed';
    upload_id: string;
    key?: string;
    bytes?: number;
    bytes_received?: number;
    sha256?: string;
    reason?: string;
    completed_at?: string;
    failed_at?: string;
};

type UploadResult = {
    ok: boolean;
    mode?: string;
    filename?: string;
    key?: string;
    content_type?: string;
    bytes?: number;
    sha256?: string;
    elapsed_ms?: number;
    php_handled_body?: boolean;
    upload_id?: string;
    method?: string;
    url?: string;
    headers?: Record<string, string>;
    max_bytes?: number;
    error?: string | { code?: string; message?: string };
};

type LaneState = {
    running: boolean;
    progress: number;
    elapsedMs: number | null;
    result: UploadResult | null;
    error: string | null;
};

type PressureMode = 'raw' | 'pogo';

type PressurePing = {
    id: number;
    latencyMs: number;
    ok: boolean;
};

type PressureState = {
    running: boolean;
    mode: PressureMode | null;
    uploadsStarted: number;
    uploadsCompleted: number;
    uploadsFailed: number;
    bytesStreamed: number;
    pings: PressurePing[];
    error: string | null;
    summary: string | null;
};

type FetchInitWithDuplex = RequestInit & {
    duplex: 'half';
};

const props = defineProps<{
    uploadAvailable: boolean;
    uploadStatus: UploadStatus;
    maxBytes: number;
    acceptedContentTypes: string[];
}>();

const file = ref<File | null>(null);
const generatedPayload = ref<Blob | null>(null);
const generatedSize = 2 * 1024 * 1024;
const pressureUploadCount = 5;
const pressurePayloadSize = 768 * 1024;
const pressureChunkSize = 16 * 1024;
const pressureChunkDelayMs = 40;
const pressurePingIntervalMs = 350;
const rawLane = ref<LaneState>(emptyLane());
const pogoLane = ref<LaneState>(emptyLane());
const pressure = ref<PressureState>(emptyPressure());
const serverProgress = ref<UploadProgress | null>(null);
const workerEvent = ref<UploadEvent | null>(null);
const liveStatus = ref<UploadStatus>(props.uploadStatus);
let pollTimer: number | null = null;
let pollAttempts = 0;
const maxPollAttempts = 45;
let pressurePingTimer: number | null = null;
let pressurePingId = 0;

const csrfToken = () =>
    document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

const selectedPayload = computed(() => {
    if (file.value) {
        return {
            blob: file.value,
            filename: file.value.name,
            contentType: file.value.type || 'application/octet-stream',
            size: file.value.size,
            source: 'Selected file',
        };
    }

    const payload = generatedPayload.value ?? makeGeneratedPayload();
    generatedPayload.value = payload;

    return {
        blob: payload,
        filename: 'pogo-upload-demo.txt',
        contentType: 'text/plain',
        size: payload.size,
        source: 'Generated payload',
    };
});

const acceptedLabel = computed(() => props.acceptedContentTypes.join(', '));
const maxBytesLabel = computed(() => formatBytes(props.maxBytes));
const sizeLabel = computed(() => formatBytes(selectedPayload.value.size));
const payloadTooLarge = computed(
    () => selectedPayload.value.size > props.maxBytes,
);
const pressureAveragePing = computed(() => {
    const pings = pressure.value.pings.filter((item) => item.ok);

    if (pings.length === 0) {
        return 0;
    }

    return Math.round(
        pings.reduce((total, item) => total + item.latencyMs, 0) / pings.length,
    );
});
const pressureSlowestPing = computed(() =>
    Math.max(0, ...pressure.value.pings.map((item) => item.latencyMs)),
);
const pressureConfigLabel = computed(
    () =>
        `${pressureUploadCount} uploads x ${formatBytes(pressurePayloadSize)}`,
);
const pressureModeLabel = computed(() => {
    if (pressure.value.mode === 'raw') {
        return 'Raw PHP';
    }
    if (pressure.value.mode === 'pogo') {
        return 'Pogo Upload';
    }

    return 'Not run yet';
});

const metricCards = computed(() => [
    {
        label: 'Accepted',
        value: liveStatus.value.accepted,
        icon: 'i-lucide-log-in',
    },
    {
        label: 'Completed',
        value: liveStatus.value.completed,
        icon: 'i-lucide-circle-check',
    },
    {
        label: 'Active',
        value: liveStatus.value.active_uploads,
        icon: 'i-lucide-activity',
    },
    {
        label: 'Bytes',
        value: formatBytes(liveStatus.value.bytes_received),
        icon: 'i-lucide-hard-drive-upload',
    },
]);

function emptyLane(): LaneState {
    return {
        running: false,
        progress: 0,
        elapsedMs: null,
        result: null,
        error: null,
    };
}

function emptyPressure(): PressureState {
    return {
        running: false,
        mode: null,
        uploadsStarted: 0,
        uploadsCompleted: 0,
        uploadsFailed: 0,
        bytesStreamed: 0,
        pings: [],
        error: null,
        summary: null,
    };
}

function makeGeneratedPayload(): Blob {
    return makeTextPayload(generatedSize);
}

function makePressurePayload(): Blob {
    return makeTextPayload(pressurePayloadSize);
}

function makeTextPayload(size: number): Blob {
    const chunk = new TextEncoder().encode(
        'Pogo upload showcase payload. This text is repeated so the browser has a visible body to send.\n',
    );
    const bytes = new Uint8Array(size);

    for (let offset = 0; offset < bytes.length; offset += chunk.length) {
        bytes.set(chunk.slice(0, bytes.length - offset), offset);
    }

    return new Blob([bytes], { type: 'text/plain' });
}

function onFileChange(event: Event) {
    const input = event.target as HTMLInputElement;
    file.value = input.files?.[0] ?? null;
    resetLanes();
}

function resetLanes() {
    rawLane.value = emptyLane();
    pogoLane.value = emptyLane();
    serverProgress.value = null;
    workerEvent.value = null;
    stopPolling();
}

function resetPressure() {
    stopPressurePings();
    pressure.value = emptyPressure();
}

async function runRawUpload() {
    rawLane.value = emptyLane();
    rawLane.value.running = true;
    const payload = selectedPayload.value;
    const startedAt = performance.now();

    try {
        const result = await sendWithProgress(
            raw().url,
            'POST',
            payload.blob,
            {
                'Content-Type': payload.contentType,
                'X-CSRF-TOKEN': csrfToken(),
                'X-Upload-Filename': payload.filename,
            },
            (percent) => {
                rawLane.value.progress = percent;
            },
        );

        rawLane.value.result = result;
        rawLane.value.elapsedMs = Math.round(performance.now() - startedAt);
        if (!result.ok) {
            rawLane.value.error = errorMessage(result.error);
        }
    } catch (error) {
        rawLane.value.error =
            error instanceof Error ? error.message : 'Raw PHP upload failed.';
    } finally {
        rawLane.value.running = false;
    }
}

async function runPogoUpload() {
    pogoLane.value = emptyLane();
    pogoLane.value.running = true;
    serverProgress.value = null;
    workerEvent.value = null;
    const payload = selectedPayload.value;
    const startedAt = performance.now();

    try {
        const intentResponse = await fetch(intent().url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                filename: payload.filename,
                content_type: payload.contentType,
                size: payload.size,
            }),
        });
        const created = (await intentResponse.json()) as UploadResult;

        if (!intentResponse.ok || !created.upload_id || !created.url) {
            throw new Error(errorMessage(created.error));
        }

        pogoLane.value.result = created;
        startPolling(created.upload_id);

        const result = await sendWithProgress(
            created.url,
            created.method ?? 'PUT',
            payload.blob,
            {
                'Content-Type':
                    created.headers?.['content-type'] ?? payload.contentType,
            },
            (percent) => {
                pogoLane.value.progress = percent;
            },
        );

        pogoLane.value.result = {
            ...created,
            ...result,
        };
        pogoLane.value.elapsedMs = Math.round(performance.now() - startedAt);
        if (!result.ok) {
            pogoLane.value.error = errorMessage(result.error);
        }
    } catch (error) {
        pogoLane.value.error =
            error instanceof Error ? error.message : 'Pogo upload failed.';
    } finally {
        pogoLane.value.running = false;
    }
}

async function runPressureTest(mode: PressureMode) {
    if (pressure.value.running) {
        return;
    }

    resetLanes();
    resetPressure();

    pressure.value.running = true;
    pressure.value.mode = mode;
    startPressurePings();

    try {
        const results = await Promise.allSettled(
            Array.from({ length: pressureUploadCount }, (_, index) =>
                runPressureUpload(mode, index + 1),
            ),
        );
        const failures = results.filter(
            (result) => result.status === 'rejected',
        );

        if (failures.length > 0 && !pressure.value.error) {
            pressure.value.error = `${failures.length} pressure upload failed.`;
        }

        pressure.value.summary =
            mode === 'raw'
                ? 'Raw uploads used normal Laravel request workers while pings were running.'
                : 'Pogo uploads streamed through the native handler while Laravel only handled intents and pings.';
    } catch (error) {
        pressure.value.error =
            error instanceof Error ? error.message : 'Pressure test failed.';
    } finally {
        stopPressurePings();
        pressure.value.running = false;
    }
}

async function runPressureUpload(mode: PressureMode, index: number) {
    pressure.value.uploadsStarted += 1;

    try {
        const payload = makePressurePayload();
        const filename = `pressure-${mode}-${index}.txt`;

        if (mode === 'raw') {
            await sendStreamingUpload(
                raw().url,
                'POST',
                payload,
                {
                    'Content-Type': 'text/plain',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Upload-Filename': filename,
                },
                (bytes) => {
                    pressure.value.bytesStreamed += bytes;
                },
            );
        } else {
            await runPogoPressureUpload(payload, filename);
        }

        pressure.value.uploadsCompleted += 1;
    } catch (error) {
        pressure.value.uploadsFailed += 1;
        pressure.value.error =
            error instanceof Error ? error.message : 'Pressure upload failed.';

        throw error;
    }
}

async function runPogoPressureUpload(payload: Blob, filename: string) {
    const intentResponse = await fetch(intent().url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify({
            filename,
            content_type: 'text/plain',
            size: payload.size,
        }),
    });
    const created = (await intentResponse.json()) as UploadResult;

    if (!intentResponse.ok || !created.upload_id || !created.url) {
        throw new Error(errorMessage(created.error));
    }

    if (!pollTimer) {
        startPolling(created.upload_id);
    }

    await sendStreamingUpload(
        created.url,
        created.method ?? 'PUT',
        payload,
        {
            'Content-Type': created.headers?.['content-type'] ?? 'text/plain',
        },
        (bytes) => {
            pressure.value.bytesStreamed += bytes;
        },
    );
}

function sendWithProgress(
    url: string,
    method: string,
    body: Blob,
    headers: Record<string, string>,
    onProgress: (percent: number) => void,
): Promise<UploadResult> {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();

        xhr.open(method, url);
        xhr.responseType = 'json';
        xhr.setRequestHeader('Accept', 'application/json');
        for (const [key, value] of Object.entries(headers)) {
            xhr.setRequestHeader(key, value);
        }

        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable && event.total > 0) {
                onProgress(Math.round((event.loaded / event.total) * 100));
            }
        };
        xhr.onerror = () => reject(new Error('Network error during upload.'));
        xhr.onload = () => {
            onProgress(100);
            const response =
                typeof xhr.response === 'object' && xhr.response !== null
                    ? (xhr.response as UploadResult)
                    : parseJson(xhr.responseText);

            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(response);
                return;
            }

            resolve({
                ...response,
                ok: false,
                error: response.error ?? {
                    code: 'http_error',
                    message: `HTTP ${xhr.status}`,
                },
            });
        };

        xhr.send(body);
    });
}

async function sendStreamingUpload(
    url: string,
    method: string,
    body: Blob,
    headers: Record<string, string>,
    onBytes: (bytes: number) => void,
): Promise<UploadResult> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...headers,
        },
        body: makeThrottledBody(body, onBytes),
        duplex: 'half',
    } as FetchInitWithDuplex);
    const payload = await readUploadResponse(response);

    if (response.ok && payload.ok !== false) {
        return payload;
    }

    throw new Error(
        errorMessage(
            payload.error ?? {
                code: 'http_error',
                message: `HTTP ${response.status}`,
            },
        ),
    );
}

function makeThrottledBody(
    body: Blob,
    onBytes: (bytes: number) => void,
): ReadableStream<Uint8Array> {
    let offset = 0;

    return new ReadableStream<Uint8Array>({
        async pull(controller) {
            if (offset >= body.size) {
                controller.close();
                return;
            }

            const nextOffset = Math.min(offset + pressureChunkSize, body.size);
            const buffer = await body.slice(offset, nextOffset).arrayBuffer();

            await sleep(pressureChunkDelayMs);

            offset = nextOffset;
            onBytes(buffer.byteLength);
            controller.enqueue(new Uint8Array(buffer));
        },
    });
}

async function readUploadResponse(response: Response): Promise<UploadResult> {
    try {
        return (await response.json()) as UploadResult;
    } catch {
        return { ok: false };
    }
}

function sleep(ms: number): Promise<void> {
    return new Promise((resolve) => window.setTimeout(resolve, ms));
}

function parseJson(value: string): UploadResult {
    try {
        return JSON.parse(value) as UploadResult;
    } catch {
        return { ok: false };
    }
}

function startPressurePings() {
    stopPressurePings();
    pressurePingId = 0;
    void recordPressurePing();
    pressurePingTimer = window.setInterval(
        () => void recordPressurePing(),
        pressurePingIntervalMs,
    );
}

async function recordPressurePing() {
    const startedAt = performance.now();
    const id = ++pressurePingId;

    try {
        await fetch(ping().url, {
            headers: {
                Accept: 'application/json',
            },
            cache: 'no-store',
        });

        pressure.value.pings = [
            ...pressure.value.pings.slice(-11),
            {
                id,
                latencyMs: Math.round(performance.now() - startedAt),
                ok: true,
            },
        ];
    } catch {
        pressure.value.pings = [
            ...pressure.value.pings.slice(-11),
            {
                id,
                latencyMs: Math.round(performance.now() - startedAt),
                ok: false,
            },
        ];
    }
}

function stopPressurePings() {
    if (pressurePingTimer !== null) {
        window.clearInterval(pressurePingTimer);
        pressurePingTimer = null;
    }
}

function startPolling(uploadId: string) {
    stopPolling();
    pollAttempts = 0;
    pollUpload(uploadId);
    pollTimer = window.setInterval(() => pollUpload(uploadId), 800);
}

async function pollUpload(uploadId: string) {
    try {
        pollAttempts += 1;
        const response = await fetch(progress(uploadId).url, {
            headers: {
                Accept: 'application/json',
            },
        });
        const payload = (await response.json()) as {
            progress: UploadProgress | null;
            event: UploadEvent | null;
            status: UploadStatus;
        };

        serverProgress.value = payload.progress;
        workerEvent.value = payload.event;
        liveStatus.value = payload.status;

        if (
            payload.event?.type === 'completed' ||
            payload.event?.type === 'failed'
        ) {
            stopPolling();
            return;
        }

        if (pollAttempts >= maxPollAttempts) {
            stopPolling();
        }
    } catch {
        stopPolling();
    }
}

function stopPolling() {
    if (pollTimer !== null) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
}

function errorMessage(error: UploadResult['error']): string {
    if (!error) {
        return 'Upload failed.';
    }
    if (typeof error === 'string') {
        return error;
    }

    return error.message ?? error.code ?? 'Upload failed.';
}

function formatBytes(value?: number | null): string {
    const bytes = value ?? 0;
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

onBeforeUnmount(stopPolling);
onBeforeUnmount(stopPressurePings);
</script>

<template>
    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 sm:p-6">
        <div
            class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
        >
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <UBadge
                        :color="uploadAvailable ? 'success' : 'warning'"
                        variant="subtle"
                    >
                        {{
                            uploadAvailable
                                ? 'Pogo upload ready'
                                : 'Pogo upload unavailable'
                        }}
                    </UBadge>
                    <UBadge color="neutral" variant="subtle">
                        {{ sizeLabel }}
                    </UBadge>
                </div>
                <h1 class="text-2xl font-semibold tracking-normal">
                    Upload pressure isolation
                </h1>
                <p class="mt-2 max-w-3xl text-sm text-muted">
                    This is not a speed race for one small file. PHP authorizes
                    the upload, while Pogo receives the slow body, enforces
                    limits, stores bytes, and reports completion back to PHP.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <label>
                    <input
                        type="file"
                        class="sr-only"
                        :accept="acceptedContentTypes.join(',')"
                        @change="onFileChange"
                    />
                    <UButton
                        as="span"
                        color="neutral"
                        variant="subtle"
                        icon="i-lucide-file-up"
                    >
                        Choose file
                    </UButton>
                </label>
                <UButton
                    color="neutral"
                    variant="ghost"
                    icon="i-lucide-refresh-cw"
                    :disabled="rawLane.running || pogoLane.running"
                    @click="resetLanes"
                >
                    Reset
                </UButton>
            </div>
        </div>

        <UAlert
            v-if="!uploadAvailable"
            color="warning"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            title="Pogo upload is not loaded in this runtime"
            description="The raw PHP lane still works. Start the FrankenPHP binary compiled with the Pogo upload module to run the native ingress lane."
        />

        <UAlert
            v-if="payloadTooLarge"
            color="error"
            variant="subtle"
            icon="i-lucide-circle-alert"
            title="Payload exceeds the configured upload limit"
            :description="`Choose a file up to ${maxBytesLabel} to run this showcase.`"
        />

        <UAlert
            color="info"
            variant="subtle"
            icon="i-lucide-gauge"
            title="The useful signal is app responsiveness under upload pressure"
            description="The single-file flow below proves both paths work. The pressure test shows why moving upload bodies out of Laravel workers matters."
        />

        <section class="rounded-lg border border-default bg-elevated/30 p-4">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <div class="mb-2 flex flex-wrap gap-2">
                        <UBadge color="primary" variant="subtle">
                            Pressure test
                        </UBadge>
                        <UBadge color="neutral" variant="subtle">
                            {{ pressureConfigLabel }}
                        </UBadge>
                    </div>
                    <h2 class="font-semibold">
                        Ping Laravel while uploads are streaming
                    </h2>
                    <p class="mt-1 max-w-3xl text-sm text-muted">
                        Each run starts throttled upload streams and probes a
                        normal Laravel route during the upload. Raw PHP keeps
                        app workers occupied; Pogo keeps the body stream in the
                        native handler.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <UButton
                        icon="i-lucide-server"
                        :loading="pressure.running && pressure.mode === 'raw'"
                        :disabled="pressure.running"
                        @click="runPressureTest('raw')"
                    >
                        Run raw pressure
                    </UButton>
                    <UButton
                        color="neutral"
                        variant="subtle"
                        icon="i-lucide-gauge"
                        :loading="pressure.running && pressure.mode === 'pogo'"
                        :disabled="pressure.running || !uploadAvailable"
                        @click="runPressureTest('pogo')"
                    >
                        Run Pogo pressure
                    </UButton>
                    <UButton
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-rotate-ccw"
                        :disabled="pressure.running"
                        @click="resetPressure"
                    >
                        Reset
                    </UButton>
                </div>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-lg border border-default bg-default p-4">
                    <p class="text-xs text-muted">Mode</p>
                    <p class="mt-1 font-semibold">{{ pressureModeLabel }}</p>
                </div>
                <div class="rounded-lg border border-default bg-default p-4">
                    <p class="text-xs text-muted">Uploads</p>
                    <p class="mt-1 font-semibold">
                        {{ pressure.uploadsCompleted }}/{{
                            pressureUploadCount
                        }}
                        <span
                            v-if="pressure.uploadsFailed > 0"
                            class="text-error"
                        >
                            failed {{ pressure.uploadsFailed }}
                        </span>
                    </p>
                </div>
                <div class="rounded-lg border border-default bg-default p-4">
                    <p class="text-xs text-muted">Streamed</p>
                    <p class="mt-1 font-semibold">
                        {{ formatBytes(pressure.bytesStreamed) }}
                    </p>
                </div>
                <div class="rounded-lg border border-default bg-default p-4">
                    <p class="text-xs text-muted">Average ping</p>
                    <p class="mt-1 font-semibold">
                        {{
                            pressureAveragePing > 0
                                ? `${pressureAveragePing}ms`
                                : 'waiting'
                        }}
                    </p>
                </div>
                <div class="rounded-lg border border-default bg-default p-4">
                    <p class="text-xs text-muted">Slowest ping</p>
                    <p class="mt-1 font-semibold">
                        {{
                            pressureSlowestPing > 0
                                ? `${pressureSlowestPing}ms`
                                : 'waiting'
                        }}
                    </p>
                </div>
            </div>

            <div
                v-if="pressure.pings.length > 0"
                class="mt-4 rounded-lg border border-default bg-default p-4"
            >
                <div class="mb-3 flex items-center justify-between gap-3">
                    <p class="text-sm font-medium">Recent app pings</p>
                    <p class="text-xs text-muted">
                        {{ pressurePingIntervalMs }}ms interval
                    </p>
                </div>
                <div class="space-y-2">
                    <div
                        v-for="item in pressure.pings"
                        :key="item.id"
                        class="grid grid-cols-[4rem_1fr] items-center gap-3"
                    >
                        <p
                            class="text-xs"
                            :class="item.ok ? 'text-muted' : 'text-error'"
                        >
                            {{ item.ok ? `${item.latencyMs}ms` : 'failed' }}
                        </p>
                        <div class="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full"
                                :class="item.ok ? 'bg-primary' : 'bg-error'"
                                :style="{
                                    width: `${Math.min(
                                        100,
                                        Math.max(8, item.latencyMs / 8),
                                    )}%`,
                                }"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <UAlert
                v-if="pressure.summary"
                class="mt-4"
                color="success"
                variant="subtle"
                icon="i-lucide-circle-check"
                :title="pressure.summary"
            />

            <UAlert
                v-if="pressure.error"
                class="mt-4"
                color="error"
                variant="subtle"
                icon="i-lucide-circle-alert"
                :title="pressure.error"
            />
        </section>

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

        <section class="grid gap-5 xl:grid-cols-[1fr_1fr]">
            <div class="rounded-lg border border-default bg-muted/20 p-4">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">Raw PHP</h2>
                        <p class="text-sm text-muted">
                            Laravel receives the body and keeps a request worker
                            busy until the file has been streamed.
                        </p>
                    </div>
                    <UBadge color="neutral" variant="subtle">baseline</UBadge>
                </div>

                <div class="space-y-4">
                    <div
                        class="rounded-lg border border-default bg-default p-4"
                    >
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <p class="text-xs text-muted">PHP work</p>
                                <p class="font-medium">Body stream</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">Checksum</p>
                                <p class="font-medium">In controller</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">Request time</p>
                                <p class="font-medium">Worker occupied</p>
                            </div>
                        </div>
                    </div>

                    <UProgress :model-value="rawLane.progress" />

                    <UButton
                        icon="i-lucide-play"
                        :loading="rawLane.running"
                        :disabled="
                            rawLane.running ||
                            pogoLane.running ||
                            payloadTooLarge
                        "
                        @click="runRawUpload"
                    >
                        Send through PHP
                    </UButton>

                    <UAlert
                        v-if="rawLane.error"
                        color="error"
                        variant="subtle"
                        icon="i-lucide-circle-alert"
                        :title="rawLane.error"
                    />

                    <div
                        v-if="rawLane.result"
                        class="rounded-lg border border-default bg-default p-4"
                    >
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <p class="text-xs text-muted">Elapsed</p>
                                <p class="font-semibold">
                                    {{
                                        rawLane.elapsedMs ??
                                        rawLane.result.elapsed_ms
                                    }}ms
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">Bytes</p>
                                <p class="font-semibold">
                                    {{ formatBytes(rawLane.result.bytes) }}
                                </p>
                            </div>
                        </div>
                        <p class="mt-3 text-xs break-all text-muted">
                            {{ rawLane.result.sha256 }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-default bg-muted/20 p-4">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold">Pogo Upload</h2>
                        <p class="text-sm text-muted">
                            PHP signs a short-lived intent; Go owns the upload
                            stream and emits the completion event.
                        </p>
                    </div>
                    <UBadge color="primary" variant="subtle">native</UBadge>
                </div>

                <div class="space-y-4">
                    <div
                        class="rounded-lg border border-default bg-default p-4"
                    >
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <p class="text-xs text-muted">PHP work</p>
                                <p class="font-medium">Intent + event</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">Checksum</p>
                                <p class="font-medium">In Go handler</p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">Request time</p>
                                <p class="font-medium">Off app workers</p>
                            </div>
                        </div>
                    </div>

                    <UProgress :model-value="pogoLane.progress" />

                    <UButton
                        icon="i-lucide-play"
                        :loading="pogoLane.running"
                        :disabled="
                            pogoLane.running ||
                            rawLane.running ||
                            !uploadAvailable ||
                            payloadTooLarge
                        "
                        @click="runPogoUpload"
                    >
                        Send through Pogo
                    </UButton>

                    <UAlert
                        v-if="pogoLane.error"
                        color="error"
                        variant="subtle"
                        icon="i-lucide-circle-alert"
                        :title="pogoLane.error"
                    />

                    <div
                        v-if="pogoLane.result || serverProgress || workerEvent"
                        class="rounded-lg border border-default bg-default p-4"
                    >
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div>
                                <p class="text-xs text-muted">Elapsed</p>
                                <p class="font-semibold">
                                    {{ pogoLane.elapsedMs ?? 'running' }}ms
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">
                                    Server progress
                                </p>
                                <p class="font-semibold">
                                    {{
                                        formatBytes(
                                            serverProgress?.bytes_received,
                                        )
                                    }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-muted">Worker event</p>
                                <p class="font-semibold">
                                    {{ workerEvent?.type ?? 'pending' }}
                                </p>
                            </div>
                        </div>
                        <p
                            v-if="
                                workerEvent?.sha256 || pogoLane.result?.sha256
                            "
                            class="mt-3 text-xs break-all text-muted"
                        >
                            {{ workerEvent?.sha256 ?? pogoLane.result?.sha256 }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-default bg-elevated/30 p-4">
            <div class="grid gap-4 lg:grid-cols-[1fr_2fr]">
                <div>
                    <h2 class="font-semibold">Payload</h2>
                    <p class="mt-1 text-sm text-muted">
                        {{ selectedPayload.source }} / {{ sizeLabel }}
                    </p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <p class="text-xs text-muted">Filename</p>
                        <p class="font-medium break-all">
                            {{ selectedPayload.filename }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted">Accepted types</p>
                        <p class="font-medium break-all">
                            {{ acceptedLabel }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted">Configured limit</p>
                        <p class="font-medium">{{ maxBytesLabel }}</p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
