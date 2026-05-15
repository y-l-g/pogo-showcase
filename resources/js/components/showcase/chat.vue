<script setup lang="ts">
import { useAuthPage } from '@/composables/useAuthPage';
import { message } from '@/routes/showcase/chat';
import { useForm } from '@inertiajs/vue3';
import { useEcho, useEchoPresence, useEchoPublic } from '@laravel/echo-vue';
import { computed, ref, watch } from 'vue';

const currentUser = computed(() => useAuthPage().props.user);

const messages = ref<any[]>([]);
const onlineUsers = ref<any[]>([]);
const typingUsers = ref<Record<number, string>>({});
const activeTab = ref('0');

const tabs = [
    { label: 'Public', key: 'public' },
    { label: 'Private', key: 'private' },
    { label: 'Presence', key: 'presence' },
];
const currentChannel = computed(() => tabs[Number(activeTab.value)].key);

const form = useForm({
    content: '',
    type: currentChannel.value,
});

watch(currentChannel, (newChannel) => {
    form.type = newChannel;
});

const filteredMessages = computed(() =>
    messages.value.filter((m) => m.channel === currentChannel.value),
);
const typingLabel = computed(() => {
    const names = Object.values(typingUsers.value);
    return names.length && currentChannel.value === 'presence'
        ? `${names.join(', ')} typing...`
        : undefined;
});

const addMsg = (e: any, channel: string) =>
    messages.value.push({ ...e, channel });

useEchoPublic('chat.public', '.message.sent', (e) => addMsg(e, 'public'));
useEcho('chat.private', '.message.sent', (e) => addMsg(e, 'private'));
const { channel: presence } = useEchoPresence(
    'chat.presence',
    '.message.sent',
    (e) => addMsg(e, 'presence'),
);

const ch = presence() as any;
if (ch) {
    ch.here((u: any[]) => (onlineUsers.value = u))
        .joining((u: any) => onlineUsers.value.push(u))
        .leaving((u: any) => {
            onlineUsers.value = onlineUsers.value.filter((i) => i.id !== u.id);
            delete typingUsers.value[u.id];
        })
        .listenForWhisper('typing', (e: any) => {
            typingUsers.value[e.id] = e.name;
            setTimeout(() => delete typingUsers.value[e.id], 2000);
        });
}

const send = () => {
    form.type = currentChannel.value;
    form.submit(message(), {
        onSuccess: () => {
            form.reset('content');
            form.type = currentChannel.value;
        },
    });
};
</script>

<template>
    <UCard
        :ui="{
            body: 'h-[calc(100vh-300px)]',
        }"
        variant="soft"
    >
        <template #header>
            <div class="flex gap-4">
                <UTabs v-model="activeTab" :items="tabs" class="flex-1" />
                <UPopover mode="hover" :popper="{ placement: 'bottom-end' }">
                    <UButton
                        color="gray"
                        icon="i-heroicons-users"
                        :label="`${onlineUsers.length}`"
                    />
                    <template #content>
                        <div
                            class="max-h-40 w-40 space-y-1 overflow-auto p-2 text-sm"
                        >
                            <p
                                v-for="u in onlineUsers"
                                :key="u.id"
                                class="truncate"
                            >
                                <span class="text-green-500">●</span>
                                {{ u.name }}
                            </p>
                        </div>
                    </template>
                </UPopover>
            </div>
        </template>
        <div
            v-if="!filteredMessages.length"
            class="m-auto text-sm text-gray-400"
        >
            No message
        </div>

        <div
            v-for="msg in filteredMessages"
            :key="msg.id"
            class="my-5 flex flex-col space-y-2"
            :class="
                msg.user.id === currentUser.id
                    ? 'items-end self-end'
                    : 'items-start self-start'
            "
        >
            <span class="px-1 text-xs text-muted">{{ msg.user.name }}</span>
            <UBadge
                :color="
                    msg.user.id === currentUser.id ? 'primary' : 'secondary'
                "
                :label="msg.content"
                class="text-left whitespace-normal"
            />
        </div>

        <template #footer>
            <UFormField
                :help="typingLabel"
                :ui="{ help: 'text-primary animate-pulse absolute -top-6' }"
            >
                <UInput
                    v-model="form.content"
                    placeholder="Type a message..."
                    autofocus
                    class="w-full"
                    :loading="form.processing"
                    @keydown.enter="send"
                    @input="
                        currentChannel === 'presence' &&
                        presence().whisper('typing', {
                            id: currentUser.id,
                            name: currentUser.name,
                        })
                    "
                >
                    <template #trailing>
                        <UButton
                            color="gray"
                            variant="link"
                            icon="i-heroicons-paper-airplane"
                            :padded="false"
                            @click="send"
                        />
                    </template>
                </UInput>
            </UFormField>
        </template>
    </UCard>
</template>
