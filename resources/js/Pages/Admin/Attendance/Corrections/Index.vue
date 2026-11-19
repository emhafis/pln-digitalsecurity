<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Badge from '@/Components/Admin/Badge.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import SearchInput from '@/Components/Admin/SearchInput.vue';
import SearchSelect from '@/Components/Admin/SearchSelect.vue';
import Select from '@/Components/Admin/Select.vue';
import InputError from '@/Components/Admin/InputError.vue';

const props = defineProps({
    corrections: { type: Array, default: () => [] },
    meta: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
});

const filters = ref({ ...props.filters });
const selected = ref(null);
const decision = ref(null);
const form = useForm({ review_note: '' });

const statusVariants = {
    pending: 'amber',
    approved: 'emerald',
    rejected: 'red',
};

const decisionTitle = computed(() => (
    decision.value === 'approve' ? 'Setujui Pengajuan Koreksi' : 'Tolak Pengajuan Koreksi'
));

function queryParams(page = null) {
    const params = {
        search: filters.value.search || undefined,
        status: filters.value.status || undefined,
        unit_id: filters.value.unit_id || undefined,
    };

    if (page) {
        params.page = page;
    }

    return Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    );
}

function applyFilter(page = null) {
    router.get('/admin/attendance-correction', queryParams(page), { preserveState: true, preserveScroll: true });
}

function resetFilter() {
    filters.value = {
        search: '',
        status: props.filters.status ?? '',
        unit_id: '',
    };
    applyFilter();
}

function goToPage(page) {
    applyFilter(page);
}

function openDecision(correction, value) {
    selected.value = correction;
    decision.value = value;
    form.reset();
    form.clearErrors();
}

function closeDecision() {
    selected.value = null;
    decision.value = null;
    form.reset();
    form.clearErrors();
}

function submitDecision() {
    if (!selected.value || !decision.value) {
        return;
    }

    const path = `/admin/attendance-correction/${selected.value.id}/${decision.value}`;
    form.post(path, {
        preserveScroll: true,
        onSuccess: () => closeDecision(),
    });
}
</script>

