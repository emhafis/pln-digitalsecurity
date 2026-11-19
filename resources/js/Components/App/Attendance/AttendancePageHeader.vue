<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronLeftIcon } from '@heroicons/vue/24/outline';

defineProps({
    title: { type: String, required: true },
    backHref: { type: String, default: '/app' },
});
</script>

<template>
    <header class="flex h-[68px] items-center bg-[#1D567F] px-4">
        <Link :href="backHref" class="flex h-9 w-9 items-center justify-center rounded-full text-white" aria-label="Kembali">
            <ChevronLeftIcon class="h-6 w-6" />
        </Link>
        <h1 class="flex-1 pr-9 text-center text-base font-semibold text-[#7EDDD7]">{{ title }}</h1>
    </header>
</template>
