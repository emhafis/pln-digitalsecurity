
<script setup>
defineProps({
    modelValue: {
        type: [String, Number],
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    error: {
        type: [String, Array],
        default: '',
    },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="w-full">
        <input
            :value="modelValue"
            @input="$emit('update:modelValue', $event.target.value)"
            :disabled="disabled"
            v-bind="$attrs"
            :class="[
                'py-2 px-3 text-sm border rounded-md w-full',
                'focus:outline-none focus:ring-1',
                error
                    ? 'border-red-500 focus:border-red-500 focus:ring-red-500'
                    : 'border-gray-300 focus:border-[#155e75] focus:ring-[#155e75]'
            ]"
        />
    </div>
</template>
