<script setup>
import { ArchiveBoxIcon } from '@heroicons/vue/24/outline';

defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
            <ArchiveBoxIcon class="h-6 w-6" />
        </span>
        <p class="mt-4 text-sm font-medium text-gray-900">{{ title }}</p>
        <p v-if="description" class="mt-1 max-w-sm text-xs text-gray-500">{{ description }}</p>
        <div v-if="$slots.action" class="mt-5">
            <slot name="action" />
        </div>
    </div>
</template>
