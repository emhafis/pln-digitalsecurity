<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronLeftIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    backUrl: { type: String, default: '/app' },
    backLabel: { type: String, default: 'Beranda' },
});
</script>

<template>
    <header class="bg-[#1D567F] px-5 pb-5 pt-5 text-white">
        <Link
            :href="backUrl"
            class="mb-5 inline-flex items-center gap-1.5 text-xs text-white/75 transition-colors hover:text-white"
        >
            <ChevronLeftIcon class="h-4 w-4" />
            {{ backLabel }}
        </Link>

        <h1 class="text-xl font-semibold tracking-tight">
            {{ title }}
        </h1>
        <p v-if="description" class="mt-1 text-sm text-white/70">
            {{ description }}
        </p>

        <slot />
    </header>
</template>
