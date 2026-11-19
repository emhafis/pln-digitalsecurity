<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Badge from '@/Components/Admin/Badge.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import StatCard from '@/Components/Admin/StatCard.vue';

const props = defineProps({
    stats: { type: Object, required: true },
    activeVisits: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const dateFrom = ref(props.filters.date_from || '');
const dateTo = ref(props.filters.date_to || '');
let debounce = null;

function apply(page = 1) {
    router.get('/admin/guest-book', {
        search: search.value || undefined,
        status: status.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        page,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

watch(search, () => { clearTimeout(debounce); debounce = setTimeout(() => apply(1), 300); });
watch([status, dateFrom, dateTo], () => apply(1));
function go(p) { apply(p); }

function statusVariant(s) {
    return s === 'active' ? 'amber' : 'emerald';
}
</script>

<template>
    <AdminLayout title="Buku Tamu Digital">
        <div class="space-y-6">
            <PageHeader
                title="Buku Tamu Digital"
                description="Monitoring dan riwayat kunjungan tamu."
                :breadcrumb="[
                    { label: 'Buku Tamu', href: '/admin/guest-book' },
                    { label: 'Monitoring Kunjungan' },
                ]"
            />

            <FlashMessage />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard label="Masih di Lokasi" :value="stats.active" />
                <StatCard label="Kunjungan Hari Ini" :value="stats.today" />
                <StatCard label="Sudah Check-out Hari Ini" :value="stats.checked_out_today" />
            </div>

            <Card v-if="activeVisits.length">
                <template #title>Kunjungan Aktif Terbaru</template>
                <DataTable :has-rows="activeVisits.length > 0" empty-title="Tidak ada kunjungan aktif">
                    <template #head>
                        <th>Nama</th>
                        <th>Instansi</th>
                        <th>Unit</th>
                        <th>Check-in</th>
                        <th class="text-center">Aksi</th>
                    </template>
                    <tr v-for="v in activeVisits" :key="v.id">
                        <td class="font-medium text-gray-800">{{ v.full_name }}</td>
                        <td>{{ v.company_name }}</td>
                        <td>{{ v.unit_name }}</td>
                        <td class="whitespace-nowrap">{{ v.check_in_at }}</td>
                        <td class="text-center">
                            <Link :href="v.detail_url" class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-blue-600 transition hover:bg-blue-50 hover:text-blue-700">Detail</Link>
                        </td>
                    </tr>
                </DataTable>
            </Card>

            <Card>
                <template #title>Daftar Kunjungan</template>
                <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <input v-model="search" type="search" placeholder="Cari nama, instansi, HP..." class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                    <select v-model="status" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                        <option value="">Semua status</option>
                        <option value="active">Masih di Lokasi</option>
                        <option value="checked_out">Sudah Pulang</option>
                    </select>
                    <input v-model="dateFrom" type="date" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                    <input v-model="dateTo" type="date" class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" />
                </div>

                <DataTable :has-rows="items.length > 0" empty-title="Tidak ada data kunjungan" empty-description="Ubah filter atau tunggu kunjungan baru.">
                    <template #head>
                        <th>Nama</th>
                        <th>No. HP</th>
                        <th>Instansi</th>
                        <th>Tujuan</th>
                        <th>Bertemu</th>
                        <th>Unit</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Status</th>
                        <th class="text-center">Aksi</th>
                    </template>
                    <tr v-for="v in items" :key="v.id">
                        <td class="font-medium text-gray-800">{{ v.full_name }}</td>
                        <td>{{ v.phone_number }}</td>
                        <td>{{ v.company_name }}</td>
                        <td class="max-w-[200px] truncate" :title="v.visit_purpose">{{ v.visit_purpose }}</td>
                        <td>{{ v.person_to_meet }}</td>
                        <td>{{ v.unit_name }}</td>
                        <td class="whitespace-nowrap">{{ v.check_in_at }}</td>
                        <td class="whitespace-nowrap">{{ v.check_out_at || '-' }}</td>
                        <td><Badge :variant="statusVariant(v.status)">{{ v.status_label }}</Badge></td>
                        <td class="text-center">
                            <Link :href="v.detail_url" class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-blue-600 transition hover:bg-blue-50 hover:text-blue-700">Detail</Link>
                        </td>
                    </tr>

                    <template #pagination>
                        <Pagination v-if="meta && meta.last_page > 1" :meta="meta" @change="go" />
                    </template>
                </DataTable>
            </Card>
        </div>
    </AdminLayout>
</template>
