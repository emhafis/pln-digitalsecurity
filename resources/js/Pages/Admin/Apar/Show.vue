<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';

const props = defineProps({
    apar: { type: Object, required: true },
});

const year = ref(new Date().getFullYear());

function toggleActive() {
    const action = props.apar.is_active ? 'menonaktifkan' : 'mengaktifkan';
    if (confirm(`Yakin ${action} APAR "${props.apar.apar_code}"?`)) {
        router.post(`/admin/apar/${props.apar.id}/toggle-active`, {}, { preserveScroll: true });
    }
}

function formatDate(d) {
    return d || '-';
}

function openQrPdf() {
    window.open(`/admin/apar/${props.apar.id}/qr-pdf`, '_blank');
}

function openAnnualCard() {
    window.open(`/admin/apar/${props.apar.id}/annual-card?year=${year.value}`, '_blank');
}
</script>

<template>
    <AdminLayout title="Detail APAR">
        <div class="space-y-6">
            <PageHeader
                :title="`Detail APAR - ${apar.apar_code}`"
                :breadcrumb="[
                    { label: 'Data APAR', href: '/admin/apar' },
                    { label: apar.apar_code },
                ]"
            >
                <template #actions>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md border px-4 py-2 text-xs transition"
                        :class="apar.is_active ? 'border-amber-300 text-amber-700 hover:bg-amber-50' : 'border-green-300 text-green-700 hover:bg-green-50'"
                        @click="toggleActive"
                    >
                        {{ apar.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                    <Link
                        :href="`/admin/apar/${apar.id}/edit`"
                        class="inline-flex items-center justify-center rounded-md bg-blue-600 px-4 py-2 text-xs text-white transition hover:bg-blue-700"
                    >
                        Edit
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <div class="grid gap-6 lg:grid-cols-3">
                <Card class="lg:col-span-2">
                    <template #title>Informasi APAR</template>
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div><dt class="text-xs font-medium text-gray-500">Kode APAR</dt><dd class="mt-1 text-sm text-gray-900">{{ apar.apar_code }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Jenis</dt><dd class="mt-1 text-sm text-gray-900">{{ apar.apar_type_label }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Merek</dt><dd class="mt-1 text-sm text-gray-900">{{ apar.brand }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Kapasitas</dt><dd class="mt-1 text-sm text-gray-900">{{ apar.capacity }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Lokasi Penempatan</dt><dd class="mt-1 text-sm text-gray-900">{{ apar.placement }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Unit</dt><dd class="mt-1 text-sm text-gray-900">{{ apar.unit_name }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Nomor Seri</dt><dd class="mt-1 text-sm text-gray-900">{{ apar.serial_number || '-' }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Status</dt>
                            <dd class="mt-1">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="apar.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'">
                                    {{ apar.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </dd>
                        </div>
                        <div><dt class="text-xs font-medium text-gray-500">Tgl Produksi/Pengadaan</dt><dd class="mt-1 text-sm text-gray-900">{{ formatDate(apar.procurement_date) }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Tgl Pemasangan</dt><dd class="mt-1 text-sm text-gray-900">{{ formatDate(apar.installation_date) }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Tgl Isi Ulang</dt><dd class="mt-1 text-sm text-gray-900">{{ formatDate(apar.recharge_date) }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Tgl Kedaluwarsa</dt><dd class="mt-1 text-sm text-gray-900">{{ formatDate(apar.expiry_date) }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-xs font-medium text-gray-500">QR Code (UUID)</dt><dd class="mt-1 font-mono text-xs text-gray-700">{{ apar.qr_code }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Dibuat</dt><dd class="mt-1 text-sm text-gray-900">{{ apar.created_at || '-' }}</dd></div>
                        <div><dt class="text-xs font-medium text-gray-500">Diperbarui</dt><dd class="mt-1 text-sm text-gray-900">{{ apar.updated_at || '-' }}</dd></div>
                    </dl>
                </Card>

                <div class="space-y-6">
                    <Card>
                        <template #title>Foto APAR</template>
                        <div v-if="apar.photo_url">
                            <img :src="apar.photo_url" alt="Foto APAR" class="w-full rounded-md border object-cover" />
                        </div>
                        <p v-else class="text-sm text-gray-500">Tidak ada foto.</p>
                    </Card>

                    <Card>
                        <template #title>QR & Kartu Inspeksi</template>
                        <div class="space-y-3">
                            <button type="button" class="w-full rounded-md border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50" @click="openQrPdf">
                                QR Code APAR (PDF)
                            </button>
                            <div class="space-y-2">
                                <label class="block text-xs font-medium text-gray-600">Kartu Inspeksi Tahunan</label>
                                <div class="flex items-center gap-2">
                                    <input v-model.number="year" type="number" min="2000" max="2100" class="w-28 rounded-md border border-gray-300 px-2 py-1.5 text-sm" />
                                    <button type="button" class="flex-1 rounded-md bg-blue-600 px-3 py-2 text-xs text-white hover:bg-blue-700" @click="openAnnualCard">
                                        Buka PDF
                                    </button>
                                </div>
                                <p class="text-[11px] text-gray-500">Menampilkan seluruh inspeksi periode Januari–Desember tahun dipilih.</p>
                            </div>
                        </div>
                    </Card>

                    <Card>
                        <template #title>Ringkasan Inspeksi</template>
                        <div class="space-y-3">
                            <div>
                                <p class="text-xs font-medium text-gray-500">Jumlah Inspeksi</p>
                                <p class="mt-1 text-lg font-semibold text-gray-900">{{ apar.inspections_count }}</p>
                            </div>
                            <div v-if="apar.latest_inspection">
                                <p class="text-xs font-medium text-gray-500">Hasil Terakhir</p>
                                <p class="mt-1">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="apar.latest_inspection.result === 'ok' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'"
                                    >
                                        {{ apar.latest_inspection.result === 'ok' ? 'Baik' : 'Tidak Baik' }}
                                    </span>
                                </p>
                                <p class="mt-1 text-sm text-gray-700">{{ apar.latest_inspection.inspected_at || '-' }}</p>
                            </div>
                            <p v-else class="text-sm text-gray-500">Belum ada histori inspeksi.</p>
                            <div class="pt-1">
                                <Link :href="`/admin/apar/${apar.id}/inspections`" class="text-sm font-medium text-blue-600 hover:text-blue-800">Lihat Riwayat Inspeksi &rarr;</Link>
                            </div>
                        </div>
                    </Card>
                </div>
            </div>

            <div>
                <Link href="/admin/apar" class="text-sm text-gray-600 hover:text-gray-900">&larr; Kembali ke daftar APAR</Link>
            </div>
        </div>
    </AdminLayout>
</template>
