<script setup lang="ts">
import { useClipboard } from '@vueuse/core';

const props = withDefaults(
    defineProps<{
        code: string;
        filename: string;
        language?: string;
    }>(),
    {
        language: 'php',
    },
);

const { copied, copy } = useClipboard();

const copyCode = () => {
    void copy(props.code);
};
</script>

<template>
    <div
        class="h-full overflow-hidden rounded-lg border border-default bg-muted/40"
    >
        <div
            class="flex items-center justify-between gap-3 border-b border-default px-3 py-2.5 sm:px-4"
        >
            <div class="flex min-w-0 items-center gap-2">
                <UIcon
                    name="i-lucide-file-code-2"
                    class="size-4 shrink-0 text-muted"
                />
                <span class="truncate text-xs font-medium text-toned">
                    {{ filename }}
                </span>
                <UBadge color="neutral" variant="subtle" size="sm">
                    {{ language }}
                </UBadge>
            </div>

            <UButton
                :icon="copied ? 'i-lucide-check' : 'i-lucide-copy'"
                color="neutral"
                variant="ghost"
                size="sm"
                :aria-label="copied ? 'Copied' : 'Copy code'"
                @click="copyCode"
            />
        </div>

        <pre
            class="h-full min-h-56 overflow-auto p-4 text-xs leading-relaxed break-words whitespace-pre-wrap text-toned"
        ><code>{{ code }}</code></pre>
    </div>
</template>
