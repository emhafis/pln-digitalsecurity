<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import ImageWithFallback from '@/Components/Admin/ImageWithFallback.vue';

const props = defineProps({
    apar: { type: Object, required: true },
});

const year = ref(new Date().getFullYear());

function toggleActive() {
    const action = props.apar.is_active
        ? 'menonaktifkan'
        : 'mengaktifkan';

    if (confirm(`Yakin ${action} APAR "${props.apar.apar_code}"?`)) {
        router.post(
            `/admin/apar/${props.apar.id}/toggle-active`,
            {},
            { preserveScroll: true }
        );
    }
}

function formatDate(value) {
    return value || '-';
}

function openQrPdf() {
    window.open(`/admin/apar/${props.apar.id}/qr-pdf`, '_blank');
}

function openAnnualCard() {
    window.open(
        `/admin/apar/${props.apar.id}/annual-card?year=${year.value}`,
        '_blank'
    );
}

const equipmentDetails = [
    { label: 'Jenis APAR', value: props.apar.apar_type_label },
    { label: 'Merek', value: props.apar.brand },
    { label: 'Kapasitas', value: props.apar.capacity },
    { label: 'Nomor Seri', value: props.apar.serial_number },
    { label: 'Lokasi Penempatan', value: props.apar.placement },
    { label: 'Unit', value: props.apar.unit_name },
];

const dateDetails = [
    { label: 'Produksi / Pengadaan', value: props.apar.procurement_date },
    { label: 'Tanggal Pemasangan', value: props.apar.installation_date },
    { label: 'Pengisian Ulang', value: props.apar.recharge_date },
    { label: 'Tanggal Kedaluwarsa', value: props.apar.expiry_date },
];
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
            :class="apar.is_active
                ? 'border-amber-300 text-amber-700 hover:bg-amber-50'
                : 'border-green-300 text-green-700 hover:bg-green-50'"
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

        <Link
            href="/admin/apar"
            class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
        >
            Kembali
        </Link>
    </template>
