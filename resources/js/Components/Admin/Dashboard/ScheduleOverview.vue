<script setup>
import { computed } from 'vue';
import DashboardSectionHeader from './DashboardSectionHeader.vue';
import DashboardEmptyState from './DashboardEmptyState.vue';
import ShiftDistribution from './ShiftDistribution.vue';

const props = defineProps({
    schedule: { type: Object, required: true },
});

const today = computed(() => props.schedule?.today || { total: 0, by_shift: [], items: [] });
</script>

<template>
    <section class="rounded-lg border border-[#DCE2DE] bg-white p-4 sm:p-5">
        <DashboardSectionHeader
            title="Jadwal Satpam Hari Ini"
            :description="`Jadwal tersimpan untuk ${today.date_label || 'hari ini'}`"
            :link-href="schedule.links?.calendar"
            link-label="Kalender jadwal"
        />

        <div class="mt-4 flex items-center gap-4 rounded-lg rounded-lg border border-[#DCE2DE] bg-[#F8F9F7] px-4 py-3">
            <div>
                <p class="text-xs font-medium uppercase tracking-wide text-[#89918F]">Personel Dijadwalkan</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-[#34434A]">{{ today.total ?? 0 }}</p>
            </div>
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-2">
            <ShiftDistribution :shifts="today.by_shift || []" />

            <div>
                <h3 class="text-sm font-medium text-[#45534F]">Daftar jadwal</h3>
                <div v-if="(today.items || []).length === 0" class="mt-3">
                    <DashboardEmptyState title="Belum ada jadwal hari ini" description="Jadwal yang tersimpan untuk hari ini akan ditampilkan di sini." />
                </div>
                <div v-else class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-[#E3E7E5] text-xs uppercase tracking-wide text-[#89918F]">
                                <th class="py-2 pr-3 font-semibold">Satpam</th>
                                <th class="py-2 pr-3 font-semibold">Regu</th>
                                <th class="py-2 pr-3 font-semibold">Shift</th>
                                <th class="py-2 font-semibold">Pos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#EEF0EE]">
                            <tr v-for="row in today.items" :key="row.id">
                                <td class="py-2.5 pr-3">
                                    <p class="font-medium text-[#34434A]">{{ row.security_name || '-' }}</p>
                                    <p v-if="row.registration_number" class="text-xs text-[#89918F]">{{ row.registration_number }}</p>
                                </td>
                                <td class="py-2.5 pr-3 text-[#596762]">{{ row.team_name || '-' }}</td>
                                <td class="py-2.5 pr-3 text-[#596762]">
                                    <p>{{ row.shift_name || '-' }}</p>
                                    <p v-if="row.shift_time" class="text-xs text-[#89918F]">{{ row.shift_time }}</p>
                                </td>
                                <td class="py-2.5 text-[#596762]">
                                    <p>{{ row.post_name || '-' }}</p>
                                    <p v-if="row.unit_name" class="text-xs text-[#89918F]">{{ row.unit_name }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="(today.total || 0) > (today.items || []).length" class="mt-2 text-xs text-[#89918F]">
                        Menampilkan {{ today.items.length }} dari {{ today.total }} jadwal.
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>

