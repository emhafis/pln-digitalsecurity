<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import SearchSelect from '@/Components/App/SearchSelect.vue';

const props = defineProps({
    record: { type: Object, default: null },
    records: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    selected_record_id: { type: [Number, null], default: null },
    correctable_statuses: { type: Array, default: () => [] },
});

const page = usePage();
const correctionType = ref('');
const selectedId = ref(props.selected_record_id || props.record?.id || '');

const month = ref(props.filters?.month ?? (new Date().getMonth() + 1));
const year = ref(props.filters?.year ?? new Date().getFullYear());

const monthOptions = [
    { value: 1, label: 'Januari' }, { value: 2, label: 'Februari' }, { value: 3, label: 'Maret' },
    { value: 4, label: 'April' }, { value: 5, label: 'Mei' }, { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' }, { value: 8, label: 'Agustus' }, { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' }, { value: 11, label: 'November' }, { value: 12, label: 'Desember' },
];
const currentYear = new Date().getFullYear();
const yearOptions = computed(() => {
    const years = [];
    for (let y = currentYear - 2; y <= currentYear + 1; y++) years.push({ value: y, label: String(y) });
    return years;
});

const recordOptions = computed(() =>
    (props.records || []).map((r) => ({ value: r.id, label: r.option_label || `${r.work_date_label} - ${r.shift_name || '-'}` }))
);

const correctionOptions = [
    { value: 'check_in', label: 'Koreksi Waktu Absen Masuk' },
    { value: 'check_out', label: 'Koreksi Waktu Absen Keluar' },
    { value: 'check_in_out', label: 'Koreksi Absen Masuk dan Keluar' },
    { value: 'status', label: 'Koreksi Status Kehadiran' },
];

const form = useForm({
    requested_check_in_at: '',
    requested_check_out_at: '',
    requested_status: '',
    reason: '',
});

const flashSuccess = computed(() => page.props.flash?.success || '');
const flashError = computed(() => page.props.flash?.error || '');
const submitError = ref('');

const hasFieldErrors = computed(() => form.errors && Object.keys(form.errors).length > 0);

const showCheckIn = computed(() => ['check_in', 'check_in_out'].includes(correctionType.value));
const showCheckOut = computed(() => ['check_out', 'check_in_out'].includes(correctionType.value));
const showStatus = computed(() => correctionType.value === 'status');

watch(() => props.filters, (f) => {
    if (f?.month) month.value = f.month;
    if (f?.year) year.value = f.year;
}, { deep: true });

watch(() => props.record, (r) => {
    if (!r) return;
    form.requested_check_in_at = r.check_in_time || '';
    form.requested_check_out_at = r.check_out_time || '';
    form.requested_status = r.status && ['in_progress','completed','absent','day_off'].includes(r.status) ? r.status : '';
    correctionType.value = '';
    form.clearErrors();
}, { immediate: true });

watch(correctionType, () => {
    if (!props.record) return;
    if (!showCheckIn.value) form.requested_check_in_at = '';
    if (!showCheckOut.value) form.requested_check_out_at = '';
    if (!showStatus.value) form.requested_status = '';
    if (showCheckIn.value && !form.requested_check_in_at) form.requested_check_in_at = props.record.check_in_time || '';
    if (showCheckOut.value && !form.requested_check_out_at) form.requested_check_out_at = props.record.check_out_time || '';
    if (showStatus.value && !form.requested_status) {
        form.requested_status = ['in_progress','completed','absent','day_off'].includes(props.record.status) ? props.record.status : (props.correctable_statuses[0]?.value || '');
    }
});

function applyPeriod() {
    selectedId.value = '';
    router.get('/app/attendance/correction', { month: month.value, year: year.value }, { preserveScroll: true, preserveState: false });
}

function onSelectRecord() {
    if (!selectedId.value) return;
    router.get('/app/attendance/correction', { record_id: selectedId.value, month: month.value, year: year.value }, { preserveScroll: true, preserveState: false });
}

const canSubmit = computed(() => {
    if (!props.record || !props.record.can_request_correction) return false;
    if (!correctionType.value) return false;
    if (!String(form.reason || '').trim()) return false;
    if (showCheckIn.value && !form.requested_check_in_at) return false;
    if (showCheckOut.value && !form.requested_check_out_at) return false;
    if (showStatus.value && !form.requested_status) return false;
    return !form.processing;
});

