<script setup lang="ts">
import { intent, progress, raw } from '@/routes/showcase/upload';
import { computed, ref } from 'vue';

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

type UploadResult = {
    ok: boolean;
    elapsed_ms?: number;
    php_elapsed_ms?: number;
    upload_id?: string;
    method?: string;
    url?: string;
    headers?: Record<string, string>;
    error?: string | { code?: string; message?: string };
};

type PressureMode = 'raw' | 'pogo';

type PressureState = {
    running: boolean;
    mode: PressureMode | null;
    uploadsCompleted: number;
    uploadsFailed: number;
    bytesStreamed: number;
    appWorkerMs: number;
    error: string | null;
    summary: string | null;
};

type PressureSnapshot = {
    mode: PressureMode;
    uploadsCompleted: number;
    uploadsFailed: number;
    bytesStreamed: number;
    appWorkerMs: number;
};

const props = defineProps<{
    uploadAvailable: boolean;
    uploadStatus: UploadStatus;
    maxBytes: number;
    acceptedContentTypes: string[];
}>();

const pressureUploadCount = 5;
const pressurePayloadSize = 768 * 1024;
const pressureRawDelayMs = 160;
const pressure = ref<PressureState>(emptyPressure());
const pressureSnapshots = ref<Record<PressureMode, PressureSnapshot | null>>({
    raw: null,
    pogo: null,
});
const liveStatus = ref<UploadStatus>(props.uploadStatus);

const csrfHeaders = () => {
    const xsrfToken = document.cookie
        .split('; ')
        .find((cookie) => cookie.startsWith('XSRF-TOKEN='))
        ?.split('=')
        .slice(1)
        .join('=');

    if (xsrfToken) {
        return {
            'X-XSRF-TOKEN': decodeURIComponent(xsrfToken),
        };
    }

    return {
        'X-CSRF-TOKEN':
            document
                .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.getAttribute('content') ?? '',
    };
};

