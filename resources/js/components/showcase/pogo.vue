<script setup lang="ts">
import { run } from '@/routes/showcase/pogo';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

type PogoJobResult = {
    key: string;
    label: string;
    expected_delay_ms: number;
    result: Record<string, any>;
};

type PogoDemoResult = {
    sku: string;
    quantity: number;
    pool: string;
    workers: number;
    elapsed_ms: number;
    sequential_estimate_ms: number;
    saved_ms: number;
    jobs: PogoJobResult[];
};

const props = defineProps<{
    pogoAvailable: boolean;
    poolSizes: Record<string, number>;
    demoResult?: PogoDemoResult | null;
}>();

const form = useForm({
    sku: props.demoResult?.sku ?? 'POGO-001',
    quantity: props.demoResult?.quantity ?? 3,
});

const externalWorkers = computed(() => props.poolSizes.external_api ?? 0);
const defaultWorkers = computed(() => props.poolSizes.default ?? 0);
const savedRatio = computed(() => {
    if (!props.demoResult?.sequential_estimate_ms) {
        return 0;
    }

    return Math.max(
        0,
        Math.round(
            (props.demoResult.saved_ms /
                props.demoResult.sequential_estimate_ms) *
                100,
        ),
    );
});

const submit = () => {
    form.submit(run(), {
        preserveScroll: true,
    });
};

const stringify = (value: unknown) => JSON.stringify(value, null, 2);
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
                <p class="text-sm text-muted">Default pool</p>
                <p class="text-xl font-semibold">
                    {{ defaultWorkers }} workers
                </p>
            </UCard>

            <UCard variant="soft">
                <p class="text-sm text-muted">External API pool</p>
                <p class="text-xl font-semibold">
                    {{ externalWorkers }} workers
                </p>
            </UCard>
        </div>

        <UAlert
            v-if="!pogoAvailable"
            color="warning"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            title="Pogo is not loaded in this runtime"
            description="Start the FrankenPHP binary compiled with the Pogo module to run the parallel job demo."
        />

        <section class="grid gap-6 lg:grid-cols-[minmax(0,24rem)_1fr]">
            <UCard variant="soft">
                <template #header>
                    <div class="flex items-center gap-2">
                        <UIcon name="i-lucide-workflow" class="size-5" />
                        <h2 class="font-semibold">Request fan-out</h2>
                    </div>
                </template>

                <form class="space-y-4" @submit.prevent="submit">
                    <UFormField label="SKU" name="sku" :error="form.errors.sku">
                        <UInput
                            v-model="form.sku"
                            class="w-full"
                            autocomplete="off"
                            placeholder="POGO-001"
                        />
                    </UFormField>

                    <UFormField
                        label="Quantity"
                        name="quantity"
                        :error="form.errors.quantity"
                    >
                        <UInput
                            v-model.number="form.quantity"
                            class="w-full"
                            type="number"
                            min="1"
                            max="20"
                        />
                    </UFormField>

                    <UButton
                        type="submit"
                        block
                        icon="i-lucide-play"
                        :loading="form.processing"
                        :disabled="!pogoAvailable"
                    >
                        Run parallel jobs
                    </UButton>
                </form>
            </UCard>

            <UCard variant="soft">
                <template #header>
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="font-semibold">Latest run</h2>
                            <p class="text-sm text-muted">
                                pogo_dispatch + pogo_await
                            </p>
                        </div>
                        <UBadge v-if="demoResult" color="primary" variant="subtle">
                            {{ demoResult.pool }}
                        </UBadge>
                    </div>
                </template>

                <div
                    v-if="!demoResult"
                    class="flex min-h-52 items-center justify-center text-sm text-muted"
                >
                    No run yet
                </div>

                <div v-else class="space-y-5">
                    <div class="grid gap-3 sm:grid-cols-4">
                        <div>
                            <p class="text-sm text-muted">Elapsed</p>
                            <p class="text-2xl font-semibold">
                                {{ demoResult.elapsed_ms }}ms
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-muted">Sequential</p>
                            <p class="text-2xl font-semibold">
                                {{ demoResult.sequential_estimate_ms }}ms
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-muted">Saved</p>
                            <p class="text-2xl font-semibold">
                                {{ savedRatio }}%
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-muted">Workers</p>
                            <p class="text-2xl font-semibold">
                                {{ demoResult.workers }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 xl:grid-cols-3">
                        <UCard
                            v-for="job in demoResult.jobs"
                            :key="job.key"
                            variant="subtle"
                            :ui="{ body: 'space-y-3' }"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <h3 class="font-medium">{{ job.label }}</h3>
                                <UBadge color="neutral" variant="outline">
                                    {{ job.expected_delay_ms }}ms
                                </UBadge>
                            </div>
                            <pre
                                class="overflow-auto rounded bg-elevated p-3 text-xs leading-5 text-muted"
                            >{{ stringify(job.result) }}</pre>
                        </UCard>
                    </div>
                </div>
            </UCard>
        </section>
    </div>
</template>
