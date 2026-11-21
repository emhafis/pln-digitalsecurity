<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import StatCard from '@/Components/Admin/StatCard.vue';
import SearchSelect from '@/Components/Admin/SearchSelect.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 }) },
    filters: { type: Object, default: () => ({}) },
    units: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({ total: 0, ok: 0, not_ok: 0 }) },
});

const form = ref({
    search: props.filters.search || '',
    unit_id: props.filters.unit_id ?? '',
    result: props.filters.result || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
});

function apply(page = 1) {
    router.get('/admin/monitoring/hydrant', {
        search: form.value.search || undefined,
        unit_id: form.value.unit_id ?? undefined,
        result: form.value.result || undefined,
        date_from: form.value.date_from || undefined,
        date_to: form.value.date_to || undefined,
        page,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function reset() {
    form.value = { search: '', unit_id: '', result: '', date_from: '', date_to: '' };
    apply();
}
</script>

<template>
    <AdminLayout title="Monitoring Hydrant">
        <div class="space-y-6">
            <PageHeader
                title="Monitoring Hydrant"
                description="Pantau seluruh riwayat inspeksi Hydrant sesuai unit yang dapat diakses."
                :breadcrumb="[
                    { label: 'Monitoring Hydrant' },
                ]"
            />

            <FlashMessage />

            <div class="grid gap-4 sm:grid-cols-3">
                <StatCard label="Total Inspeksi" :value="summary.total" hint="Sesuai filter aktif" />
                <StatCard label="Hasil Baik" :value="summary.ok" hint="Kondisi OK" />
                <StatCard label="Tidak Baik" :value="summary.not_ok" hint="Perlu tindak lanjut" />
            </div>

            <Card title="Filter" description="Gunakan filter untuk mempersempit data monitoring.">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-600">Cari</label>
                        <input
                            v-model="form.search"
                            type="text"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            placeholder="Kode, nama, lokasi, unit, atau petugas"
                            @keyup.enter="apply()"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Unit</label>
                        <SearchSelect
                            v-model="form.unit_id"
                            :options="units"
                            placeholder="Semua Unit"
                            none-label="Semua Unit"
                            search-placeholder="Cari unit..."
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Hasil</label>
                        <select
                            v-model="form.result"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                        >
                            <option value="">Semua</option>
                            <option value="ok">Baik (OK)</option>
                            <option value="not_ok">Tidak Baik (NOT OK)</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2 sm:col-span-2 lg:col-span-1">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Dari</label>
                            <input v-model="form.date_from" type="date" class="w-full rounded-md border border-gray-300 px-2 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Sampai</label>
                            <input v-model="form.date_to" type="date" class="w-full rounded-md border border-gray-300 px-2 py-2 text-sm" />
                        </div>
                    </div>
                </div>
                <div class="mt-3 flex gap-2">
                    <button type="button" class="rounded-md bg-blue-600 px-4 py-2 text-xs text-white hover:bg-blue-700" @click="apply()">Terapkan</button>
                    <button type="button" class="rounded-md border border-gray-300 px-4 py-2 text-xs text-gray-700 hover:bg-gray-50" @click="reset">Reset</button>
                </div>
            </Card>

            <div>
                <DataTable :has-rows="items.length > 0" empty-title="Belum ada inspeksi Hydrant" empty-description="Riwayat inspeksi Hydrant akan muncul di sini.">
                    <template #head>
                        <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Waktu</th>
                        <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Kode Hydrant</th>
                        <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Nama</th>
                        <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Lokasi</th>
                        <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Unit</th>
                        <th class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">Petugas</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Hasil</th>
                        <th class="px-3 py-2 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Foto</th>
                    </template>

                    <tr
                        v-for="item in items"
                        :key="item.id"
                        class="cursor-pointer hover:bg-gray-50/60"
                        @click="router.visit(item.detail_url)"
                    >
                        <td class="px-3 py-2 text-sm text-gray-700">{{ item.inspected_at || '-' }}</td>
                        <td class="px-3 py-2 text-sm font-medium text-gray-900">{{ item.hydrant_code || '-' }}</td>
                        <td class="px-3 py-2 text-sm text-gray-600">{{ item.name || '-' }}</td>
                        <td class="px-3 py-2 text-sm text-gray-600">{{ item.placement || '-' }}</td>
                        <td class="px-3 py-2 text-sm text-gray-600">{{ item.unit_name || '-' }}</td>
                        <td class="px-3 py-2 text-sm text-gray-600">{{ item.officer_name || '-' }}</td>
                        <td class="px-3 py-2 text-center">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="item.result === 'ok' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                            >
                                {{ item.result_label }}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-center text-sm text-gray-600">{{ item.photos_count }}</td>
                    </tr>

                    <template #pagination>
                        <Pagination v-if="meta" :meta="meta" @change="apply" />
                    </template>
                </DataTable>
            </div>
        </div>
    </AdminLayout>
</template>