<template>
    <AdminLayout title="Koreksi Absensi">
        <div class="space-y-6">
            <PageHeader
                title="Koreksi Absensi"
                description="Tinjau pengajuan koreksi absensi Satpam. Setiap keputusan dicatat beserta peninjau dan waktunya."
                :breadcrumb="[
                    { label: 'Absensi' },
                    { label: 'Koreksi Absensi' },
                ]"
            />

            <FlashMessage />

            <Card title="Filter">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-600">Pencarian</label>
                        <SearchInput
                            v-model="filters.search"
                            placeholder="Nama atau NIP Satpam..."
                            class="sm:w-full"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Status Pengajuan</label>
                        <Select v-model="filters.status" :options="statuses" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Unit</label>
                        <SearchSelect
                            v-model="filters.unit_id"
                            :options="units"
                            placeholder="Semua Unit"
                            none-label="Semua Unit"
                            search-placeholder="Cari unit..."
                        />
                    </div>

                    <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-4">
                        <Button variant="secondary" @click="applyFilter()">Terapkan</Button>
                        <Button variant="ghost" @click="resetFilter">Reset</Button>
                    </div>
                </div>
            </Card>

            <Card
                v-if="selected"
                :title="decisionTitle"
                :description="`${selected.security_name} - ${selected.work_date_label ?? '-'}`"
            >
                <div class="space-y-4 text-sm text-gray-700">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <p class="text-xs font-medium text-gray-500">Check-in Diminta</p>
                            <p class="mt-0.5 font-medium text-gray-900">{{ selected.requested_check_in ?? 'Tidak diubah' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">Check-out Diminta</p>
                            <p class="mt-0.5 font-medium text-gray-900">{{ selected.requested_check_out ?? 'Tidak diubah' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-500">Status Diminta</p>
                            <p class="mt-0.5 font-medium text-gray-900">{{ selected.requested_status ?? 'Tidak diubah' }}</p>
                        </div>
                    </div>

                    <p class="text-xs text-gray-600"><span class="font-medium text-gray-700">Alasan:</span> {{ selected.reason }}</p>

                    <label class="block">
                        <span class="text-xs font-medium text-gray-600">Catatan Peninjau (opsional)</span>
                        <textarea
                            v-model="form.review_note"
                            rows="3"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20"
                        />
                    </label>
                    <InputError :messages="form.errors.review_note || form.errors.correction" />

                    <div class="flex flex-wrap gap-2">
                        <Button
                            :variant="decision === 'approve' ? 'primary' : 'danger'"
                            :disabled="form.processing"
                            @click="submitDecision"
                        >
                            {{ form.processing ? 'Memproses...' : (decision === 'approve' ? 'Setujui' : 'Tolak') }}
                        </Button>
                        <Button variant="secondary" :disabled="form.processing" @click="closeDecision">Batal</Button>
                    </div>
                </div>
            </Card>

            <DataTable
                :has-rows="corrections.length > 0"
                empty-title="Belum ada pengajuan koreksi"
                empty-description="Pengajuan koreksi dari Satpam akan muncul di sini."
            >
                <template #head>
                    <th>Diajukan</th>
                    <th>Satpam</th>
                    <th>Tanggal / Shift</th>
                    <th>Perubahan Diminta</th>
                    <th>Alasan</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </template>

                <tr v-for="correction in corrections" :key="correction.id">
                    <td class="whitespace-nowrap">
                        <p class="text-gray-800">{{ correction.requested_at }}</p>
                        <p class="text-xs text-gray-500">{{ correction.requested_by ?? '-' }}</p>
                    </td>
                    <td class="min-w-[180px]">
                        <p class="font-medium text-gray-900">{{ correction.security_name }}</p>
                        <p class="text-xs text-gray-500">{{ correction.registration_number ?? '-' }} - {{ correction.unit_name }}</p>
                    </td>
                    <td class="whitespace-nowrap">
                        <p class="text-gray-800">{{ correction.work_date_label ?? '-' }}</p>
                        <p class="text-xs text-gray-500">{{ correction.shift_name ?? '-' }}</p>
                    </td>
                    <td class="min-w-[180px] text-xs text-gray-700">
                        <p><span class="text-gray-500">Check-in:</span> {{ correction.requested_check_in ?? '-' }}</p>
                        <p><span class="text-gray-500">Check-out:</span> {{ correction.requested_check_out ?? '-' }}</p>
                        <p><span class="text-gray-500">Status:</span> {{ correction.requested_status ?? '-' }}</p>
                    </td>
                    <td class="max-w-xs text-sm text-gray-700">
                        <p class="line-clamp-3" :title="correction.reason">{{ correction.reason }}</p>
                    </td>
                    <td class="min-w-[170px]">
                        <Badge :variant="statusVariants[correction.status] ?? 'gray'">{{ correction.status_label }}</Badge>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ correction.reviewed_by ?? '-' }}
                            <span v-if="correction.reviewed_at">, {{ correction.reviewed_at }}</span>
                        </p>
                    </td>
                    <td class="whitespace-nowrap text-right">
                        <div class="flex flex-col items-end gap-1.5">
                            <Link :href="correction.show_url" class="text-xs font-medium text-blue-600 transition hover:text-blue-700 hover:underline">
                                Detail Absensi
                            </Link>
                            <template v-if="correction.status === 'pending'">
                                <button type="button" class="text-xs font-medium text-emerald-600 transition hover:text-emerald-700 hover:underline" @click="openDecision(correction, 'approve')">
                                    Setujui
                                </button>
                                <button type="button" class="text-xs font-medium text-red-600 transition hover:text-red-700 hover:underline" @click="openDecision(correction, 'reject')">
                                    Tolak
                                </button>
                            </template>
                        </div>
                    </td>
                </tr>

                <template #pagination>
                    <Pagination :meta="meta" @change="goToPage" />
                </template>
            </DataTable>
        </div>
    </AdminLayout>
</template>