</PageHeader>

            <FlashMessage />

            <!-- PROFILE -->
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <div class="flex flex-col gap-6 p-5 sm:flex-row sm:items-center sm:p-7">

                    <ImageWithFallback
                        :src="apar.photo_url"
                        alt="Foto APAR"
                        class="h-48 w-full shrink-0 rounded-md border border-gray-200 object-cover sm:h-36 sm:w-44"
                    />

                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium uppercase tracking-wider text-gray-400">
                            Identitas Peralatan
                        </p>

                        <h2 class="mt-2 text-2xl font-semibold tracking-tight text-gray-900">
                            {{ apar.apar_code }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-500">
                            {{ apar.apar_type_label || '-' }}
                            <span class="mx-1 text-gray-300">/</span>
                            {{ apar.brand || '-' }}
                        </p>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center gap-2 rounded-md border px-2.5 py-1 text-xs font-medium"
                                :class="apar.is_active
                                    ? 'border-green-200 bg-green-50 text-green-700'
                                    : 'border-gray-200 bg-gray-50 text-gray-600'"
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full"
                                    :class="apar.is_active ? 'bg-green-500' : 'bg-gray-400'"
                                ></span>
                                {{ apar.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>

                            <span class="rounded-md border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs text-gray-600">
                                {{ apar.capacity || '-' }}
                            </span>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4 sm:border-l sm:border-t-0 sm:pl-6 sm:pt-0">
                        <p class="text-xs text-gray-400">Lokasi Penempatan</p>
                        <p class="mt-1.5 max-w-48 text-sm font-medium text-gray-800">
                            {{ apar.placement || '-' }}
                        </p>

                        <p class="mt-4 text-xs text-gray-400">Unit</p>
                        <p class="mt-1.5 text-sm text-gray-700">
                            {{ apar.unit_name || '-' }}
                        </p>
                    </div>
                </div>
            </section>

            <!-- CONTENT -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                <div class="space-y-6 lg:col-span-2">

                    <!-- INFORMASI PERALATAN -->
                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                        <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
                            <h3 class="text-sm font-semibold text-gray-900">
                                Informasi Peralatan
                            </h3>
                            <p class="mt-1 text-xs text-gray-500">
                                Spesifikasi dan identitas unit APAR.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2">
                            <div
                                v-for="item in equipmentDetails"
                                :key="item.label"
                                class="border-b border-gray-100 px-5 py-4 sm:px-6"
                            >
                                <p class="text-xs text-gray-400">
                                    {{ item.label }}
                                </p>
                                <p class="mt-1.5 break-words text-sm text-gray-800">
                                    {{ item.value || '-' }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- TANGGAL -->
                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                        <div class="border-b border-gray-100 px-5 py-4 sm:px-6">
                            <h3 class="text-sm font-semibold text-gray-900">
                                Riwayat Tanggal
                            </h3>
                            <p class="mt-1 text-xs text-gray-500">
                                Informasi produksi, pemasangan, dan pemeliharaan APAR.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2">
                            <div
                                v-for="item in dateDetails"
                                :key="item.label"
                                class="border-b border-gray-100 px-5 py-4 sm:px-6"
                            >
                                <p class="text-xs text-gray-400">
                                    {{ item.label }}
                                </p>
                                <p class="mt-1.5 text-sm font-medium text-gray-800">
                                    {{ formatDate(item.value) }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- QR UUID -->
                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                        <div class="px-5 py-4 sm:px-6">
                            <h3 class="text-sm font-semibold text-gray-900">
                                Identifikasi QR Code
                            </h3>
                            <p class="mt-1 text-xs text-gray-500">
                                UUID yang digunakan untuk identifikasi APAR.
                            </p>

                            <div class="mt-4 rounded-md border border-gray-200 bg-gray-50 px-4 py-3">
                                <p class="break-all font-mono text-xs leading-relaxed text-gray-600">
                                    {{ apar.qr_code || '-' }}
                                </p>
                            </div>
                        </div>
                    </section>

                </div>

                <!-- SIDEBAR -->
                <div class="space-y-6">

                    <!-- DOKUMEN -->
                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                        <div class="border-b border-gray-100 px-5 py-4">
                            <h3 class="text-sm font-semibold text-gray-900">
                                Dokumen APAR
                            </h3>
                            <p class="mt-1 text-xs text-gray-500">
                                QR Code dan kartu inspeksi tahunan.
                            </p>
                        </div>

                        <div class="space-y-5 p-5">
                            <div>
                                <p class="text-sm font-medium text-gray-800">
                                    QR Code APAR
                                </p>
                                <p class="mt-1 text-xs leading-relaxed text-gray-500">
                                    Cetak QR Code untuk ditempelkan pada unit APAR.
                                </p>

                                <button
                                    type="button"
                                    class="mt-3 w-full rounded-md border border-gray-300 px-4 py-2.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50"
                                    @click="openQrPdf"
                                >
                                    Unduh QR Code PDF
                                </button>
                            </div>

                            <div class="border-t border-gray-100 pt-5">
                                <p class="text-sm font-medium text-gray-800">
                                    Kartu Inspeksi Tahunan
                                </p>

                                <p class="mt-1 text-xs leading-relaxed text-gray-500">
                                    Lihat seluruh inspeksi periode Januari–Desember.
                                </p>

                                <label class="mt-4 block text-xs text-gray-500">
                                    Tahun inspeksi
                                </label>

                                <div class="mt-2 flex gap-2">
                                    <input
                                        v-model.number="year"
                                        type="number"
                                        min="2000"
                                        max="2100"
                                        class="min-w-0 flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                                    />

                                    <button
                                        type="button"
                                        class="rounded-md bg-blue-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-blue-700"
                                        @click="openAnnualCard"
                                    >
                                        Buka PDF
                                    </button>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- RINGKASAN INSPEKSI -->
                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                        <div class="border-b border-gray-100 px-5 py-4">
                            <h3 class="text-sm font-semibold text-gray-900">
                                Ringkasan Inspeksi
                            </h3>
                            <p class="mt-1 text-xs text-gray-500">
                                Informasi pemeriksaan terakhir.
                            </p>
                        </div>

                        <div class="space-y-5 p-5">
                            <div>
                                <p class="text-xs text-gray-400">
                                    Total inspeksi
                                </p>
                                <p class="mt-1 text-2xl font-semibold tracking-tight text-gray-900">
                                    {{ apar.inspections_count ?? 0 }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500">
                                    Pemeriksaan tercatat
                                </p>
                            </div>

                            <div class="border-t border-gray-100 pt-4">
                                <p class="text-xs text-gray-400">
                                    Hasil pemeriksaan terakhir
                                </p>

                                <template v-if="apar.latest_inspection">
                                    <div class="mt-2">
                                        <span
                                            class="inline-flex rounded-md border px-2.5 py-1 text-xs font-medium"
                                            :class="apar.latest_inspection.result === 'ok'
                                                ? 'border-green-200 bg-green-50 text-green-700'
                                                : 'border-red-200 bg-red-50 text-red-700'"
                                        >
                                            {{ apar.latest_inspection.result === 'ok' ? 'Baik' : 'Tidak Baik' }}
                                        </span>
                                    </div>

                                    <p class="mt-2 text-sm text-gray-700">
                                        {{ apar.latest_inspection.inspected_at || '-' }}
                                    </p>
                                </template>

                                <p v-else class="mt-2 text-sm text-gray-400">
                                    Belum ada histori inspeksi.
                                </p>
                            </div>

                            <div class="border-t border-gray-100 pt-4">
                                <Link
                                    :href="`/admin/apar/${apar.id}/inspections`"
                                    class="inline-flex items-center gap-2 text-sm font-medium text-blue-600 transition hover:text-blue-800"
                                >
                                    Lihat riwayat inspeksi →
                                </Link>
                            </div>
                        </div>
                    </section>

                </div>
            </div>

            <!-- METADATA -->
            <section class="rounded-lg border border-gray-200 bg-white px-5 py-4 sm:px-6">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs text-gray-400">Data dibuat</p>
                        <p class="mt-1.5 text-xs text-gray-600">
                            {{ apar.created_at || '-' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-400">Terakhir diperbarui</p>
                        <p class="mt-1.5 text-xs text-gray-600">
                            {{ apar.updated_at || '-' }}
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>