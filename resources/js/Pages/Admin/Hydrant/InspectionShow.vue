<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';

defineProps({
    inspection: { type: Object, required: true },
    back: { type: Object, required: true },
});

function formatDate(d) {
    return d || '-';
}
</script>

<template>
    <AdminLayout title="Detail Inspeksi Hydrant">
        <div class="space-y-6">
            <PageHeader
                :title="`Detail Inspeksi - ${inspection.snapshot.hydrant_code || 'Hydrant'}`"
                :breadcrumb="[
                    { label: 'Data Hydrant', href: '/admin/hydrant' },
                    { label: inspection.snapshot.hydrant_code || 'Hydrant', href: `/admin/hydrant/${inspection.hydrant_id}` },
                    { label: 'Riwayat Inspeksi', href: back.href },
                    { label: `Inspeksi #${inspection.id}` },
                ]"
            />

            <div class="grid gap-6 lg:grid-cols-3">
                <Card class="lg:col-span-2">
                    <template #title>Informasi Inspeksi (Snapshot)</template>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div><dt class="text-xs font-medium text-gray-500">Kode Hydrant</dt><dd class="mt-1 text-sm text-gray-900">{{ inspection.snapshot.hydrant_code || '-' }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Nama</dt><dd class="mt-1 text-sm text-gray-900">{{ inspection.snapshot.name || '-' }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Jenis</dt><dd class="mt-1 text-sm text-gray-900">{{ inspection.snapshot.hydrant_type || '-' }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Merek</dt><dd class="mt-1 text-sm text-gray-900">{{ inspection.snapshot.brand || '-' }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Model</dt><dd class="mt-1 text-sm text-gray-900">{{ inspection.snapshot.model || '-' }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Nomor Seri</dt><dd class="mt-1 text-sm text-gray-900">{{ inspection.snapshot.serial_number || '-' }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Lokasi (saat inspeksi)</dt><dd class="mt-1 text-sm text-gray-900">{{ inspection.snapshot.placement || '-' }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Maintenance Terakhir (saat inspeksi)</dt><dd class="mt-1 text-sm text-gray-900">{{ formatDate(inspection.snapshot.last_maintenance_date) }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Unit</dt><dd class="mt-1 text-sm text-gray-900">{{ inspection.unit_name }}<span v-if="inspection.unit_code" class="text-gray-500"> ({{ inspection.unit_code }})</span></dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Petugas</dt><dd class="mt-1 text-sm text-gray-900">{{ inspection.officer_name }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Waktu Inspeksi</dt><dd class="mt-1 text-sm text-gray-900">{{ inspection.inspected_at || '-' }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Hasil Akhir</dt>
                            <dd class="mt-1">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="inspection.result === 'ok' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                                    {{ inspection.result_label }}
                                </span>
                            </dd>
                        </div>
                        <div><dt class="text-xs font-medium text-gray-500">Status Aset Saat Ini</dt>
                            <dd class="mt-1">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="inspection.hydrant_is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'">
                                    {{ inspection.hydrant_is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                                <span class="ml-2 text-xs text-gray-500">(tidak mengubah data inspeksi)</span>
                            </dd>
                        </div>
                        <div class="sm:col-span-2"><dt class="text-xs font-medium text-gray-500">Catatan Inspeksi</dt><dd class="mt-1 whitespace-pre-wrap text-sm text-gray-900">{{ inspection.notes || '-' }}</dd></div>
                    </dl>
                </Card>

                <Card>
                    <template #title>Foto Inspeksi</template>
                    <div v-if="inspection.photos && inspection.photos.length" class="grid grid-cols-2 gap-2">
                        <a v-for="photo in inspection.photos" :key="photo.id" :href="photo.url" target="_blank" rel="noopener" class="block overflow-hidden rounded-md border">
                            <img :src="photo.url" alt="Foto inspeksi" class="h-28 w-full object-cover" />
                        </a>
                    </div>
                    <p v-else class="text-sm text-gray-500">Tidak ada foto.</p>
                </Card>
            </div>

            <Card>
                <template #title>Checklist Inspeksi</template>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">No</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Item</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Kondisi</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Catatan Item</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(item, idx) in inspection.items" :key="item.id">
                                <td class="px-3 py-2 text-gray-700">{{ item.sort_order || idx + 1 }}</td>
                                <td class="px-3 py-2 text-gray-900">{{ item.item_label }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="item.is_good ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                                        {{ item.is_good ? 'Baik' : 'Tidak Baik' }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-gray-700">{{ item.note || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>

            <div>
                <Link :href="back.href" class="text-sm text-gray-600 hover:text-gray-900">&larr; Kembali ke {{ back.label }}</Link>
            </div>
        </div>
    </AdminLayout>
</template>
