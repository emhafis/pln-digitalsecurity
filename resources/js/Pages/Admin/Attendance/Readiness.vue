<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Badge from '@/Components/Admin/Badge.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import InputError from '@/Components/Admin/InputError.vue';

const props = defineProps({
    report: { type: Object, required: true },
    anomalies: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    unit_id: { type: [String, Number], default: '' },
    period_id: { type: [String, Number], default: '' },
});

const filters = ref({
    unit_id: props.unit_id,
    period_id: props.period_id,
});

const form = useForm({
    period_id: props.period_id === '' ? null : props.period_id,
    unit_id: props.unit_id === '' ? null : props.unit_id,
});

const globalReady = computed(() => props.report.global.ready);
const summary = computed(() => props.report.summary);
const periods = computed(() => props.report.periods ?? []);
const globalSettings = computed(() => props.report.global.settings);

const settingLabels = {
    gps_radius_meters: 'Radius Lokasi (meter)',
    check_in_open_minutes: 'Check-in Dibuka (menit sebelum shift)',
    late_tolerance_minutes: 'Toleransi Keterlambatan (menit)',
    early_checkout_tolerance_minutes: 'Toleransi Check-out Awal (menit)',
    checkout_close_minutes: 'Check-out Ditutup (menit setelah shift)',
    max_gps_accuracy_meters: 'Akurasi Lokasi Maksimal (meter)',
};

function applyFilter() {
    router.get('/admin/attendance/readiness', {
        unit_id: filters.value.unit_id || undefined,
        period_id: filters.value.period_id || undefined,
    }, { preserveState: true, preserveScroll: true });
}

function generate() {
    form.period_id = filters.value.period_id === '' ? null : filters.value.period_id;
    form.unit_id = filters.value.unit_id === '' ? null : filters.value.unit_id;
    form.post('/admin/attendance/generate', { preserveScroll: true });
}

function statusVariant(period) {
    if (period.blocked_by_global) {
        return 'red';
    }

    return period.ready ? 'emerald' : 'amber';
}

function statusLabel(period) {
    if (period.blocked_by_global) {
        return 'Diblokir Pengaturan Global';
    }

    return period.ready ? 'Siap' : 'Belum Siap';
}
</script>

