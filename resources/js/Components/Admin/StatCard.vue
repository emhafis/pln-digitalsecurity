<script setup>
import { ArrowTrendingDownIcon, ArrowTrendingUpIcon } from '@heroicons/vue/24/outline';

defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: [String, Number],
        required: true,
    },
    hint: {
        type: String,
        default: '',
    },
    trend: {
        type: String,
        default: '',
    },
    trendUp: {
        type: Boolean,
        default: true,
    },
    icon: {
        type: Function,
        default: null,
    },
});
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-500">{{ label }}</p>
                <p class="mt-2 text-2xl font-semibold tracking-tight text-gray-900">
                    {{ value }}
                </p>
            </div>
            <span
                v-if="icon"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600"
            >
                <component :is="icon" class="h-5 w-5" />
            </span>
        </div>
        <div class="mt-3 flex items-center gap-2">
            <span
                v-if="trend"
                class="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-medium"
                :class="trendUp ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
            >
                <ArrowTrendingUpIcon v-if="trendUp" class="h-3 w-3" />
                <ArrowTrendingDownIcon v-else class="h-3 w-3" />
                {{ trend }}
            </span>
            <span v-if="hint" class="text-xs text-gray-400">{{ hint }}</span>
        </div>
    </div>
</template>
