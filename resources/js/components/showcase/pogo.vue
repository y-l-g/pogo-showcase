<script setup lang="ts">
import { run } from '@/routes/showcase/pogo';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

type Mode = 'sequential' | 'parallel';
type JobKey =
    | 'current_weather'
    | 'daily_forecast'
    | 'air_quality'
    | 'elevation';

type LocationResult = {
    display: string;
    latitude: number;
    longitude: number;
    timezone?: string | null;
};

type ApiResult = Record<string, number | string | null | undefined>;

type ApiJobResult = {
    key: JobKey;
    label: string;
    result: ApiResult;
};

type RunResult = {
    city: string;
    mode: Mode;
    location: LocationResult;
    pool?: string | null;
    workers?: number | null;
    elapsed_ms: number;
    jobs: ApiJobResult[];
};

type DemoResults = Partial<Record<Mode, RunResult>>;

const props = defineProps<{
    pogoAvailable: boolean;
    poolSizes: Record<string, number>;
    demoResults?: DemoResults;
}>();

const initialCity =
    props.demoResults?.parallel?.city ??
    props.demoResults?.sequential?.city ??
    'Paris';

const form = useForm<{ city: string; mode: Mode }>({
    city: initialCity,
    mode: 'parallel',
});

const externalWorkers = computed(() => props.poolSizes.external_api ?? 0);
const sequentialResult = computed(() => props.demoResults?.sequential ?? null);
const parallelResult = computed(() => props.demoResults?.parallel ?? null);

const submit = (mode: Mode) => {
    form.mode = mode;
    form.submit(run(), {
        preserveScroll: true,
    });
};

const format = (value?: number | string | null, unit?: string | null) =>
    value === null || value === undefined || value === ''
        ? 'n/a'
        : `${value}${unit ?? ''}`;

const job = (result: RunResult | null, key: JobKey) =>
    result?.jobs.find((item) => item.key === key)?.result ?? {};

const cards = (result: RunResult | null) => {
    const current = job(result, 'current_weather');
    const daily = job(result, 'daily_forecast');
    const air = job(result, 'air_quality');
    const elevation = job(result, 'elevation');

    return [
        {
            key: 'current_weather',
            icon: 'i-lucide-thermometer',
            title: 'Current weather',
            primary: format(current.temperature, current.temperature_unit),
            detail: `Humidity ${format(current.humidity, current.humidity_unit)} / Wind ${format(current.wind_speed, current.wind_speed_unit)}`,
        },
        {
            key: 'daily_forecast',
            icon: 'i-lucide-calendar-days',
            title: 'Daily forecast',
            primary: `${format(daily.high, daily.daily_temperature_unit)} / ${format(daily.low, daily.daily_temperature_unit)}`,
            detail: `Rain probability ${format(daily.precipitation_probability, daily.precipitation_probability_unit)}`,
        },
        {
            key: 'air_quality',
            icon: 'i-lucide-wind',
            title: 'Air quality',
            primary: format(air.european_aqi),
            detail: `${format(air.category)} / PM2.5 ${format(air.pm2_5, air.pm2_5_unit)}`,
        },
        {
            key: 'elevation',
            icon: 'i-lucide-mountain',
            title: 'Elevation',
            primary: format(elevation.elevation, elevation.elevation_unit),
            detail: 'Open-Meteo elevation API',
        },
    ];
};
</script>

