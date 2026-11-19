<script setup>
defineProps({
    title: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <section class="rounded-xl border border-gray-200 bg-white">
        <div v-if="title || $slots.header" class="flex items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
            <div class="min-w-0">
                <h2 v-if="title" class="text-sm font-semibold text-gray-900">{{ title }}</h2>
                <p v-if="description" class="mt-0.5 text-xs text-gray-500">{{ description }}</p>
            </div>
            <slot name="header" />
        </div>
        <div class="p-5">
            <slot />
        </div>
    </section>
</template>
