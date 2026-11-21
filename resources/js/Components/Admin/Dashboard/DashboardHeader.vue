
<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    filters: { type: Object, required: true },
    context: { type: Object, required: true },
});

const periods = [
    { value: 'today', label: 'Hari ini' },
    { value: '7d', label: '7 hari' },
    { value: '30d', label: '30 hari' },
];

function setPeriod(value) {
    if (value === props.filters.period) return;

    router.get('/admin/dashboard', {
        period: value,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
</script>

<template>
    <header class="flex flex-col gap-5 border-b border-[#DCE2DE] pb-6 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-[10px] font-bold tracking-[.16em] text-[#16866B]">
                SECURITY OPERATIONS
            </p>

            <h1 class="mt-2 text-[27px] font-semibold tracking-[-.055em] text-[#202B35] sm:text-[32px]">
                Dashboard
            </h1>

            <p class="mt-1 text-sm text-[#78827F]">
                Gambaran aktivitas dan kondisi operasional keamanan.
            </p>

            <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs text-[#78827F]">
                <span>
                    <span class="text-[#A0A8A5]">Tanggal</span>
                    <span class="ml-2 font-medium text-[#34434A]">
                        {{ context.today }}
                    </span>
                </span>

                <span>
                    <span class="text-[#A0A8A5]">Cakupan</span>
                    <span class="ml-2 font-medium text-[#34434A]">
                        {{ context.unit_scope_label }}
                    </span>
                </span>
            </div>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <span class="text-xs text-[#89918F]">
                Periode data
            </span>

            <div class="inline-flex w-fit items-center gap-1 rounded-lg bg-[#EAEDEA] p-1">
                <button
                    v-for="period in periods"
                    :key="period.value"
                    type="button"
                    class="rounded-md px-3.5 py-2 text-xs font-medium transition-colors duration-150"
                    :class="filters.period === period.value
                        ? 'bg-white text-[#244B59] shadow-sm'
                        : 'text-[#78827F] hover:text-[#202B35]'"
                    :aria-pressed="filters.period === period.value"
                    @click="setPeriod(period.value)"
                >
                    {{ period.label }}
                </button>
            </div>
        </div>
    </header>
</template>
