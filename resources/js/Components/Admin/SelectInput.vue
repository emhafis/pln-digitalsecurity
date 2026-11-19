<script setup>
const model = defineModel({ default: '' });

defineProps({
    id: { type: String, default: '' },
    options: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
});
</script>

<template>
    <select
        :id="id"
        v-model="model"
        :disabled="disabled"
        class="block w-full rounded-md border border-gray-300 bg-white px-3.5 py-1.5 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 disabled:bg-gray-50 disabled:text-gray-500"
    >
        <option v-for="option in options" :key="option.value" :value="option.value">
            {{ option.label }}
        </option>
    </select>
</template>
