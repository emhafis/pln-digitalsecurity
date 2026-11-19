<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const appName = computed(() => page.props.appName);
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-gradient-to-br from-sky-50 via-white to-gray-50 px-4 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-sky-500 text-lg font-bold text-white">
                    {{ appName?.charAt(0)?.toUpperCase() }}
                </span>
                <h1 class="mt-4 text-2xl font-semibold tracking-tight text-gray-900">
                    {{ appName }}
                </h1>
                <p class="mt-1 text-sm text-gray-500">
                    Masuk untuk melanjutkan
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">
                <slot />
            </div>

            <p class="mt-6 text-center text-xs text-gray-400">
                &copy; {{ new Date().getFullYear() }} {{ appName }}. All rights reserved.
            </p>
        </div>
    </div>
</template>