function submit() {
    if (!props.record || form.processing) return;
    submitError.value = '';
    form.clearErrors();
    form.transform((data) => ({
        reason: data.reason,
        requested_check_in_at: showCheckIn.value ? data.requested_check_in_at : null,
        requested_check_out_at: showCheckOut.value ? data.requested_check_out_at : null,
        requested_status: showStatus.value ? data.requested_status : null,
    })).post(`/app/attendance/${props.record.id}/correction`, {
        preserveScroll: true,
        onSuccess: () => {
            correctionType.value = '';
            form.reason = '';
            form.requested_status = '';
            form.requested_check_in_at = '';
            form.requested_check_out_at = '';
            submitError.value = '';
        },
        onError: (errors) => {
            if (!errors || Object.keys(errors).length === 0) {
                submitError.value = 'Pengajuan koreksi gagal dikirim. Silakan coba lagi.';
            }
        },
        onFinish: () => {
            if (form.hasErrors && !submitError.value && (!form.errors || Object.keys(form.errors).length === 0)) {
                submitError.value = 'Pengajuan koreksi gagal dikirim. Silakan coba lagi.';
            }
        },
    });
}

const correctionStatusLabel = computed(() => {
    if (!props.record) return '';
    if (props.record.has_pending_correction) return 'Menunggu Keputusan';
    return props.record.latest_correction_status_label || '';
});
</script>