<template>
    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 sm:p-6">
        <div class="grid gap-3 sm:grid-cols-3">
            <UCard variant="soft">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm text-muted">Extension</p>
                        <p class="text-xl font-semibold">
                            {{ pogoAvailable ? 'Loaded' : 'Unavailable' }}
                        </p>
                    </div>
                    <UBadge
                        :color="pogoAvailable ? 'success' : 'warning'"
                        variant="subtle"
                    >
                        {{ pogoAvailable ? 'ready' : 'missing' }}
                    </UBadge>
                </div>
            </UCard>

            <UCard variant="soft">
                <p class="text-sm text-muted">External API pool</p>
                <p class="text-xl font-semibold">
                    {{ externalWorkers }} workers
                </p>
            </UCard>

            <UCard variant="soft">
                <p class="text-sm text-muted">Fetches per run</p>
                <p class="text-xl font-semibold">4 real APIs</p>
            </UCard>
        </div>

        <UAlert
            v-if="!pogoAvailable"
            color="warning"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            title="Pogo is not loaded in this runtime"
            description="Standard PHP mode still works; start the FrankenPHP binary compiled with the Pogo module to run the parallel comparison."
        />

        <UCard variant="soft">
            <template #header>
                <div class="flex items-center gap-2">
                    <UIcon name="i-lucide-cloud-sun" class="size-5" />
                    <h2 class="font-semibold">City conditions comparison</h2>
                </div>
            </template>

            <UFormField label="City" name="city" :error="form.errors.city">
                <UInput
                    v-model="form.city"
                    class="w-full max-w-md"
                    autocomplete="off"
                    placeholder="Paris"
                />
            </UFormField>
        </UCard>

        <section class="grid gap-6 xl:grid-cols-2">
            <UCard variant="soft">
                <template #header>
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="font-semibold">Standard PHP</h2>
                            <p class="text-sm text-muted">
                                Same Open-Meteo fetches, one after another
                            </p>
                        </div>
                        <UBadge color="neutral" variant="subtle">
                            sequential
                        </UBadge>
                    </div>
                </template>

                <div class="space-y-5">
                    <UButton
                        icon="i-lucide-play"
                        :loading="form.processing && form.mode === 'sequential'"
                        :disabled="form.processing"
                        @click="submit('sequential')"
                    >
                        Fetch live APIs
                    </UButton>

                    <div
                        v-if="!sequentialResult"
                        class="flex min-h-64 items-center justify-center text-sm text-muted"
                    >
                        No standard PHP run yet
                    </div>

                    <div v-else class="space-y-5">
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p class="text-sm text-muted">
                                    Resolved location
                                </p>
                                <p class="text-xl font-semibold">
                                    {{ sequentialResult.location.display }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-muted">Elapsed</p>
                                <p class="text-xl font-semibold">
                                    {{ sequentialResult.elapsed_ms }}ms
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div
                                v-for="item in cards(sequentialResult)"
                                :key="item.key"
                                class="rounded-lg border border-default p-4"
                            >
                                <div class="mb-3 flex items-center gap-2">
                                    <UIcon :name="item.icon" class="size-5" />
                                    <h3 class="font-medium">
                                        {{ item.title }}
                                    </h3>
                                </div>
                                <p class="text-xl font-semibold">
                                    {{ item.primary }}
                                </p>
                                <p class="mt-1 text-sm text-muted">
                                    {{ item.detail }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </UCard>

            <UCard variant="soft">
                <template #header>
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="font-semibold">Pogo parallel</h2>
                            <p class="text-sm text-muted">
                                Same Open-Meteo fetches, dispatched together
                            </p>
                        </div>
                        <UBadge color="primary" variant="subtle">
                            {{ parallelResult?.pool ?? 'external_api' }}
                        </UBadge>
                    </div>
                </template>

                <div class="space-y-5">
                    <UButton
                        icon="i-lucide-play"
                        :loading="form.processing && form.mode === 'parallel'"
                        :disabled="form.processing || !pogoAvailable"
                        @click="submit('parallel')"
                    >
                        Fetch live APIs
                    </UButton>

                    <div
                        v-if="!parallelResult"
                        class="flex min-h-64 items-center justify-center text-sm text-muted"
                    >
                        No Pogo run yet
                    </div>

                    <div v-else class="space-y-5">
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p class="text-sm text-muted">
                                    Resolved location
                                </p>
                                <p class="text-xl font-semibold">
                                    {{ parallelResult.location.display }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-muted">Elapsed</p>
                                <p class="text-xl font-semibold">
                                    {{ parallelResult.elapsed_ms }}ms
                                </p>
                                <p class="text-sm text-muted">
                                    {{ parallelResult.workers ?? 0 }} workers
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div
                                v-for="item in cards(parallelResult)"
                                :key="item.key"
                                class="rounded-lg border border-default p-4"
                            >
                                <div class="mb-3 flex items-center gap-2">
                                    <UIcon :name="item.icon" class="size-5" />
                                    <h3 class="font-medium">
                                        {{ item.title }}
                                    </h3>
                                </div>
                                <p class="text-xl font-semibold">
                                    {{ item.primary }}
                                </p>
                                <p class="mt-1 text-sm text-muted">
                                    {{ item.detail }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </UCard>
        </section>
    </div>
</template>
