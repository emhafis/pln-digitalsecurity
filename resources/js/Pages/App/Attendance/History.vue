<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { FunnelIcon, ClockIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppHeader from '@/Components/App/AppHeader.vue';

import BottomNavigation from '@/Components/App/Home/BottomNavigation.vue';

const props = defineProps({
    records: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Array, default: () => [] },
    has_security_profile: { type: Boolean, default: false },
    summary: { type: Object, default: () => ({}) },
});

const month = ref(props.filters?.month ?? new Date().getMonth() + 1);
const year = ref(props.filters?.year ?? new Date().getFullYear());
const status = ref(props.filters?.status ?? '');
const loading = ref(false);

const monthOptions = [
    { value: 1, label: 'Januari' }, { value: 2, label: 'Februari' }, { value: 3, label: 'Maret' },
    { value: 4, label: 'April' }, { value: 5, label: 'Mei' }, { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' }, { value: 8, label: 'Agustus' }, { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' }, { value: 11, label: 'November' }, { value: 12, label: 'Desember' },
];

const currentYear = new Date().getFullYear();
const yearOptions = computed(() => {
    const years = [];
    for (let y = currentYear - 2; y <= currentYear + 1; y++) years.push(y);
    return years;
});

function applyFilters() {
    loading.value = true;
    router.get('/app/attendance/history', {
        month: month.value,
        year: year.value,
        status: status.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => { loading.value = false; },
    });
}

function statusClasses(s) {
    switch (s) {
        case 'completed': return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'absent': return 'bg-red-50 text-[#E76B83] border-red-200';
        case 'in_progress': return 'bg-[#E8F0F6] text-[#1D567F] border-[#cfe0ee]';
        case 'pending': return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'day_off': return 'bg-gray-100 text-gray-600 border-gray-200';
        case 'scheduled': return 'bg-slate-50 text-slate-600 border-slate-200';
        default: return 'bg-gray-50 text-gray-600 border-gray-200';
    }
}

function correctionClasses(s) {
    switch (s) {
        case 'approved': return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'rejected': return 'bg-red-50 text-[#E76B83] border-red-200';
        case 'pending': return 'bg-amber-50 text-amber-700 border-amber-200';
        default: return 'bg-gray-50 text-gray-600 border-gray-200';
    }
}
</script>

