<script setup lang="ts">
import PublicLayout from '@/layouts/PublicLayout.vue';
import { register } from '@/routes';
import { motion } from 'motion-v';
import ILucideAlarmClockCheck from '~icons/lucide/alarm-clock-check';
import ILucideArrowDownUp from '~icons/lucide/arrow-down-up';
import ILucideArrowRight from '~icons/lucide/arrow-right';
import ILucideGithub from '~icons/lucide/github';
import ILucideListStart from '~icons/lucide/list-start';

useSeoMeta({
    title: 'Home',
    description: 'Pogo Showcase',
});

const architecturalPillars = [
    {
        title: 'Websocket',
        description:
            'Built in fast websocket server, can replace Laravel Reverb or Pusher external service.',
        icon: ILucideArrowDownUp,
    },
    {
        title: 'Queue',
        description:
            'Built in fast in-memory queue (no persistence) can replace php artisan queue:work or horizon in some cases.',
        icon: ILucideListStart,
    },
    {
        title: 'Scheduler',
        description:
            'Built in scheduler server with laravel driver, no cron jobs or php artisan schedule:run needed.',
        icon: ILucideAlarmClockCheck,
    },
];
</script>

<template>
    <PublicLayout>
        <motion.div
            :initial="{ opacity: 0, y: 50 }"
            :animate="{
                opacity: 1,
                y: 0,
                transition: { duration: 0.5, ease: 'easeOut' },
            }"
        >
            <UPageHero
                title="Pogo extensions for FrankenPHP"
                description="Run php artisan octane:frankenphp and enjoy a fast application with built in websocket, queue and scheduler (and more to come)."
            >
                <template #links>
                    <motion.div
                        :initial="{ opacity: 0, y: 50 }"
                        :animate="{
                            opacity: 1,
                            y: 0,
                            transition: {
                                delay: 0.2,
                                duration: 0.5,
                                ease: 'easeOut',
                            },
                        }"
                        class="flex flex-wrap justify-center gap-4"
                    >
                        <UButton
                            :to="register().url"
                            size="xl"
                            label="Get Started - It's Free"
                            :icon="ILucideArrowRight"
                            trailing
                        />
                        <UButton
                            to="https://github.com/y-l-g/websocket"
                            target="_blank"
                            size="xl"
                            label="Star on GitHub"
                            color="neutral"
                            variant="ghost"
                            :icon="ILucideGithub"
                        />
                    </motion.div>
                </template>
            </UPageHero>
        </motion.div>

        <motion.div
            :initial="{ opacity: 0, y: 50 }"
            :whileInView="{
                opacity: 1,
                y: 0,
                transition: { duration: 0.7, ease: 'easeOut' },
            }"
            :inViewOptions="{ once: true }"
        >
            <UPageSection
                title="Built for speed and simplicity"
                description="Give super powers to your application with FrankenPHP and Pogo extensions."
                :ui="{ title: 'text-center', description: 'text-center' }"
            >
                <UPageGrid>
                    <motion.div
                        v-for="(pillar, index) in architecturalPillars"
                        :key="pillar.title"
                        :initial="{ opacity: 0, y: 50 }"
                        :whileInView="{
                            opacity: 1,
                            y: 0,
                            transition: {
                                delay: index * 0.15,
                                duration: 0.5,
                                ease: 'easeOut',
                            },
                        }"
                        :inViewOptions="{ once: true }"
                    >
                        <UPageCard
                            :title="pillar.title"
                            :description="pillar.description"
                            :icon="pillar.icon"
                            variant="ghost"
                        />
                    </motion.div>
                </UPageGrid>
            </UPageSection>
        </motion.div>

        <!-- <motion.div
            :initial="{ opacity: 0, y: 50 }"
            :whileInView="{
                opacity: 1,
                y: 0,
                transition: { duration: 0.7, ease: 'easeOut' },
            }"
            :inViewOptions="{ once: true }"
        >
            <UPageSection
                :ui="{
                    wrapper: 'py-24 sm:py-32',
                }"
            >
                <UPageCTA
                    title="Ready to Build Your Next Big Idea?"
                    description="Stop rebuilding the same features. Start with a solid foundation and focus on what makes your application unique."
                    card
                    variant="soft"
                >
                    <template #links>
                        <UButton
                            :to="register().url"
                            size="xl"
                            label="Start Building Now"
                        />
                    </template>
                </UPageCTA>
            </UPageSection>
        </motion.div> -->
    </PublicLayout>
</template>
