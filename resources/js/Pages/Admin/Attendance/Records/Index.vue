<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
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
import TextInput from '@/Components/Admin/TextInput.vue';

const props = defineProps({
    records: { type: Array, default: () => [] },
    meta: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    posts: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
});

const filters = ref({ ...props.filters });

const statusVariants = {
    scheduled: 'gray',
    pending: 'amber',
    in_progress: 'blue',
    completed: 'emerald',
    absent: 'red',
    day_off: 'gray',
};

const statusOptions = computed(() => [
    { value: '', label: 'Semua Status' },
    ...props.statuses,
]);

function queryParams(page = null) {
    const params = {
        search: filters.value.search || undefined,
        status: filters.value.status || undefined,
        unit_id: filters.value.unit_id || undefined,
        security_post_id: filters.value.security_post_id || undefined,
        period_id: filters.value.period_id || undefined,
        date_from: filters.value.date_from || undefined,
        date_to: filters.value.date_to || undefined,
        sort: filters.value.sort || undefined,
        dir: filters.value.dir || undefined,
    };

    if (page) {
        params.page = page;
    }

    return Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    );
}

function applyFilter(page = null) {
    router.get('/admin/attendance-record', queryParams(page), { preserveState: true, preserveScroll: true });
}

function resetFilter() {
    filters.value = {
        search: '',
        status: '',
        unit_id: '',
        security_post_id: '',
        period_id: '',
        date_from: '',
        date_to: '',
        sort: 'work_date',
        dir: 'desc',
    };
    const params = {
        search: filters.value.search || undefined,
        status: filters.value.status || undefined,
        unit_id: filters.value.unit_id || undefined,
        security_post_id: filters.value.security_post_id || undefined,
        period_id: filters.value.period_id || undefined,
        sort: filters.value.sort || undefined,
        dir: filters.value.dir || undefined,
    };
    router.get('/admin/attendance-record', Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    ), { preserveState: true, preserveScroll: true });
}

function goToPage(page) {
    applyFilter(page);
}
</script>

<template>
    <AdminLayout title="Data Absensi">
        <div class="space-y-6">
            <PageHeader
                title="Data Absensi"
                description="Pantau data absensi Satpam yang dihasilkan dari periode jadwal."
                :breadcrumb="[
                    { label: 'Absensi' },
                    { label: 'Data Absensi' },
                ]"
            >
                <template #actions>
                    <Link
                        href="/admin/attendance/readiness"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Kesiapan Absensi
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <Card title="Filter">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="sm:col-span-2 lg:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-600">Pencarian</label>
                        <SearchInput
                            v-model="filters.search"
                            placeholder="Nama, NIP, pos, atau unit..."
                            class="sm:w-full"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Status</label>
                        <Select v-model="filters.status" :options="statusOptions" />
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

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Pos Jaga</label>
                        <SearchSelect
                            v-model="filters.security_post_id"
                            :options="posts"
                            placeholder="Semua Pos"
                            none-label="Semua Pos"
                            search-placeholder="Cari pos..."
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Dari Tanggal</label>
                        <TextInput v-model="filters.date_from" type="date" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Sampai Tanggal</label>
                        <TextInput v-model="filters.date_to" type="date" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">ID Periode</label>
                        <TextInput v-model="filters.period_id" type="number" min="1" placeholder="Semua periode" />
                    </div>

                    <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-4">
                        <Button variant="secondary" @click="applyFilter()">Terapkan</Button>
                        <Button variant="ghost" @click="resetFilter">Reset</Button>
                    </div>
                </div>
            </Card>

            <DataTable
                :has-rows="records.length > 0"
                empty-title="Belum ada data absensi"
                empty-description="Data absensi akan muncul setelah periode jadwal siap dan pembuatan data absensi dijalankan."
            >
                <template #head>
                    <th>Tanggal</th>
                    <th>Satpam</th>
                    <th>Unit / Pos</th>
                    <th>Shift</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </template>

                <tr v-for="record in records" :key="record.id">
                    <td class="whitespace-nowrap text-sm text-gray-700">{{ record.work_date_label }}</td>
                    <td class="min-w-[180px]">
                        <p class="font-medium text-gray-900">{{ record.security_name }}</p>
                        <p class="text-xs text-gray-500">{{ record.registration_number ?? '-' }}</p>
                    </td>
                    <td class="min-w-[200px]">
                        <p class="text-gray-800">{{ record.unit_name }}</p>
                        <p class="text-xs text-gray-500">{{ record.post_name }} - {{ record.team_name }}</p>
                    </td>
                    <td class="min-w-[150px]">
                        <p class="text-gray-800">{{ record.shift_name }}</p>
                        <p class="text-xs text-gray-500">{{ record.scheduled_start }} - {{ record.scheduled_end }}</p>
                    </td>
                    <td class="whitespace-nowrap">
                        <span class="text-gray-800">{{ record.check_in ?? '-' }}</span>
                        <span v-if="record.is_late" class="ml-1 text-xs font-medium text-red-600">Terlambat</span>
                    </td>
                    <td class="whitespace-nowrap text-gray-800">{{ record.check_out ?? '-' }}</td>
                    <td>
                        <div class="flex flex-wrap items-center gap-1.5">
                            <Badge :variant="statusVariants[record.status] ?? 'gray'">{{ record.status_label }}</Badge>
                            <Badge v-if="record.has_pending_correction" variant="amber">Koreksi</Badge>
                        </div>
                    </td>
                    <td class="whitespace-nowrap text-right">
                        <Link :href="record.show_url" class="text-xs font-medium text-blue-600 transition hover:text-blue-700 hover:underline">
                            Detail
                        </Link>
                    </td>
                </tr>

                <template #pagination>
                    <Pagination :meta="meta" @change="goToPage" />
                </template>
            </DataTable>
        </div>
    </AdminLayout>
</template>
