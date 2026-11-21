
<script setup>
import { computed } from 'vue';
import DashboardEmptyState from './DashboardEmptyState.vue';

const props = defineProps({
    shifts: { type: Array, default: () => [] },
    title: { type: String, default: 'Distribusi shift' },
});

const maxCount = computed(() => Math.max(
    1,
    ...(props.shifts || []).map((s) => Number(s.count) || 0)
));
</script>

<template>
    <div>
        <h3 class="text-xs font-semibold text-[#45534F]">
            {{ title }}
        </h3>

        <div v-if="!shifts.length" class="mt-3">
            <DashboardEmptyState
                title="Belum ada data shift"
                description="Distribusi akan muncul setelah jadwal tersedia."
            />
        </div>

        <div v-else class="mt-4 space-y-4">
            <div
                v-for="shift in shifts"
                :key="(shift.code || '') + (shift.name || '')"
            >
                <div class="flex items-center justify-between gap-3 text-xs">
                    <div class="min-w-0">
                        <span class="font-medium text-[#45534F]">
                            {{ shift.name || '-' }}
                        </span>

                        <span
                            v-if="shift.code && shift.code !== '-'"
                            class="ml-1 text-[#A0A8A5]"
                        >
                            {{ shift.code }}
                        </span>
                    </div>

                    <span class="font-semibold tabular-nums text-[#34434A]">
                        {{ shift.count ?? 0 }}
                    </span>
                </div>

                <div class="mt-2 h-[5px] overflow-hidden bg-[#EDF0ED]">
                    <div
                        class="h-full bg-[#386B70] transition-all duration-500"
                        :style="{
                            width: `${Math.min(100, Math.round(((Number(shift.count) || 0) / maxCount) * 100))}%`
                        }"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
