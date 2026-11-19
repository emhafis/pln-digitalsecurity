<script setup>
defineProps({
    label: { type: String, default: '' },
    for: { type: String, default: '' },
    required: { type: Boolean, default: false },
    hint: { type: String, default: '' },
});
</script>

<template>
    <div>
        <label v-if="label" :for="for" class="block text-sm pb-2 text-gray-700">
            {{ label }}
            <span v-if="required" class="text-red-500">*</span>
        </label>
        <slot />
        <p v-if="hint" class="mt-1 text-xs text-gray-500">{{ hint }}</p>
        <slot name="error" />
    </div>
</template>
