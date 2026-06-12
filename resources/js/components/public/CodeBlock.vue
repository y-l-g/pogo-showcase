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
        import('@shikijs/themes/github-dark'),
    ]).then(
        ([
            { createHighlighterCore },
            { createJavaScriptRegexEngine },
            javascript,
            php,
            githubLight,
            githubDark,
        ]) =>
            createHighlighterCore({
                themes: [githubLight.default, githubDark.default],
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
                dark: 'github-dark',
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
        class="code-block h-full overflow-hidden rounded-lg border border-default bg-muted/40"
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

        <div v-if="highlightedCode" v-html="highlightedCode" />
        <pre
            v-else
            class="min-h-56 overflow-auto p-4 text-xs leading-relaxed break-words whitespace-pre-wrap text-toned"
        ><code>{{ code }}</code></pre>
    </div>
</template>

<style scoped>
.code-block :deep(.shiki) {
    min-height: 14rem;
    margin: 0;
    overflow: auto;
    background: transparent !important;
    padding: 1rem;
    color: var(--shiki-light);
    font-size: 0.75rem;
    line-height: 1.625;
    white-space: pre-wrap;
    overflow-wrap: break-word;
}

.code-block :deep(.shiki span) {
    color: var(--shiki-light);
    background: transparent !important;
}

:global(.dark) .code-block :deep(.shiki),
:global(.dark) .code-block :deep(.shiki span) {
    color: var(--shiki-dark);
}
</style>
