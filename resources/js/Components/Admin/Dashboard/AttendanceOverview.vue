
<script setup>
import { computed } from 'vue';
import Badge from '@/Components/Admin/Badge.vue';
import DashboardSectionHeader from './DashboardSectionHeader.vue';
import DashboardEmptyState from './DashboardEmptyState.vue';

const props = defineProps({
    attendance: { type: Object, required: true },
});

const today = computed(() => props.attendance?.today || {});

const stats = computed(() => [
    { label: 'Total record', value: today.value.total ?? 0 },
    { label: 'Shift kerja', value: today.value.working ?? 0 },
    { label: 'Check-in', value: today.value.checked_in ?? 0 },
    { label: 'Belum check-in', value: today.value.not_checked_in ?? 0 },
    { label: 'Sedang bertugas', value: today.value.in_progress ?? 0 },
    { label: 'Selesai', value: today.value.completed ?? 0 },
    { label: 'Tidak hadir', value: today.value.absent ?? 0 },
    { label: 'Libur', value: today.value.day_off ?? 0 },
]);

function statusVariant(status) {
    if (status === 'completed') return 'emerald';
    if (status === 'in_progress') return 'blue';
    if (status === 'pending' || status === 'scheduled') return 'amber';
    if (status === 'absent') return 'red';
    return 'gray';
}
</script>

<template>
    <section class="min-w-0 rounded-lg border border-[#DCE2DE] bg-white p-4 sm:p-5">
        <DashboardSectionHeader
            title="Monitoring absensi"
            description="Kehadiran dan status kerja personel hari ini"
            :link-href="attendance.links?.index"
            link-label="Buka absensi"
        />

        <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-4 sm:grid-cols-4">
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="border-b border-[#EEF0EE] pb-3"
            >
                <p class="text-[11px] text-[#89918F]">
                    {{ stat.label }}
                </p>
                <p class="mt-1.5 text-2xl font-semibold tracking-tight text-[#34434A] tabular-nums">
                    {{ stat.value }}
                </p>
            </div>
        </div>

        <p class="mt-3 text-[11px] leading-relaxed text-[#89918F]">
            Data dihitung dari record absensi dan jadwal tersimpan.
            Ketidakhadiran tidak disimpulkan hanya dari ketiadaan record.
        </p>

        <div class="mt-6">
            <h3 class="text-xs font-semibold text-[#45534F]">
                Riwayat absensi terbaru
            </h3>

            <div v-if="!(attendance.recent || []).length" class="mt-3">
                <DashboardEmptyState
                    title="Belum ada aktivitas absensi"
                    description="Check-in dan check-out yang tercatat akan ditampilkan di sini."
                />
            </div>

            <div v-else class="mt-3 overflow-x-auto">
                <table class="min-w-[580px] w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-[#E3E7E5] text-[10px] uppercase tracking-wider text-[#89918F]">
                            <th class="py-3 pr-3 font-semibold">Personel</th>
                            <th class="py-3 pr-3 font-semibold">Lokasi</th>
                            <th class="py-3 pr-3 font-semibold">Masuk</th>
                            <th class="py-3 pr-3 font-semibold">Pulang</th>
                            <th class="py-3 text-right font-semibold">Status</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-[#EEF0EE]">
                        <tr
                            v-for="row in attendance.recent"
                            :key="row.id"
                            class="transition hover:bg-[#F8F9F7]"
                        >
                            <td class="py-3 pr-3">
                                <p class="font-medium text-[#34434A]">
                                    {{ row.security_name || '-' }}
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
                                {{ row.check_in || '-' }}
                            </td>

                            <td class="py-3 pr-3 tabular-nums text-[#596762]">
                                {{ row.check_out || '-' }}
                            </td>

                            <td class="py-3 text-right">
                                <div class="inline-flex flex-col items-end gap-1">
                                    <Badge :variant="statusVariant(row.status)">
                                        {{ row.status_label || row.status }}
                                    </Badge>

                                    <span
                                        v-if="row.is_late"
                                        class="text-[10px] font-medium text-[#B77932]"
                                    >
                                        Terlambat
                                    </span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>