const maxBytesLabel = computed(() => formatBytes(props.maxBytes));
const pressureConfigLabel = computed(
    () =>
        `${pressureUploadCount} uploads x ${formatBytes(pressurePayloadSize)}`,
);
const pressureSnapshotRows = computed(() =>
    [pressureSnapshots.value.raw, pressureSnapshots.value.pogo].filter(
        (snapshot): snapshot is PressureSnapshot => snapshot !== null,
    ),
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

function emptyPressure(): PressureState {
    return {
        running: false,
        mode: null,
        uploadsCompleted: 0,
        uploadsFailed: 0,
        bytesStreamed: 0,
        appWorkerMs: 0,
        error: null,
        summary: null,
    };
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

function resetCurrentPressure() {
    pressure.value = emptyPressure();
}

function resetPressure() {
    resetCurrentPressure();
    pressureSnapshots.value = {
        raw: null,
        pogo: null,
    };
}

async function runPressureTest(mode: PressureMode) {
    if (pressure.value.running) {
        return;
    }

    resetCurrentPressure();

    pressure.value.running = true;
    pressure.value.mode = mode;

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

        pressureSnapshots.value = {
            ...pressureSnapshots.value,
            [mode]: {
                mode,
                uploadsCompleted: pressure.value.uploadsCompleted,
                uploadsFailed: pressure.value.uploadsFailed,
                bytesStreamed: pressure.value.bytesStreamed,
                appWorkerMs: pressure.value.appWorkerMs,
            },
        };

        pressure.value.summary =
            failures.length === 0
                ? mode === 'raw'
                    ? `Raw uploads spent ${formatDuration(pressure.value.appWorkerMs)} inside Laravel HTTP workers receiving bodies.`
                    : `Pogo spent ${formatDuration(pressure.value.appWorkerMs)} in Laravel intent routes; upload bodies stayed in the native handler.`
                : null;
    } catch (error) {
        pressure.value.error =
            error instanceof Error ? error.message : 'Pressure test failed.';
    } finally {
        pressure.value.running = false;
    }
}

async function runPressureUpload(mode: PressureMode, index: number) {
    try {
        const payload = makePressurePayload();
        const filename = `pressure-${mode}-${index}.txt`;

        if (mode === 'raw') {
            const result = await sendPressureUpload(
                raw().url,
                'POST',
                payload,
                {
                    'Content-Type': 'text/plain',
                    ...csrfHeaders(),
                    'X-Upload-Filename': filename,
                    'X-Upload-Pressure-Delay-Ms': pressureRawDelayMs.toString(),
                },
                (bytes) => {
                    pressure.value.bytesStreamed += bytes;
                },
            );
            recordAppWorkerTime(result);
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
            ...csrfHeaders(),
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

    recordAppWorkerTime(created);

    await sendPressureUpload(
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

    void refreshPressureStatus(created.upload_id);
}

function sendPressureUpload(
    url: string,
    method: string,
    body: Blob,
    headers: Record<string, string>,
    onBytes: (bytes: number) => void,
): Promise<UploadResult> {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        let lastLoaded = 0;

        xhr.open(method, url);
        xhr.responseType = 'json';
        xhr.setRequestHeader('Accept', 'application/json');
        for (const [key, value] of Object.entries(headers)) {
            xhr.setRequestHeader(key, value);
        }

        xhr.upload.onprogress = (event) => {
            if (event.loaded > lastLoaded) {
                onBytes(event.loaded - lastLoaded);
                lastLoaded = event.loaded;
            }
        };
        xhr.onerror = () => reject(new Error('Network error during upload.'));
        xhr.onload = () => {
            if (body.size > lastLoaded) {
                onBytes(body.size - lastLoaded);
            }

            const response =
                typeof xhr.response === 'object' && xhr.response !== null
                    ? (xhr.response as UploadResult)
                    : parseJson(xhr.responseText);

            if (
                xhr.status >= 200 &&
                xhr.status < 300 &&
                response.ok !== false
            ) {
                resolve(response);
                return;
            }

            reject(
                new Error(
                    errorMessage(
                        response.error ?? {
                            code: 'http_error',
                            message: `HTTP ${xhr.status}`,
                        },
                    ),
                ),
            );
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

function recordAppWorkerTime(result: UploadResult) {
    pressure.value.appWorkerMs +=
        result.php_elapsed_ms ?? result.elapsed_ms ?? 0;
}

async function refreshPressureStatus(uploadId: string) {
    try {
        const response = await fetch(progress(uploadId).url, {
            headers: {
                Accept: 'application/json',
            },
        });
        const payload = (await response.json()) as {
            status: UploadStatus;
        };

        liveStatus.value = payload.status;
    } catch {
        // Pressure metrics are still valid if the status refresh races completion.
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

function formatDuration(value?: number | null): string {
    const ms = value ?? 0;

    if (ms < 1000) {
        return `${Math.round(ms)}ms`;
    }

    return `${(ms / 1000).toFixed(ms < 10000 ? 1 : 0)}s`;
}

function pressureOwnerLabel(mode: PressureMode): string {
    return mode === 'raw' ? 'Laravel receives bodies' : 'Pogo receives bodies';
}
</script>

<template>
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 sm:p-6">
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
                    {{ pressureConfigLabel }}
                </UBadge>
                <UBadge color="neutral" variant="subtle">
                    Limit {{ maxBytesLabel }}
                </UBadge>
            </div>
            <h1 class="text-2xl font-semibold tracking-normal">
                Upload pressure isolation
            </h1>
            <p class="mt-2 max-w-3xl text-sm text-muted">
                Laravel should authorize uploads, not spend HTTP worker time
                receiving slow bodies. The upload module moves the body stream,
                size checks, checksum, and storage into the native handler.
            </p>
        </div>

        <UAlert
            v-if="!uploadAvailable"
            color="warning"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            title="Pogo upload is not loaded in this runtime"
            description="The raw baseline still runs. Start the FrankenPHP binary compiled with the Pogo upload module to run the native ingress path."
        />

        <section class="grid gap-3 md:grid-cols-3">
            <div class="rounded-lg border border-default bg-elevated/30 p-4">
                <div class="mb-3 flex items-center gap-2">
                    <UIcon
                        name="i-lucide-key-round"
                        class="size-5 text-muted"
                    />
                    <h2 class="font-semibold">Authorize</h2>
                </div>
                <p class="text-sm text-muted">
                    Laravel validates the user and signs a short-lived upload
                    intent.
                </p>
            </div>
            <div class="rounded-lg border border-primary/40 bg-primary/5 p-4">
                <div class="mb-3 flex items-center gap-2">
                    <UIcon
                        name="i-lucide-hard-drive-upload"
                        class="size-5 text-primary"
                    />
                    <h2 class="font-semibold">Receive</h2>
                </div>
                <p class="text-sm text-muted">
                    Pogo receives the body, enforces limits, stores bytes, and
                    computes the checksum.
                </p>
            </div>
            <div class="rounded-lg border border-default bg-elevated/30 p-4">
                <div class="mb-3 flex items-center gap-2">
                    <UIcon
                        name="i-lucide-message-square-check"
                        class="size-5 text-muted"
                    />
                    <h2 class="font-semibold">Report</h2>
                </div>
                <p class="text-sm text-muted">
                    Laravel receives the completion event with metadata after
                    the body is already handled.
                </p>
            </div>
        </section>

        <section class="rounded-lg border border-default bg-elevated/30 p-4">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <h2 class="font-semibold">Compare Laravel worker time</h2>
                    <p class="mt-1 max-w-3xl text-sm text-muted">
                        Both paths send the same total body size. The useful
                        number is how much Laravel HTTP worker time is consumed
                        while those bodies are being received.
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

            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
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
                <div
                    class="rounded-lg border border-primary/40 bg-primary/5 p-4"
                >
                    <p class="text-xs text-muted">Laravel HTTP time</p>
                    <p class="mt-1 font-semibold">
                        {{
                            pressure.appWorkerMs > 0
                                ? formatDuration(pressure.appWorkerMs)
                                : 'waiting'
                        }}
                    </p>
                </div>
                <div class="rounded-lg border border-default bg-default p-4">
                    <p class="text-xs text-muted">Body streamed</p>
                    <p class="mt-1 font-semibold">
                        {{ formatBytes(pressure.bytesStreamed) }}
                    </p>
                </div>
            </div>

            <div
                v-if="pressureSnapshotRows.length > 0"
                class="mt-4 grid gap-3 lg:grid-cols-2"
            >
                <div
                    v-for="snapshot in pressureSnapshotRows"
                    :key="snapshot.mode"
                    class="rounded-lg border border-default bg-default p-4"
                >
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium">
                                {{
                                    snapshot.mode === 'raw'
                                        ? 'Raw PHP'
                                        : 'Pogo Upload'
                                }}
                            </p>
                            <p class="text-xs text-muted">
                                {{ pressureOwnerLabel(snapshot.mode) }}
                            </p>
                        </div>
                        <UBadge
                            :color="
                                snapshot.mode === 'raw' ? 'neutral' : 'primary'
                            "
                            variant="subtle"
                        >
                            {{ snapshot.uploadsCompleted }}/{{
                                pressureUploadCount
                            }}
                        </UBadge>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <p class="text-xs text-muted">Laravel HTTP time</p>
                            <p class="font-semibold">
                                {{ formatDuration(snapshot.appWorkerMs) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted">Body streamed</p>
                            <p class="font-semibold">
                                {{ formatBytes(snapshot.bytesStreamed) }}
                            </p>
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

        <section
            v-if="uploadAvailable"
            class="rounded-lg border border-default bg-elevated/30 p-4"
        >
            <div class="mb-4">
                <h2 class="font-semibold">Native handler counters</h2>
                <p class="mt-1 text-sm text-muted">
                    These counters come from the upload module, not from the raw
                    Laravel endpoint.
                </p>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-lg border border-default bg-default p-4">
                    <p class="text-xs text-muted">Accepted</p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ liveStatus.accepted }}
                    </p>
                </div>
                <div class="rounded-lg border border-default bg-default p-4">
                    <p class="text-xs text-muted">Completed</p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ liveStatus.completed }}
                    </p>
                </div>
                <div class="rounded-lg border border-default bg-default p-4">
                    <p class="text-xs text-muted">Active</p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ liveStatus.active_uploads }}
                    </p>
                </div>
                <div class="rounded-lg border border-default bg-default p-4">
                    <p class="text-xs text-muted">Bytes received</p>
                    <p class="mt-1 text-2xl font-semibold">
                        {{ formatBytes(liveStatus.bytes_received) }}
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
