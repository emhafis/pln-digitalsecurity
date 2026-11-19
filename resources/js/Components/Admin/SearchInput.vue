<script setup>
import { MagnifyingGlassIcon } from '@heroicons/vue/24/outline';

defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Cari...',
    },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="relative w-full sm:w-64">
        <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input
            type="search"
            :value="modelValue"
            :placeholder="placeholder"
            class="w-full rounded-md border border-gray-300 bg-white py-2 pl-9 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
            @input="$emit('update:modelValue', $event.target.value)"
        >
    </div>
</template>
