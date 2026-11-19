<script setup>
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Badge from '@/Components/Admin/Badge.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';

const props = defineProps({
    units: { type: Array, default: () => [] },
    missingCheckoutCount: { type: Number, default: 0 },
    canManage: { type: Boolean, default: false },
});

function backfill() {
    if (!confirm(`Jalankan backfill QR Check-out untuk ${props.missingCheckoutCount} unit yang masih NULL? Tindakan ini hanya mengisi QR Check-out kosong dan tidak mengubah QR lain atau histori.`)) return;
    router.post('/admin/guest-book/qr/backfill', {}, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout title="QR Buku Tamu">
        <div class="space-y-6">
            <PageHeader
                :title="canManage ? 'Kelola QR Buku Tamu' : 'QR Buku Tamu Unit'"
                :description="canManage ? 'Lihat, unduh, dan regenerasi QR Check-in/Check-out per unit (Superadmin).' : 'Lihat dan unduh QR Check-in/Check-out unit dalam cakupan Anda.'"
                :breadcrumb="[
                    { label: 'Buku Tamu', href: '/admin/guest-book' },
                    { label: canManage ? 'Kelola QR Unit' : 'QR Unit' },
                ]"
            >
                <template #actions>
                    <button v-if="canManage && missingCheckoutCount > 0" type="button" class="inline-flex items-center justify-center rounded-md bg-amber-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-amber-700" @click="backfill">
                        Backfill QR Check-out ({{ missingCheckoutCount }})
                    </button>
                </template>
            </PageHeader>

            <FlashMessage />

            <Card v-if="canManage && missingCheckoutCount > 0">
                <div class="flex items-start gap-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                    <span>Terdapat <span class="font-semibold">{{ missingCheckoutCount }}</span> unit dengan QR Check-out masih kosong (NULL). Gunakan <b>Backfill QR Check-out</b> untuk mengisi UUID baru secara aman (hanya NULL, idempotent, transaksi).</span>
                </div>
            </Card>

            <Card>
                <template #title>Daftar Unit</template>
                <DataTable :has-rows="units.length > 0" empty-title="Tidak ada unit" empty-description="Unit dalam cakupan Anda akan tampil di sini.">
                    <template #head>
                        <th>Unit</th>
                        <th>Kode</th>
                        <th>Status Unit</th>
                        <th>QR Check-in</th>
                        <th>QR Check-out</th>
                        <th class="text-center">Aksi</th>
                    </template>
                    <tr v-for="u in units" :key="u.id">
                        <td class="font-medium text-gray-800">{{ u.unit_name }}</td>
                        <td>{{ u.unit_code || '-' }}</td>
                        <td><Badge :variant="u.is_active ? 'emerald' : 'gray'">{{ u.is_active ? 'Aktif' : 'Nonaktif' }}</Badge></td>
                        <td><Badge :variant="u.checkin_ready ? 'emerald' : 'amber'">{{ u.checkin_ready ? 'Siap' : 'Belum ada' }}</Badge></td>
                        <td><Badge :variant="u.checkout_ready ? 'emerald' : 'amber'">{{ u.checkout_ready ? 'Siap' : 'Belum ada' }}</Badge></td>
                        <td class="text-center">
                            <Link :href="u.detail_url" class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-blue-600 transition hover:bg-blue-50 hover:text-blue-700">Detail QR</Link>
                        </td>
                    </tr>
                </DataTable>
            </Card>
        </div>
    </AdminLayout>
</template>
