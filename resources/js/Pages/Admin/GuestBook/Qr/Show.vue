<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Badge from '@/Components/Admin/Badge.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';

const props = defineProps({
    unit: { type: Object, required: true },
    checkin: { type: Object, required: true },
    checkout: { type: Object, required: true },
    canManage: { type: Boolean, default: false },
});

function regenCheckIn() {
    if (!confirm('Regenerasi QR Check-in? QR lama akan tidak berlaku untuk transaksi baru. Histori kunjungan tetap utuh.')) return;
    router.post(props.checkin.regenerate_url, {}, { preserveScroll: true });
}
function regenCheckOut() {
    if (!confirm('Regenerasi QR Check-out? QR lama akan tidak berlaku untuk transaksi baru. Histori kunjungan tetap utuh.')) return;
    router.post(props.checkout.regenerate_url, {}, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout title="Detail QR Unit">
        <div class="space-y-6">
            <PageHeader
                :title="`QR Buku Tamu — ${unit.unit_name}`"
                :description="unit.unit_code ? `Kode unit: ${unit.unit_code}` : 'Detail QR Check-in dan Check-out'"
                :breadcrumb="[
                    { label: 'Buku Tamu', href: '/admin/guest-book' },
                    { label: canManage ? 'Kelola QR Unit' : 'QR Unit', href: '/admin/guest-book/qr' },
                    { label: unit.unit_name },
                ]"
            >
                <template #actions>
                    <Link href="/admin/guest-book/qr" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50">Kembali</Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <Card>
                <div class="flex flex-wrap items-center gap-4 text-sm">
                    <div><span class="text-xs font-medium text-gray-500">Status unit:</span> <Badge :variant="unit.is_active ? 'emerald' : 'gray'">{{ unit.is_active ? 'Aktif' : 'Nonaktif' }}</Badge></div>
                    <p class="text-xs text-gray-500">QR Check-in dan Check-out digunakan tamu untuk mencatat kunjungan pada unit ini.</p>
                </div>
            </Card>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <Card>
                    <template #title>QR Check-in</template>
                    <div v-if="checkin.ready" class="space-y-4">
                        <div class="flex justify-center"><img :src="checkin.qr_data_uri" alt="QR Check-in" class="h-56 w-56 rounded-md border border-gray-200 bg-white p-2" /></div>
                        <div class="space-y-2 text-xs text-gray-600">
                            <div><span class="font-medium text-gray-700">URL:</span> <a :href="checkin.url" target="_blank" class="break-all text-blue-600 hover:underline">{{ checkin.url }}</a></div>
                            <div class="break-all"><span class="font-medium text-gray-700">UUID:</span> <span class="font-mono">{{ checkin.uuid }}</span></div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a :href="checkin.download_url" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50">Unduh PNG</a>
                            <button v-if="canManage" type="button" class="inline-flex items-center justify-center rounded-md bg-amber-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-amber-700" @click="regenCheckIn">Regenerasi Check-in</button>
                        </div>
                        <p v-if="canManage" class="text-[11px] text-gray-500">Regenerasi hanya mengganti QR Check-in. QR Check-out dan histori kunjungan tidak berubah.</p>
                    </div>
                    <p v-else class="text-sm text-gray-500">QR Check-in belum tersedia.</p>
                </Card>

                <Card>
                    <template #title>QR Check-out</template>
                    <div v-if="checkout.ready" class="space-y-4">
                        <div class="flex justify-center"><img :src="checkout.qr_data_uri" alt="QR Check-out" class="h-56 w-56 rounded-md border border-gray-200 bg-white p-2" /></div>
                        <div class="space-y-2 text-xs text-gray-600">
                            <div><span class="font-medium text-gray-700">URL:</span> <a :href="checkout.url" target="_blank" class="break-all text-blue-600 hover:underline">{{ checkout.url }}</a></div>
                            <div class="break-all"><span class="font-medium text-gray-700">UUID:</span> <span class="font-mono">{{ checkout.uuid }}</span></div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a :href="checkout.download_url" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50">Unduh PNG</a>
                            <button v-if="canManage" type="button" class="inline-flex items-center justify-center rounded-md bg-amber-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-amber-700" @click="regenCheckOut">Regenerasi Check-out</button>
                        </div>
                        <p v-if="canManage" class="text-[11px] text-gray-500">Regenerasi hanya mengganti QR Check-out. QR Check-in dan histori kunjungan tidak berubah.</p>
                    </div>
                    <div v-else class="space-y-3">
                        <p class="text-sm text-amber-700">QR Check-out belum tersedia (NULL).</p>
                        <p v-if="canManage" class="text-xs text-gray-500">Jalankan backfill dari halaman daftar QR untuk mengisi QR Check-out yang kosong.</p>
                    </div>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
