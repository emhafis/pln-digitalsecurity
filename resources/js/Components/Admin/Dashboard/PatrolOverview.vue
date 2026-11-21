
<script setup>
import { computed } from 'vue';
import Badge from '@/Components/Admin/Badge.vue';
import DashboardSectionHeader from './DashboardSectionHeader.vue';
import DashboardEmptyState from './DashboardEmptyState.vue';

const props = defineProps({
    patrol: { type: Object, required: true },
});

const today = computed(() => props.patrol?.today || {});

const statuses = computed(() => [
    {
        label: 'Selesai',
        value: today.value.completed ?? 0,
        color: 'text-[#16866B]',
    },
    {
        label: 'Aktif',
        value: today.value.active ?? 0,
        color: 'text-[#386B70]',
    },
    {
        label: 'Menunggu',
        value: today.value.pending ?? 0,
        color: 'text-[#B77932]',
    },
    {
        label: 'Bermasalah',
        value: today.value.issues ?? 0,
        color: 'text-[#B94D4D]',
    },
]);

const completion = computed(() => {
    const total = Number(today.value.total ?? 0);
    const completed = Number(today.value.completed ?? 0);

    return total > 0
        ? Math.min(100, Math.round((completed / total) * 100))
        : 0;
});

function statusVariant(status) {
    if (status === 'completed') return 'emerald';
    if (status === 'active') return 'blue';
    if (status === 'pending') return 'amber';
    if (status === 'incomplete' || status === 'not_started') return 'red';
    return 'gray';
}
</script>

<template>
    <section class="min-w-0 rounded-lg border border-[#DCE2DE] bg-white p-4 sm:p-5">
        <DashboardSectionHeader
            title="Monitoring patroli"
            description="Rekap pelaksanaan patroli hari ini"
            :link-href="patrol.links?.index"
            link-label="Buka monitoring"
        />

        <div class="mt-6 flex items-end justify-between gap-4">
            <div>
                <p class="text-xs text-[#89918F]">Total sesi patroli</p>
                <p class="mt-1 text-[36px] font-semibold leading-none tracking-[-.06em] text-[#202B35] tabular-nums">
                    {{ today.total ?? 0 }}
                </p>
            </div>

            <div class="text-right">
                <p class="text-[11px] text-[#89918F]">Penyelesaian</p>
                <p class="mt-1 text-xl font-semibold text-[#16866B] tabular-nums">
                    {{ completion }}%
                </p>
            </div>
        </div>

        <div class="mt-4 h-[5px] overflow-hidden bg-[#EDF0ED]">
            <div
                class="h-full bg-[#16866B] transition-all duration-500"
                :style="{ width: `${completion}%` }"
            />
        </div>

        <div class="mt-5 grid grid-cols-2 gap-y-4 border-y border-[#EEF0EE] py-4 sm:grid-cols-4">
            <div
                v-for="status in statuses"
                :key="status.label"
                class="border-l border-[#E3E7E5] pl-3 first:border-0 first:pl-0"
            >
                <p class="text-[11px] text-[#89918F]">
                    {{ status.label }}
                </p>
                <p
                    class="mt-1 text-xl font-semibold tabular-nums"
                    :class="status.color"
                >
                    {{ status.value }}
                </p>
            </div>
        </div>

        <div class="mt-5">
            <h3 class="text-xs font-semibold text-[#45534F]">
                Aktivitas patroli terbaru
            </h3>

            <div v-if="!(patrol.recent || []).length" class="mt-3">
                <DashboardEmptyState
                    title="Belum ada sesi patroli"
                    description="Data patroli yang tercatat akan muncul di sini."
                />
            </div>

            <div v-else class="mt-3 overflow-x-auto">
                <table class="min-w-[580px] w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-[#E3E7E5] text-[10px] uppercase tracking-wider text-[#89918F]">
                            <th class="py-3 pr-3 font-semibold">Pelaksana</th>
                            <th class="py-3 pr-3 font-semibold">Pos / Unit</th>
                            <th class="py-3 pr-3 font-semibold">Mulai</th>
                            <th class="py-3 pr-3 font-semibold">Selesai</th>
                            <th class="py-3 text-right font-semibold">Status</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-[#EEF0EE]">
                        <tr
                            v-for="row in patrol.recent"
                            :key="row.id"
                            class="transition hover:bg-[#F8F9F7]"
                        >
                            <td class="py-3 pr-3">
                                <p class="font-medium text-[#34434A]">
                                    {{ row.actor || '-' }}
                                </p>
                                <p v-if="row.shift_name" class="mt-1 text-[11px] text-[#89918F]">
                                    {{ row.shift_name }}
                                </p>
                            </td>

                            <td class="py-3 pr-3 text-[#596762]">
                                <p>{{ row.post_name || '-' }}</p>
                                <p v-if="row.unit_name" class="mt-1 text-[11px] text-[#89918F]">
                                    {{ row.unit_name }}
                                </p>
                            </td>

                            <td class="py-3 pr-3 tabular-nums text-[#596762]">
                                {{ row.started_at || '-' }}
                            </td>

                            <td class="py-3 pr-3 tabular-nums text-[#596762]">
                                {{ row.ended_at || '-' }}
                            </td>

                            <td class="py-3 text-right">
                                <Badge :variant="statusVariant(row.status)">
                                    {{ row.status_label || row.status }}
                                </Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>
