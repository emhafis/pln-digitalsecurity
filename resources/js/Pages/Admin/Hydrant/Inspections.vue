<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';

const props = defineProps({
    hydrant: { type: Object, required: true },
    items: { type: Array, default: () => [] },
    meta: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const form = ref({
    search: props.filters.search || '',
    result: props.filters.result || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
});

function apply() {
    router.get(`/admin/hydrant/${props.hydrant.id}/inspections`, {
        search: form.value.search || undefined,
        result: form.value.result || undefined,
        date_from: form.value.date_from || undefined,
        date_to: form.value.date_to || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function reset() {
    form.value = { search: '', result: '', date_from: '', date_to: '' };
    apply();
}

function go(page) {
    router.get(`/admin/hydrant/${props.hydrant.id}/inspections`, {
        ...form.value,
        page,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

const hasFilters = computed(() => !!(form.value.search || form.value.result || form.value.date_from || form.value.date_to));

function exportExcel() {
    const params = new URLSearchParams();
    params.set('hydrant_id', String(props.hydrant.id));
    if (form.value.search) params.set('search', form.value.search);
    if (form.value.result) params.set('result', form.value.result);
    if (form.value.date_from) params.set('date_from', form.value.date_from);
    if (form.value.date_to) params.set('date_to', form.value.date_to);
    window.open(`/admin/hydrant/inspections/export?${params.toString()}`, '_blank');
}
</script>

<template>
    <AdminLayout title="Riwayat Inspeksi Hydrant">
        <div class="space-y-6">
            <PageHeader
                :title="`Riwayat Inspeksi - ${hydrant.hydrant_code}`"
                :breadcrumb="[
                    { label: 'Data Hydrant', href: '/admin/hydrant' },
                    { label: hydrant.hydrant_code, href: `/admin/hydrant/${hydrant.id}` },
                    { label: 'Riwayat Inspeksi' },
                ]"
            />

            <FlashMessage />

            <Card>
                <template #title>Filter</template>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="lg:col-span-2">
                        <label class="mb-1 block text-xs font-medium text-gray-600">Cari (kode Hydrant / petugas)</label>
                        <input v-model="form.search" type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" placeholder="Kode Hydrant atau nama petugas" @keyup.enter="apply" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Hasil</label>
                        <select v-model="form.result" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                            <option value="">Semua</option>
                            <option value="ok">Baik (OK)</option>
                            <option value="not_ok">Tidak Baik (NOT OK)</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Dari Tanggal</label>
                        <input v-model="form.date_from" type="date" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Sampai Tanggal</label>
                        <input v-model="form.date_to" type="date" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" />
                    </div>
                </div>
                <div class="mt-3 flex gap-2">
                    <button type="button" class="rounded-md bg-blue-600 px-4 py-2 text-xs text-white hover:bg-blue-700" @click="apply">Terapkan</button>
                    <button v-if="hasFilters" type="button" class="rounded-md border border-gray-300 px-4 py-2 text-xs text-gray-700 hover:bg-gray-50" @click="reset">Reset</button>
                </div>
            </Card>

            <Card>
                <template #title>
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <span>Daftar Riwayat Inspeksi</span>
                        <button type="button" class="inline-flex items-center justify-center rounded-md border border-green-600 px-3 py-1.5 text-xs font-medium text-green-700 hover:bg-green-50" @click="exportExcel">
                            Ekspor Excel
                        </button>
                    </div>
                </template>
                <div v-if="items.length === 0" class="py-8 text-center text-sm text-gray-500">Belum ada riwayat inspeksi.</div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Waktu Inspeksi</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Kode Hydrant</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Petugas</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Unit</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Hasil</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Foto</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="item in items" :key="item.id" class="hover:bg-gray-50">
                                <td class="px-3 py-2 text-gray-900">{{ item.inspected_at || '-' }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ item.hydrant_code }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ item.officer_name }}</td>
                                <td class="px-3 py-2 text-gray-700">{{ item.unit_name }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="item.result === 'ok' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                                        {{ item.result_label }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-gray-700">{{ item.photos_count }}</td>
                                <td class="px-3 py-2 text-right">
                                    <Link :href="item.detail_url" class="text-blue-600 hover:text-blue-800">Detail</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="meta.last_page > 1" class="mt-4 flex items-center justify-between text-xs text-gray-600">
                    <button type="button" class="rounded-md border border-gray-300 px-3 py-1.5 disabled:opacity-40" :disabled="meta.current_page <= 1" @click="go(meta.current_page - 1)">Sebelumnya</button>
                    <span>Hal {{ meta.current_page }} / {{ meta.last_page }} ({{ meta.total }} data)</span>
                    <button type="button" class="rounded-md border border-gray-300 px-3 py-1.5 disabled:opacity-40" :disabled="meta.current_page >= meta.last_page" @click="go(meta.current_page + 1)">Berikutnya</button>
                </div>
            </Card>

            <div>
                <Link :href="`/admin/hydrant/${hydrant.id}`" class="text-sm text-gray-600 hover:text-gray-900">&larr; Kembali ke detail Hydrant</Link>
            </div>
        </div>
    </AdminLayout>
</template>
