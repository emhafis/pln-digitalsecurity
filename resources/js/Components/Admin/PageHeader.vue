<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        default: '',
    },
    breadcrumb: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold tracking-tight text-gray-900">
                {{ title }}
            </h1>
            <nav
                v-if="breadcrumb.length"
                aria-label="Breadcrumb"
                class="mt-1 flex flex-wrap items-center gap-1.5 text-sm text-gray-500"
            >
                <template v-for="(crumb, index) in breadcrumb" :key="crumb.label">
                    <span v-if="index > 0" aria-hidden="true" class="text-gray-300">/</span>
                    <Link
                        v-if="crumb.href"
                        :href="crumb.href"
                        class="transition hover:text-blue-600"
                    >
                        {{ crumb.label }}
                    </Link>
                    <span v-else aria-current="page" class="text-blue-600">
                        {{ crumb.label }}
                    </span>
                </template>
            </nav>
            <p v-if="description" class="mt-1 text-sm text-gray-500">
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.actions" class="flex shrink-0 items-center gap-1.5">
            <slot name="actions" />
        </div>
    </div>
</template>
