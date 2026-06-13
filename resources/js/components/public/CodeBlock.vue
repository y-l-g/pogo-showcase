<script setup lang="ts">
import { useClipboard } from '@vueuse/core';
import { onMounted, ref, watch } from 'vue';

type Highlighter = {
    codeToHtml: (
        code: string,
        options: {
            lang: string;
            themes: {
                light: string;
                dark: string;
            };
            defaultColor: false;
        },
    ) => string;
};

let highlighterPromise: Promise<Highlighter> | null = null;

const getHighlighter = () => {
    highlighterPromise ??= Promise.all([
        import('shiki/core'),
        import('shiki/engine/javascript'),
        import('@shikijs/langs/javascript'),
        import('@shikijs/langs/php'),
        import('@shikijs/themes/github-light'),
        import('@shikijs/themes/github-dark-high-contrast'),
    ]).then(
        ([
            { createHighlighterCore },
            { createJavaScriptRegexEngine },
            javascript,
            php,
            githubLight,
            githubDarkHighContrast,
        ]) =>
            createHighlighterCore({
                themes: [githubLight.default, githubDarkHighContrast.default],
                langs: [javascript.default, php.default],
                engine: createJavaScriptRegexEngine(),
            }),
    );

    return highlighterPromise;
};

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
const highlightedCode = ref<string | null>(null);
let highlightRun = 0;

const copyCode = () => {
    void copy(props.code);
};

const highlightCode = async () => {
    const run = ++highlightRun;

    try {
        const highlighter = await getHighlighter();
        const html = highlighter.codeToHtml(props.code, {
            lang: props.language,
            themes: {
                light: 'github-light',
                dark: 'github-dark-high-contrast',
            },
            defaultColor: false,
        });

        if (run === highlightRun) {
            highlightedCode.value = html;
        }
    } catch {
        highlightedCode.value = null;
    }
};

onMounted(() => {
    void highlightCode();
});

watch(
    () => [props.code, props.language],
    () => {
        void highlightCode();
    },
);
</script>

<template>
    <div
        class="code-block h-full overflow-hidden rounded-lg border border-default bg-white/80 shadow-sm dark:border-white/10 dark:bg-[#0a0c10]"
    >
        <div
            class="flex items-center justify-between gap-3 border-b border-default bg-muted/30 px-3 py-2.5 sm:px-4 dark:border-white/10 dark:bg-white/[0.03]"
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

        <div v-if="highlightedCode" v-html="highlightedCode" />
        <pre
            v-else
            class="min-h-56 overflow-auto bg-white p-4 text-[13px] leading-relaxed whitespace-pre text-toned dark:bg-[#0a0c10] dark:text-white"
        ><code>{{ code }}</code></pre>
    </div>
</template>

<style scoped>
.code-block :deep(.shiki) {
    min-height: 14rem;
    margin: 0;
    overflow: auto;
    background: var(--shiki-light-bg) !important;
    padding: 1rem;
    color: var(--shiki-light);
    font-size: 0.8125rem;
    line-height: 1.625;
    white-space: pre;
}

.code-block :deep(.shiki code) {
    display: block;
    min-width: max-content;
}

.code-block :deep(.shiki span) {
    color: var(--shiki-light);
    background: transparent !important;
}

:global(.dark .code-block .shiki) {
    background: var(--shiki-dark-bg) !important;
    color: var(--shiki-dark);
}

:global(.dark .code-block .shiki span) {
    color: var(--shiki-dark);
}
</style>
