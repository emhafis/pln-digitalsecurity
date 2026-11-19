<script setup>
defineProps({
    modelValue: {
        type: [String, Number, Boolean],
        default: '',
    },
    options: {
        type: Array,
        default: () => [],
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div>
        <select
            :value="modelValue"
            :disabled="disabled"
            :class="['py-2 px-3 text-sm border-gray-300 rounded-md border w-full']"
            @change="$emit('update:modelValue', $event.target.value)"
        >
            <template v-if="options.length">
                <option
                    v-for="option in options"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </template>
            <slot v-else />
        </select>
    </div>
</template>
