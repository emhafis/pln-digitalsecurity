<script setup>
const model = defineModel({ type: Boolean, default: false });

defineProps({
    id: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});
</script>

<template>
    <label :for="id" class="flex cursor-pointer items-center gap-2 text-sm text-gray-700">
        <input
            :id="id"
            v-model="model"
            type="checkbox"
            :disabled="disabled"
            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
        />
        <slot />
    </label>
</template>
