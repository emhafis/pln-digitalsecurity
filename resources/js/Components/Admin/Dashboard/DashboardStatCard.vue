<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: '' },
    icon: { type: Function, default: null },
    tone: { type: String, default: 'neutral' },
});

const tones = {
    navy: 'bg-[#244B59]/10 text-[#244B59]',
    green: 'bg-[#16866B]/10 text-[#16866B]',
    neutral: 'bg-[#F0F2F1] text-[#596762]',
};
</script>

<template>
    <div class="rounded-lg border border-[#DCE2DE] bg-white p-4 sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[11px] font-medium uppercase tracking-[.08em] text-[#89918F]">{{ label }}</p>
                <p class="mt-2 text-2xl font-semibold tracking-tight text-[#202B35] tabular-nums">{{ value }}</p>
                <p v-if="hint" class="mt-1 text-xs text-[#78827F]">{{ hint }}</p>
            </div>
            <span
                v-if="icon"
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                :class="tones[tone] || tones.neutral"
            >
                <component :is="icon" class="h-5 w-5" />
            </span>
        </div>
    </div>
</template>
