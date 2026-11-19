
<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppHeader from '@/Components/App/AppHeader.vue';
import BottomNavigation from '@/Components/App/Home/BottomNavigation.vue';
import {
    ChevronLeftIcon,
    ChevronDownIcon,
    CalendarDaysIcon,
    ClipboardDocumentCheckIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    apar: { type: Object, required: true },
    latestInspection: { type: Object, default: null },
});

const infoExpanded = ref(false);
</script>

<template>
    <AppLayout>
        <div class="min-h-full bg-[#F7F8FA]">

            <AppHeader title="APAR" :description="`${apar.apar_code} - ${apar.apar_type_label}`" backUrl="/app/apar" backLabel="Daftar APAR" />

            <main class="space-y-5 px-4 pb-8 pt-5">

                <!-- Foto APAR -->
                <div
                    v-if="apar.photo_url"
                    class="-mt-1 overflow-hidden rounded-xl bg-white shadow-sm"
                >
                    <img
                        :src="apar.photo_url"
                        alt="Foto APAR"
                        class="max-h-60 w-full object-contain"
                    />
                </div>

                <!-- Informasi APAR -->
                <section class="rounded-xl bg-white p-4 border border-gray-200">

                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-sm font-semibold text-gray-800">
                            Informasi APAR
                        </h2>

                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Aktif
                        </span>
                    </div>

                    <!-- Informasi utama -->
                    <div class="grid grid-cols-2 gap-3">

                        <div class="rounded-lg bg-gray-50 p-3">
                            <p class="text-xs text-gray-500">
                                Kode APAR
                            </p>
                            <p class="mt-1.5 break-words text-sm font-semibold text-gray-800">
                                {{ apar.apar_code }}
                            </p>
                        </div>

                        <div class="rounded-lg bg-gray-50 p-3">
                            <p class="text-xs text-gray-500">
                                Lokasi
                            </p>
                            <p class="mt-1.5 break-words text-sm font-semibold text-gray-800">
                                {{ apar.placement }}
                            </p>
                        </div>

                    </div>

                    <!-- Informasi tambahan -->
                    <div v-if="infoExpanded" class="mt-4 space-y-3 border-t border-gray-100 pt-4">

                        <div class="flex justify-between gap-4">
                            <span class="text-sm text-gray-500">Jenis APAR</span>
                            <span class="text-right text-sm font-medium text-gray-800">
                                {{ apar.apar_type_label || '-' }}
                            </span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <span class="text-sm text-gray-500">Merek</span>
                            <span class="text-right text-sm font-medium text-gray-800">
                                {{ apar.brand || '-' }}
                            </span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <span class="text-sm text-gray-500">Kapasitas</span>
                            <span class="text-right text-sm font-medium text-gray-800">
                                {{ apar.capacity || '-' }}
                            </span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <span class="text-sm text-gray-500">Nomor seri</span>
                            <span class="text-right text-sm font-medium text-gray-800">
                                {{ apar.serial_number || '-' }}
                            </span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <span class="text-sm text-gray-500">Tanggal produksi</span>
                            <span class="text-right text-sm font-medium text-gray-800">
                                {{ apar.procurement_date || '-' }}
                            </span>
                        </div>

                        <div class="flex justify-between gap-4">
                            <span class="text-sm text-gray-500">Tanggal kedaluwarsa</span>
                            <span class="text-right text-sm font-medium text-gray-800">
                                {{ apar.expiry_date || '-' }}
                            </span>
                        </div>

                    </div>

                    <button
                        type="button"
                        class="mt-4 flex w-full items-center justify-center gap-1.5 border-t border-gray-100 pt-3 text-xs font-medium text-[#1D567F]"
                        @click="infoExpanded = !infoExpanded"
                    >
                        {{ infoExpanded ? 'Sembunyikan informasi' : 'Lihat informasi lengkap' }}
                    </button>
                </section>

                <!-- Inspeksi Terakhir -->
                <section class="rounded-xl bg-white p-4 border border-gray-200">

                    <div class="mb-4 flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50">
                            <ClipboardDocumentCheckIcon class="h-5 w-5 text-[#1D567F]" />
                        </div>

                        <div>
                            <h2 class="text-sm font-semibold text-gray-800">
                                Inspeksi Terakhir
                            </h2>
                            <p class="mt-0.5 text-xs text-gray-500">
                                Catatan pemeriksaan APAR
                            </p>
                        </div>
                    </div>

                    <template v-if="latestInspection">
                        <div class="rounded-lg bg-gray-50 p-3">

                            <div class="flex items-center justify-between gap-3">
                                <span class="text-xs text-gray-500">
                                    Hasil pemeriksaan
                                </span>

                                <span
                                    class="text-xs font-semibold"
                                    :class="latestInspection.result === 'ok'
                                        ? 'text-emerald-700'
                                        : 'text-red-600'"
                                >
                                    {{ latestInspection.result_label }}
                                </span>
                            </div>

                            <div class="mt-3 flex items-center gap-2 text-sm text-gray-700">
                                <CalendarDaysIcon class="h-4 w-4 shrink-0 text-gray-400" />
                                {{ latestInspection.inspected_at || '-' }}
                            </div>

                            <div class="mt-2 text-xs text-gray-500">
                                Petugas:
                                <span class="font-medium text-gray-700">
                                    {{ latestInspection.officer_name || '-' }}
                                </span>
                            </div>

                        </div>
                    </template>

                    <div v-else class="rounded-lg bg-gray-50 px-3 py-4">
                        <p class="text-sm text-gray-600">
                            Belum ada inspeksi yang tercatat.
                        </p>
                        <p class="mt-1 text-xs text-gray-400">
                            Lakukan inspeksi untuk mencatat kondisi APAR.
                        </p>
                    </div>

                    <Link
    :href="`/app/apar/${apar.id}/inspections`"
    class="mt-3 flex items-center justify-center py-2 text-xs font-medium text-[#1D567F]"
>
    Lihat riwayat inspeksi
</Link>
                </section>

                <!-- Aksi utama -->
                <Link
                    :href="`/app/apar/${apar.id}/inspect`"
                    class="flex w-full items-center justify-center rounded-xl bg-[#1D567F] px-4 py-3.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#174768] active:bg-[#123D59]"
                >
                    Mulai Inspeksi
                </Link>

            </main>
        </div>

        <template #bottom>
            <BottomNavigation active="apar" />
        </template>
    </AppLayout>
</template>