<template>
    <AdminLayout title="Kesiapan Absensi">
        <div class="space-y-6">
            <PageHeader
                title="Kesiapan Absensi"
                description="Periksa kesiapan data sebelum membuat data absensi untuk periode jadwal."
                :breadcrumb="[{ label: 'Absensi' }, { label: 'Kesiapan Absensi' }]"
            >
                <template #actions>
                    <Link
                        href="/admin/attendance-record"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50"
                    >
                        Data Absensi
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                    <p class="text-xs text-gray-500">Status Keseluruhan</p>
                    <p class="mt-1 text-lg font-semibold" :class="globalReady ? 'text-emerald-700' : 'text-red-700'">
                        {{ globalReady ? 'Siap' : 'Belum Siap' }}
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                    <p class="text-xs text-gray-500">Periode Diperiksa</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">{{ summary.period_count }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                    <p class="text-xs text-gray-500">Jadwal Siap</p>
                    <p class="mt-1 text-lg font-semibold text-gray-900">
                        {{ summary.ready_schedule_count }} / {{ summary.schedule_count }}
                    </p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white px-4 py-3">
                    <p class="text-xs text-gray-500">Data Bermasalah Aktif</p>
                    <p class="mt-1 text-lg font-semibold" :class="anomalies.length ? 'text-amber-700' : 'text-gray-900'">
                        {{ anomalies.length }}
                    </p>
                </div>
            </div>

            <Card v-if="!globalReady" title="Pengaturan Absensi Belum Valid">
                <ul class="list-disc space-y-1 pl-5 text-sm text-red-700">
                    <li v-for="(issue, index) in report.global.issues" :key="index">
                        {{ issue.message }}
                    </li>
                </ul>
            </Card>

            <Card v-else title="Pengaturan Absensi">
                <dl v-if="globalSettings" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div v-for="(value, key) in globalSettings" :key="key">
                        <dt class="text-xs text-gray-500">{{ settingLabels[key] ?? key }}</dt>
                        <dd class="mt-0.5 text-sm font-medium text-gray-900">{{ value }}</dd>
                    </div>
                </dl>
            </Card>

            <Card title="Filter">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label class="block">
                        <span class="text-xs font-medium text-gray-600">Unit</span>
                        <select v-model="filters.unit_id" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                            <option value="">Semua Unit</option>
                            <option v-for="unit in units" :key="unit.value" :value="unit.value">
                                {{ unit.label }}
                            </option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="text-xs font-medium text-gray-600">ID Periode</span>
                        <input
                            v-model="filters.period_id"
                            type="number"
                            min="1"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm"
                            placeholder="Semua periode"
                        />
                    </label>
                    <div class="flex items-end gap-2">
                        <Button variant="secondary" @click="applyFilter">Terapkan</Button>
                    </div>
                </div>

                <InputError :messages="form.errors.period_id || form.errors.unit_id" />

                <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4">
                    <Button :disabled="form.processing || !globalReady" @click="generate">
                        {{ form.processing ? 'Memproses...' : 'Buat Data Absensi' }}
                    </Button>
                    <p class="text-xs text-gray-500">
                        Pembuatan data absensi bersifat idempoten dan tidak mengubah snapshot yang sudah terkunci.
                    </p>
                </div>
            </Card>

            <Card title="Periode Jadwal" description="Periode dengan masalah tidak akan dibuatkan data absensi.">
                <div class="overflow-hidden rounded-md border border-gray-200">
                    <table class="w-full min-w-[860px] text-left text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Periode</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Unit / Pos / Regu</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Rentang</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Jadwal</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="period in periods" :key="period.period_id">
                                <tr class="border-t border-gray-100 odd:bg-white even:bg-gray-50/60">
                                    <td class="px-4 py-2.5">
                                        <Link :href="`/admin/schedule-period/${period.period_id}`" class="text-blue-600 hover:underline">
                                            {{ period.name }}
                                        </Link>
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        <p>{{ period.unit_name }}</p>
                                        <p class="text-xs text-gray-400">{{ period.post_name }} - {{ period.team_name }}</p>
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        {{ period.start_date_label ?? '-' }} s/d {{ period.end_date_label ?? '-' }}
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600">
                                        {{ period.ready_schedule_count }} / {{ period.schedule_count }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <Badge :variant="statusVariant(period)">{{ statusLabel(period) }}</Badge>
                                    </td>
                                </tr>
                                <tr v-if="period.issues.length" class="border-t border-gray-100 bg-red-50/40">
                                    <td colspan="5" class="px-4 py-2.5">
                                        <ul class="list-disc space-y-1 pl-5 text-xs text-red-700">
                                            <li v-for="(issue, index) in period.issues" :key="index">{{ issue.message }}</li>
                                        </ul>
                                    </td>
                                </tr>
                            </template>
                            <tr v-if="periods.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Tidak ada periode jadwal pada filter ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>

            <Card title="Data Bermasalah" description="Masalah yang menyebabkan data absensi belum dapat dibuat.">
                <div class="overflow-hidden rounded-md border border-gray-200">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Jenis</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Periode</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Satpam</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Tanggal</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="anomaly in anomalies" :key="anomaly.id" class="border-t border-gray-100 odd:bg-white even:bg-gray-50/60">
                                <td class="px-4 py-2.5 font-medium text-gray-800">{{ anomaly.type_label }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ anomaly.period_name }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ anomaly.security_name }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ anomaly.work_date_label ?? '-' }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ anomaly.details }}</td>
                            </tr>
                            <tr v-if="anomalies.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Tidak ada data bermasalah.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>
    </AdminLayout>
</template>
