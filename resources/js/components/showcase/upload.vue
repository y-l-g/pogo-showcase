<script setup lang="ts">
import { intent, progress, raw } from '@/routes/showcase/upload';
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

const props = defineProps<{
    uploadAvailable: boolean;
    uploadStatus: UploadStatus;
    maxBytes: number;
    acceptedContentTypes: string[];
}>();

const file = ref<File | null>(null);
const generatedPayload = ref<Blob | null>(null);
const generatedSize = 2 * 1024 * 1024;
const rawLane = ref<LaneState>(emptyLane());
const pogoLane = ref<LaneState>(emptyLane());
const serverProgress = ref<UploadProgress | null>(null);
const workerEvent = ref<UploadEvent | null>(null);
const liveStatus = ref<UploadStatus>(props.uploadStatus);
let pollTimer: number | null = null;
let pollAttempts = 0;
const maxPollAttempts = 45;

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

function makeGeneratedPayload(): Blob {
    const chunk = new TextEncoder().encode(
        'Pogo upload showcase payload. This text is repeated so the browser has a visible body to send.\n',
    );
    const bytes = new Uint8Array(generatedSize);

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
                ok: false,
                ...response,
                error: response.error ?? {
                    code: 'http_error',
                    message: `HTTP ${xhr.status}`,
                },
            });
        };

        xhr.send(body);
    });
}

function parseJson(value: string): UploadResult {
    try {
        return JSON.parse(value) as UploadResult;
    } catch {
        return { ok: false };
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
