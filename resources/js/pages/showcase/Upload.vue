<script setup lang="ts">
import Upload from '@/components/showcase/upload.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { upload } from '@/routes/showcase';
import type { BreadcrumbItem } from '@nuxt/ui';

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

defineProps<{
    uploadAvailable: boolean;
    uploadStatus: UploadStatus;
    maxBytes: number;
    acceptedContentTypes: string[];
}>();

const breadcrumbs = [
    {
        label: 'Upload',
        to: upload().url,
    },
] satisfies BreadcrumbItem[];
</script>

<template>
    <AppLayout :breadcrumbs>
        <template #body>
            <Upload
                :upload-available="uploadAvailable"
                :upload-status="uploadStatus"
                :max-bytes="maxBytes"
                :accepted-content-types="acceptedContentTypes"
            />
        </template>
    </AppLayout>
</template>