<template>
    <AppLayout>
        <div class="relative w-full bg-white">
            <AppHeader title="Riwayat Kehadiran" backUrl="/app/attendance" />

            <main class="space-y-4 px-5 pb-28 pt-5">
                <section class="rounded-xl border border-[#E8E8EC] bg-white p-4">
                    <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-[#263746]">
                        <FunnelIcon class="h-4 w-4 text-[#1D567F]" />
                        <span>Filter</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs text-[#9299A2]">Bulan</label>
                            <select v-model="month" class="w-full rounded-lg border border-[#E8E8EC] px-3 py-2 text-sm text-[#263746] focus:border-[#38BDB5] focus:outline-none" @change="applyFilters">
                                <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-[#9299A2]">Tahun</label>
                            <select v-model="year" class="w-full rounded-lg border border-[#E8E8EC] px-3 py-2 text-sm text-[#263746] focus:border-[#38BDB5] focus:outline-none" @change="applyFilters">
                                <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <label class="mb-1 block text-xs text-[#9299A2]">Status</label>
                            <select v-model="status" class="w-full rounded-lg border border-[#E8E8EC] px-3 py-2 text-sm text-[#263746] focus:border-[#38BDB5] focus:outline-none" @change="applyFilters">
                                <option value="">Semua status</option>
                                <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                            </select>
                        </div>
                    </div>
                    <p v-if="loading" class="mt-2 text-xs text-[#38BDB5]">Memuat riwayat...</p>
                </section>

                <div class="flex items-center justify-between text-sm">
                    <p class="text-[#9299A2]">Total riwayat</p>
                    <p class="font-semibold text-[#263746]">{{ summary?.total ?? 0 }}</p>
                </div>

                <template v-if="!has_security_profile">
                    <div class="rounded-xl border border-[#E8E8EC] bg-gray-50 p-4 text-sm text-[#263746]">
                        Profil Satpam belum tersedia. Hubungi admin untuk melengkapi data.
                    </div>
                </template>

                <template v-else-if="records.length === 0">
                    <div class="rounded-xl border border-dashed border-[#E8E8EC] bg-gray-50 p-6 text-center">
                        <p class="text-sm font-medium text-[#263746]">Belum ada riwayat kehadiran</p>
                        <p class="mt-1 text-xs text-[#9299A2]">Tidak ada data untuk periode atau filter yang dipilih.</p>
                    </div>
                </template>

                <template v-else>
                    <section class="space-y-3">
                        <article
                            v-for="item in records"
                            :key="item.id"
                            class="rounded-xl border border-[#E8E8EC] bg-white p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-[#263746]">{{ item.work_date_label }}</p>
                                    <p class="mt-0.5 text-xs text-[#9299A2]">
                                        {{ item.shift_name || '-' }}
                                        <span v-if="item.shift_time"> - {{ item.shift_time }}</span>
                                    </p>
                                </div>
                                <span class="shrink-0 rounded-full border px-2.5 py-0.5 text-[11px] font-medium" :class="statusClasses(item.status)">
                                    {{ item.status_label }}
                                </span>
                            </div>

                            <div class="mt-2 text-xs text-[#263746]">
                                <p>{{ item.post_name || '-' }} <span v-if="item.unit_name" class="text-[#9299A2]">- {{ item.unit_name }}</span></p>
                            </div>

                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <div class="rounded-lg bg-gray-50 p-2.5">
                                    <div class="flex items-center gap-1.5 text-[11px] text-[#9299A2]">
                                        <ClockIcon class="h-3.5 w-3.5 text-[#38BDB5]" />
                                        <span>Masuk</span>
                                    </div>
                                    <p class="mt-0.5 text-sm font-semibold text-[#263746]">{{ item.check_in_label || '-' }}</p>
                                    <p v-if="item.is_late" class="mt-0.5 text-[11px] font-medium text-[#E76B83]">Terlambat</p>
                                </div>
                                <div class="rounded-lg bg-gray-50 p-2.5">
                                    <div class="flex items-center gap-1.5 text-[11px] text-[#9299A2]">
                                        <ClockIcon class="h-3.5 w-3.5 text-[#E76B83]" />
                                        <span>Keluar</span>
                                    </div>
                                    <p class="mt-0.5 text-sm font-semibold text-[#263746]">{{ item.check_out_label || '-' }}</p>
                                </div>
                            </div>

                            <div v-if="item.total_work_label" class="mt-2 flex items-center justify-between rounded-lg bg-gray-50 px-3 py-2">
                                <span class="text-[11px] text-[#9299A2]">Total durasi kerja</span>
                                <span class="text-xs font-semibold text-[#263746]">{{ item.total_work_label }}</span>
                            </div>

                            <div v-if="item.latest_correction_status_label || item.can_request_correction" class="mt-3 flex items-center justify-between gap-2 border-t border-[#E8E8EC] pt-3">
                                <div>
                                    <span v-if="item.latest_correction_status_label" class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-medium" :class="correctionClasses(item.latest_correction_status)">
                                        Koreksi: {{ item.latest_correction_status_label }}
                                    </span>
                                </div>
                                <div>
                                    <Link
                                        v-if="item.can_request_correction"
                                        :href="`/app/attendance/correction?record_id=${item.id}`"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-[#1D567F] px-3 py-1.5 text-xs font-medium text-[#1D567F]"
                                    >
                                        <PencilSquareIcon class="h-3.5 w-3.5" />
                                        Ajukan Koreksi
                                    </Link>
                                    <span v-else-if="item.has_pending_correction" class="text-[11px] text-[#9299A2]">Menunggu keputusan</span>
                                </div>
                            </div>
                        </article>
                    </section>

                </template>
            </main>
        </div>

        <template #bottom>
            <BottomNavigation active="attendance" />
        </template>
    </AppLayout>
</template>