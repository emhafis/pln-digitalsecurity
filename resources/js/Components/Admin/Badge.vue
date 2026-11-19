<script setup>
const props = defineProps({
    variant: {
        type: String,
        default: 'gray',
        validator: (v) => ['gray', 'blue', 'emerald', 'amber', 'red'].includes(v),
    },
});

const classes = {
    gray: 'bg-gray-100 text-gray-600',
    blue: 'bg-blue-50 text-blue-700',
    emerald: 'bg-emerald-50 text-emerald-700',
    amber: 'bg-amber-50 text-amber-700',
    red: 'bg-red-50 text-red-700',
};
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium"
        :class="classes[props.variant]"
    >
        <slot />
    </span>
</template>
