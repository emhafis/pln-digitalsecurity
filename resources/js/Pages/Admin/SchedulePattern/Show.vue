<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Badge from '@/Components/Admin/Badge.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';

const props = defineProps({
    pattern: { type: Object, required: true },
});

const info = computed(() => [
    { label: 'Nama Pola', value: props.pattern.name },
    { label: 'Unit', value: props.pattern.unit_name },
    { label: 'Jumlah Hari Siklus', value: `${props.pattern.cycle_length} hari` },
    { label: 'Status', value: props.pattern.status, badge: true },
    { label: 'Dibuat', value: props.pattern.created_at },
    { label: 'Diperbarui', value: props.pattern.updated_at },
]);

function toggleActive() {
    const action = props.pattern.is_active ? 'Nonaktifkan' : 'Aktifkan';
    const note = props.pattern.is_active
        ? ' Pola yang nonaktif tidak akan digunakan untuk periode jadwal baru, tetapi jadwal dan histori lama tidak berubah.'
        : '';

    if (confirm(`${action} pola rotasi "${props.pattern.name}"?${note}`)) {
        router.patch(`/admin/schedule-pattern/${props.pattern.id}/toggle`);
    }
}

function destroy() {
    if (confirm(`Hapus pola rotasi "${props.pattern.name}"? Pola yang sudah digunakan oleh periode atau jadwal tidak dapat dihapus.`)) {
        router.delete(`/admin/schedule-pattern/${props.pattern.id}`);
    }
}
</script>

<template>
    <AdminLayout title="Detail Pola Rotasi">
        <div class="space-y-6">
            <PageHeader
                title="Pola Rotasi"
                :breadcrumb="[
                    { label: 'Pola Rotasi', href: '/admin/schedule-pattern' },
                    { label: 'Detail' },
                ]"
            >
                <template #actions>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-xs transition"
                        :class="props.pattern.is_active
                            ? 'bg-amber-500 text-white hover:bg-amber-600'
                            : 'bg-emerald-600 text-white hover:bg-emerald-700'"
                        @click="toggleActive"
                    >
                        {{ props.pattern.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-red-600 px-4 py-2 text-xs text-white transition hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2"
                        @click="destroy"
                    >
                        Hapus
                    </button>
                    <Link
                        :href="`/admin/schedule-pattern/${props.pattern.id}/edit`"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs text-white transition hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                    >
                        Edit
                    </Link>
                    <Link
                        href="/admin/schedule-pattern"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Kembali
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <div
                v-if="pattern.is_used"
                class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700"
            >
                Pola ini sudah digunakan oleh {{ pattern.period_members_count }} anggota periode dan
                {{ pattern.schedules_count }} jadwal. Jumlah hari siklus dan unit tidak dapat diubah.
                Perubahan template tidak akan mengubah jadwal yang sudah tersimpan.
            </div>

            <Card>
                <table class="w-full text-left">
                    <tbody>
                        <tr
                            v-for="item in info"
                            :key="item.label"
                            class="border-b border-gray-100 last:border-0"
                        >
                            <th class="w-48 py-2 pr-4 align-top text-xs font-medium text-gray-900">
                                {{ item.label }}
                            </th>
                            <td class="py-2 text-sm text-gray-700">
                                <Badge
                                    v-if="item.badge"
                                    :variant="props.pattern.is_active ? 'emerald' : 'gray'"
                                >
                                    {{ item.value }}
                                </Badge>
                                <template v-else>{{ item.value }}</template>
                            </td>
                        </tr>
                        <tr v-if="pattern.description" class="border-t border-gray-100">
                            <th class="w-48 py-2 pr-4 align-top text-xs font-medium text-gray-900">
                                Deskripsi
                            </th>
                            <td class="py-2 text-sm text-gray-700">{{ pattern.description }}</td>
                        </tr>
                    </tbody>
                </table>
            </Card>

            <Card>
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Urutan Siklus</h3>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ pattern.details.length }} hari dari siklus {{ pattern.cycle_length }} hari.
                        </p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-md border border-gray-200">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Hari</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Shift</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Kode</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Jam</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Status Shift</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="detail in pattern.details"
                                :key="detail.day_order"
                                class="border-t border-gray-100 odd:bg-white even:bg-gray-50/60"
                            >
                                <td class="px-4 py-2.5 text-gray-600">Hari {{ detail.day_order }}</td>
                                <td class="px-4 py-2.5 text-gray-700">{{ detail.shift_name ?? '-' }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ detail.shift_code ?? '-' }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ detail.shift_time ?? '-' }}</td>
                                <td class="px-4 py-2.5">
                                    <Badge :variant="detail.shift_is_active ? 'emerald' : 'gray'">
                                        {{ detail.shift_is_active ? 'Aktif' : 'Nonaktif' }}
                                    </Badge>
                                </td>
                            </tr>
                            <tr v-if="pattern.details.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Belum ada detail siklus.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>
    </AdminLayout>
</template>