<template>
    <div class="space-y-5">
        <div v-if="flashSuccess || form.recentlySuccessful" class="rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-800">
            {{ flashSuccess || 'Pengajuan koreksi berhasil dikirim.' }}
        </div>
        <div v-if="flashError" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm font-medium text-red-700">
            {{ flashError }}
        </div>
        <div v-if="hasFieldErrors || submitError" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
            <p class="font-medium">Pengajuan koreksi gagal dikirim.</p>
            <p v-if="submitError" class="mt-0.5 text-xs">{{ submitError }}</p>
            <p v-else class="mt-0.5 text-xs">Periksa kembali isian yang ditandai lalu coba lagi.</p>
        </div>

        <section class="rounded-xl border border-[#E8E8EC] bg-white p-4 space-y-3">
            <p class="text-sm font-semibold text-[#263746]">Pilih Data Kehadiran</p>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="mb-1.5 block text-xs text-[#9299A2]">Bulan</label>
                    <SearchSelect
                        v-model="month"
                        :options="monthOptions"
                        placeholder="Pilih bulan"
                        search-placeholder="Cari bulan..."
                        @update:model-value="applyPeriod"
                    />
                </div>
                <div>
                    <label class="mb-1.5 block text-xs text-[#9299A2]">Tahun</label>
                    <SearchSelect
                        v-model="year"
                        :options="yearOptions"
                        placeholder="Pilih tahun"
                        search-placeholder="Cari tahun..."
                        @update:model-value="applyPeriod"
                    />
                </div>
            </div>
            <div>
                <label class="mb-1.5 block text-xs text-[#9299A2]">Riwayat kehadiran (milik Anda)</label>
                <div v-if="records.length === 0" class="rounded-lg border border-dashed border-[#E8E8EC] bg-gray-50 px-3 py-2.5 text-sm text-[#9299A2]">
                    Tidak ada data kehadiran pada periode ini.
                </div>
                <SearchSelect
                    v-else
                    v-model="selectedId"
                    :options="recordOptions"
                    placeholder="Pilih data kehadiran"
                    search-placeholder="Cari data kehadiran..."
                    @update:model-value="onSelectRecord"
                />
            </div>
        </section>

        <template v-if="record">
            <section class="rounded-xl border border-[#E8E8EC] bg-gray-50 p-4 space-y-1.5">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-[#263746]">{{ record.work_date_label }}</p>
                        <p class="text-xs text-[#9299A2]">{{ record.shift_name || '-' }} • {{ record.post_name || '-' }}<span v-if="record.unit_name"> • {{ record.unit_name }}</span></p>
                    </div>
                    <span class="shrink-0 rounded-full border border-[#E8E8EC] bg-white px-2.5 py-0.5 text-[11px] font-medium text-[#263746]">{{ record.status_label }}</span>
                </div>
                <p class="text-xs text-[#263746]">Masuk: <span class="font-medium">{{ record.check_in_label || '-' }}</span> • Keluar: <span class="font-medium">{{ record.check_out_label || '-' }}</span></p>
                <p v-if="correctionStatusLabel" class="text-xs font-medium" :class="record.has_pending_correction ? 'text-amber-700' : 'text-[#1D567F]'">
                    Status koreksi: {{ correctionStatusLabel }}
                </p>
            </section>

            <div v-if="record.has_pending_correction" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Pengajuan koreksi untuk data ini masih menunggu keputusan. Anda dapat mengajukan koreksi baru setelah pengajuan sebelumnya selesai.
            </div>

            <form v-else class="space-y-5" @submit.prevent="submit" novalidate>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[#263746]">Jenis Koreksi</label>
                    <select
                        v-model="correctionType"
                        class="w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-sm text-[#263746] focus:border-[#1D567F] focus:outline-none"
                    >
                        <option value="" disabled>Pilih jenis koreksi</option>
                        <option v-for="opt in correctionOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                </div>

                <div v-if="showCheckIn">
                    <label class="mb-1.5 block text-sm font-medium text-[#263746]">Jam Absen Masuk (WIB)</label>
                    <p class="mb-1.5 text-xs text-[#9299A2]">Tanggal mengikuti data kehadiran ({{ record.work_date_label }}).</p>
                    <input
                        v-model="form.requested_check_in_at"
                        type="time"
                        step="60"
                        class="w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-sm text-[#263746] focus:border-[#1D567F] focus:outline-none"
                    />
                    <p v-if="form.errors.requested_check_in_at" class="mt-1.5 text-xs text-[#DC5555]">{{ form.errors.requested_check_in_at }}</p>
                </div>

                <div v-if="showCheckOut">
                    <label class="mb-1.5 block text-sm font-medium text-[#263746]">Jam Absen Keluar (WIB)</label>
                    <p class="mb-1.5 text-xs text-[#9299A2]">Tanggal mengikuti data kehadiran ({{ record.work_date_label }}).</p>
                    <input
                        v-model="form.requested_check_out_at"
                        type="time"
                        step="60"
                        class="w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-sm text-[#263746] focus:border-[#1D567F] focus:outline-none"
                    />
                    <p v-if="form.errors.requested_check_out_at" class="mt-1.5 text-xs text-[#DC5555]">{{ form.errors.requested_check_out_at }}</p>
                </div>

                <div v-if="showStatus">
                    <label class="mb-1.5 block text-sm font-medium text-[#263746]">Status Hasil Koreksi</label>
                    <select
                        v-model="form.requested_status"
                        class="w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-sm text-[#263746] focus:border-[#1D567F] focus:outline-none"
                    >
                        <option value="" disabled>Pilih status</option>
                        <option v-for="s in correctable_statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                    <p v-if="form.errors.requested_status" class="mt-1.5 text-xs text-[#DC5555]">{{ form.errors.requested_status }}</p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[#263746]">Alasan Koreksi</label>
                    <textarea
                        v-model="form.reason"
                        rows="4"
                        placeholder="Jelaskan alasan pengajuan koreksi kehadiran"
                        class="w-full rounded-lg border border-[#E5E7EB] bg-white px-3 py-2.5 text-sm text-[#263746] placeholder:text-[#9299A2] focus:border-[#1D567F] focus:outline-none"
                    ></textarea>
                    <p v-if="form.errors.reason" class="mt-1.5 text-xs text-[#DC5555]">{{ form.errors.reason }}</p>
                </div>

                <div class="rounded-lg border border-dashed border-[#E5E7EB] bg-white p-3 text-xs text-[#9299A2]">
                    Lampiran bukti belum didukung pada pengajuan koreksi ini.
                </div>

                <p v-if="form.errors.correction" class="text-xs text-[#DC5555]">{{ form.errors.correction }}</p>
                <p v-if="form.errors.attendance" class="text-xs text-[#DC5555]">{{ form.errors.attendance }}</p>

                <button
                    type="submit"
                    :disabled="!canSubmit"
                    class="w-full rounded-lg bg-[#1D567F] py-3 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ form.processing ? 'Mengirim...' : 'Ajukan Koreksi' }}
                </button>
            </form>
        </template>

        <template v-else>
            <div class="rounded-xl border border-dashed border-[#E8E8EC] bg-gray-50 p-4 text-sm text-[#263746]">
                Pilih data kehadiran dari riwayat untuk mengajukan koreksi.
            </div>
        </template>
    </div>
</template>
