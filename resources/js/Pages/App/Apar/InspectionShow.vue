<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppHeader from '@/Components/App/AppHeader.vue';
import BottomNavigation from '@/Components/App/Home/BottomNavigation.vue';
import { ChevronLeftIcon } from '@heroicons/vue/24/outline';

defineProps({
    inspection: { type: Object, required: true },
    back: { type: Object, required: true },
});
</script>

<template>
    <AppLayout>
        <div class="min-h-full bg-gray-50">
            <AppHeader title="Detail Inspeksi" :description="inspection.snapshot.apar_code || 'APAR'" :backUrl="back.href" backLabel="Kembali" />

            <main class="space-y-4 px-4 pb-8 pt-4">
                <section class="rounded-xl border border-[#E8E8EC] bg-white p-4">
                    <h2 class="mb-3 text-sm font-semibold text-[#263746]">Informasi Inspeksi (Snapshot)</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">Kode APAR</dt><dd class="font-medium text-[#263746] text-right">{{ inspection.snapshot.apar_code || '-' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">Jenis</dt><dd class="font-medium text-[#263746] text-right">{{ inspection.snapshot.apar_type || '-' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">Merek</dt><dd class="font-medium text-[#263746] text-right">{{ inspection.snapshot.brand || '-' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">Kapasitas</dt><dd class="font-medium text-[#263746] text-right">{{ inspection.snapshot.capacity || '-' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">Nomor Seri</dt><dd class="font-medium text-[#263746] text-right">{{ inspection.snapshot.serial_number || '-' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">Lokasi</dt><dd class="font-medium text-[#263746] text-right">{{ inspection.snapshot.placement || '-' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">Kedaluwarsa</dt><dd class="font-medium text-[#263746] text-right">{{ inspection.snapshot.expiry_date || '-' }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">Unit</dt><dd class="font-medium text-[#263746] text-right">{{ inspection.unit_name }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">Petugas</dt><dd class="font-medium text-[#263746] text-right">{{ inspection.officer_name }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-[#9299A2]">Waktu Inspeksi</dt><dd class="font-medium text-[#263746] text-right">{{ inspection.inspected_at || '-' }}</dd></div>
                        <div class="flex justify-between gap-4 items-center"><dt class="text-[#9299A2]">Hasil Akhir</dt>
                            <dd><span class="rounded-full px-2 py-0.5 text-[11px] font-medium" :class="inspection.result === 'ok' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">{{ inspection.result_label }}</span></dd>
                        </div>
                    </dl>
                    <div v-if="!inspection.apar_is_active" class="mt-3 rounded-lg bg-amber-50 p-2 text-xs text-amber-700">Aset saat ini nonaktif. Data inspeksi ini bersifat historis dan tidak berubah.</div>
                    <div class="mt-3">
                        <p class="text-xs text-[#9299A2]">Catatan Inspeksi</p>
                        <p class="mt-1 whitespace-pre-wrap text-sm text-[#263746]">{{ inspection.notes || '-' }}</p>
                    </div>
                </section>

                <section class="rounded-xl border border-[#E8E8EC] bg-white p-4">
                    <h2 class="mb-3 text-sm font-semibold text-[#263746]">Checklist ({{ inspection.items.length }} item)</h2>
                    <div class="space-y-2">
                        <div v-for="(item, idx) in inspection.items" :key="item.id" class="rounded-lg border border-[#E8E8EC] p-3">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-sm text-[#263746]">{{ item.sort_order || idx + 1 }}. {{ item.item_label }}</p>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium" :class="item.is_good ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'">
                                    {{ item.is_good ? 'Baik' : 'Tidak Baik' }}
                                </span>
                            </div>
                            <p v-if="item.note" class="mt-1 text-xs text-[#9299A2]">Catatan: {{ item.note }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-[#E8E8EC] bg-white p-4">
                    <h2 class="mb-3 text-sm font-semibold text-[#263746]">Foto Inspeksi</h2>
                    <div v-if="inspection.photos && inspection.photos.length" class="grid grid-cols-2 gap-2">
                        <a v-for="photo in inspection.photos" :key="photo.id" :href="photo.url" target="_blank" rel="noopener" class="block overflow-hidden rounded-lg border border-[#E8E8EC]">
                            <img :src="photo.url" alt="Foto inspeksi" class="h-28 w-full object-cover" loading="lazy" />
                        </a>
                    </div>
                    <p v-else class="text-sm text-[#9299A2]">Tidak ada foto.</p>
                </section>
            </main>
        </div>

        <template #bottom>
            <BottomNavigation active="apar" />
        </template>
    </AppLayout>
</template>
