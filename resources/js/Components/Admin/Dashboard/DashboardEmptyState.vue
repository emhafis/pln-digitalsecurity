
<script setup>
defineProps({
    title: {
        type: String,
        default: 'Belum ada data',
    },
    description: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-lg border border-dashed border-[#DCE2DE] bg-[#F8F9F7] px-5 py-9 text-center">
        <span class="text-[11px] font-semibold tracking-[.12em] text-[#A2AAA6]">
            NO RECORDS
        </span>

        <p class="mt-2 text-sm font-medium text-[#45534F]">
            {{ title }}
        </p>

        <p
            v-if="description"
            class="mt-1 max-w-sm text-xs leading-relaxed text-[#89918F]"
        >
            {{ description }}
        </p>
    </div>
</template>
