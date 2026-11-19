
<script setup>
defineProps({
    value: {
        type: String,
        default: '',
    },
    required: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <label class="block text-sm text-slate-700">
        {{ value }}
        <span v-if="required" class="text-red-500">*</span>
    </label>
</template>
