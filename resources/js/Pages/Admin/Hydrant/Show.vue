<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import ImageWithFallback from '@/Components/Admin/ImageWithFallback.vue';

const props = defineProps({
    hydrant: { type: Object, required: true },
});

function toggleActive() {
    const action = props.hydrant.is_active ? 'menonaktifkan' : 'mengaktifkan';
    if (confirm(`Yakin ${action} Hydrant "${props.hydrant.hydrant_code}"?`)) {
        router.post(`/admin/hydrant/${props.hydrant.id}/toggle-active`, {}, { preserveScroll: true });
    }
}

function formatDate(d) {
    return d || '-';
}

function openQrPdf() {
    window.open(`/admin/hydrant/${props.hydrant.id}/qr-pdf`, '_blank');
}
</script>

<template>
    <AdminLayout title="Detail Hydrant">
        <div class="space-y-6">
            <PageHeader
                :title="`Detail Hydrant - ${hydrant.hydrant_code}`"
                :breadcrumb="[
                    { label: 'Data Hydrant', href: '/admin/hydrant' },
                    { label: hydrant.hydrant_code },
                ]"
            >
                <template #actions>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md border px-4 py-2 text-xs transition"
                        :class="hydrant.is_active ? 'border-amber-300 text-amber-700 hover:bg-amber-50' : 'border-green-300 text-green-700 hover:bg-green-50'"
                        @click="toggleActive"
                    >
                        {{ hydrant.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                    <Link
                        :href="`/admin/hydrant/${hydrant.id}/edit`"
                        class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-xs text-white transition hover:bg-blue-700"
                    >
                        Edit
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <div class="grid gap-6 lg:grid-cols-3">
                <Card class="lg:col-span-2">
                    <template #title>Informasi Hydrant</template>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div><dt class="text-xs font-medium text-gray-500">Kode Hydrant</dt><dd class="mt-1 text-sm text-gray-900">{{ hydrant.hydrant_code }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Nama</dt><dd class="mt-1 text-sm text-gray-900">{{ hydrant.name }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Jenis</dt><dd class="mt-1 text-sm text-gray-900">{{ hydrant.hydrant_type_label }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Merek</dt><dd class="mt-1 text-sm text-gray-900">{{ hydrant.brand }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Model</dt><dd class="mt-1 text-sm text-gray-900">{{ hydrant.model }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Nomor Seri</dt><dd class="mt-1 text-sm text-gray-900">{{ hydrant.serial_number || '-' }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Lokasi Penempatan</dt><dd class="mt-1 text-sm text-gray-900">{{ hydrant.placement }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Unit</dt><dd class="mt-1 text-sm text-gray-900">{{ hydrant.unit_name }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Status</dt>
                            <dd class="mt-1">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="hydrant.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'">
                                    {{ hydrant.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </dd>
                        </div>
                        <div><dt class="text-xs font-medium text-gray-500">Tgl Produksi/Pengadaan</dt><dd class="mt-1 text-sm text-gray-900">{{ formatDate(hydrant.procurement_date) }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Tgl Instalasi</dt><dd class="mt-1 text-sm text-gray-900">{{ formatDate(hydrant.installation_date) }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Tgl Perawatan Terakhir</dt><dd class="mt-1 text-sm text-gray-900">{{ formatDate(hydrant.last_maintenance_date) }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs font-medium text-gray-500">QR Code (UUID)</dt><dd class="mt-1 font-mono text-xs text-gray-700">{{ hydrant.qr_code }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Dibuat</dt><dd class="mt-1 text-sm text-gray-900">{{ hydrant.created_at || '-' }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Diperbarui</dt><dd class="mt-1 text-sm text-gray-900">{{ hydrant.updated_at || '-' }}</dd></div>
                    </dl>
                </Card>

                <div class="space-y-6">
                    <Card>
                        <template #title>Foto Hydrant</template>
                        <ImageWithFallback :src="hydrant.photo_url" alt="Foto Hydrant" class="h-56 w-full rounded-md border object-cover" />
                    </Card>

                    <Card>
                        <template #title>Ringkasan Inspeksi</template>
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs font-medium text-gray-500">Jumlah Inspeksi</p>
                                <p class="mt-1 text-lg font-semibold text-gray-900">{{ hydrant.inspections_count }}</p>
                            </div>
                            <div v-if="hydrant.latest_inspection">
                                <p class="text-xs font-medium text-gray-500">Hasil Terakhir</p>
                                <p class="mt-1">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="hydrant.latest_inspection.result === 'ok' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                                    >
                                        {{ hydrant.latest_inspection.result === 'ok' ? 'Baik' : 'Tidak Baik' }}
                                    </span>
                                </p>
                                <p class="mt-1 text-sm text-gray-700">{{ hydrant.latest_inspection.inspected_at || '-' }}</p>
                            </div>
                            <p v-else class="text-sm text-gray-500">Belum ada histori inspeksi.</p>
                            <div class="pt-1">
                                <Link :href="`/admin/hydrant/${hydrant.id}/inspections`" class="text-sm font-medium text-blue-600 hover:text-blue-800">Lihat Riwayat Inspeksi &rarr;</Link>
                            </div>
                        </div>
                    </Card>

                    <Card>
                        <template #title>Dokumen Hydrant</template>
                        <button
                            type="button"
                            class="w-full rounded-md border border-blue-600 px-3 py-2 text-sm font-medium text-blue-700 hover:bg-blue-50"
                            @click="openQrPdf"
                        >
                            QR Code Hydrant (PDF)
                        </button>
                    </Card>
                </div>
            </div>

            <div>
                <Link href="/admin/hydrant" class="text-sm text-gray-600 hover:text-gray-900">&larr; Kembali ke daftar Hydrant</Link>
            </div>
        </div>
    </AdminLayout>
</template>
