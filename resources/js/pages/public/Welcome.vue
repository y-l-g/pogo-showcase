<script setup lang="ts">
import LandingExamples from '@/components/public/LandingExamples.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { register } from '@/routes';
import { motion } from 'motion-v';

useSeoMeta({
    title: 'Pogo Showcase',
    description:
        'See concrete Pogo examples for FrankenPHP: realtime chat, parallel jobs, queue workers, and scheduler polling.',
});
</script>

<template>
    <PublicLayout>
        <motion.div
            :initial="{ opacity: 0, y: 28 }"
            :animate="{
                opacity: 1,
                y: 0,
                transition: { duration: 0.4, ease: 'easeOut' },
            }"
        >
            <UPageHero
                :ui="{
                    container:
                        'items-center justify-center py-14 sm:py-16 lg:py-20',
                    title: 'mx-auto max-w-4xl',
                    description: 'mx-auto max-w-2xl',
                }"
                title="Pogo extensions for FrankenPHP"
                description="Run realtime chat, parallel jobs, queues, and scheduler examples from one FrankenPHP application."
            >
                <template #links>
                    <motion.div
                        :initial="{ opacity: 0, y: 24 }"
                        :animate="{
                            opacity: 1,
                            y: 0,
                            transition: {
                                delay: 0.15,
                                duration: 0.4,
                                ease: 'easeOut',
                            },
                        }"
                        class="flex flex-wrap justify-center gap-3"
                    >
                        <UButton
                            :to="register().url"
                            size="xl"
                            label="Get started"
                            icon="i-lucide-arrow-right"
                            trailing
                        />
                        <UButton
                            to="https://github.com/y-l-g/websocket"
                            target="_blank"
                            size="xl"
                            label="GitHub"
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-github"
                        />
                    </motion.div>
                </template>
            </UPageHero>
        </motion.div>

        <LandingExamples />
    </PublicLayout>
</template>
