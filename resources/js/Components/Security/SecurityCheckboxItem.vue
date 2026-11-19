<script setup>
defineProps({
    checked: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    label: { type: String, required: true },
    unitName: { type: String, default: '' },
    disabledReason: { type: String, default: '' },
});

defineEmits(['update:checked']);
</script>

<template>
    <label
        class="flex items-start gap-3 rounded-md border border-gray-200 px-3 py-2 transition"
        :class="disabled ? 'cursor-not-allowed bg-gray-50 opacity-60' : 'cursor-pointer hover:bg-gray-50'"
    >
        <input
            type="checkbox"
            class="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            :checked="checked"
            :disabled="disabled"
            @change="$emit('update:checked', $event.target.checked)"
        />
        <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-gray-800">{{ label }}</p>
            <p v-if="unitName" class="text-xs text-gray-500">{{ unitName }}</p>
            <p v-if="disabled && disabledReason" class="text-xs text-red-600">{{ disabledReason }}</p>
        </div>
    </label>
</template>
