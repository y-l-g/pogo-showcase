<script setup lang="ts">
import { usePoll } from '@inertiajs/vue3';

type SchedulerData = {
    color: string;
    count: number;
    last_run: string;
};

defineProps<{
    schedulerData: SchedulerData;
}>();

usePoll(
    1000,
    {
        only: ['schedulerData'],
    },
    {
        keepAlive: true,
    },
);
</script>

<template>
    <div
        class="flex min-h-screen items-center justify-center bg-gray-50 p-10 dark:bg-gray-900"
    >
        <UCard
            class="w-full max-w-sm border-2 text-center transition-all duration-500 ease-in-out"
            :style="{ borderColor: schedulerData.color }"
        >
            <template #header>
                <h2
                    class="flex items-center justify-center gap-2 text-xl font-bold"
                >
                    <UIcon name="i-heroicons-clock" class="h-6 w-6" />
                    Pogo Scheduler
                </h2>
            </template>

            <div class="flex flex-col items-center justify-center gap-4 py-6">
                <div
                    class="flex h-32 w-32 items-center justify-center rounded-full text-2xl font-bold text-white shadow-lg transition-colors duration-500"
                    :style="{ backgroundColor: schedulerData.color }"
                >
                    {{ schedulerData.count }}
                </div>

                <div class="space-y-1">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Last run :
                    </p>
                    <UBadge
                        size="lg"
                        variant="subtle"
                        :color="
                            schedulerData.color === '#gray' ? 'gray' : 'primary'
                        "
                    >
                        {{ schedulerData.last_run }}
                    </UBadge>
                </div>
            </div>

            <template #footer>
                <p class="text-xs text-gray-400">
                    Automatic update via Inertia Polling
                </p>
            </template>
        </UCard>
    </div>
</template>
