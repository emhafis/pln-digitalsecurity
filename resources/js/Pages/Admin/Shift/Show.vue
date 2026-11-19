<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Badge from '@/Components/Admin/Badge.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';

const props = defineProps({
    shift: { type: Object, required: true },
});

const timeRange = computed(() => {
    if (props.shift.type === 'day_off') {
        return 'Libur';
    }

    if (!props.shift.start_time || !props.shift.end_time) {
        return '-';
    }

    return `${props.shift.start_time} - ${props.shift.end_time}${props.shift.end_day_offset ? ' (+1 hari)' : ''}`;
});

const details = computed(() => [
    { label: 'Kode Shift', value: props.shift.code },
    { label: 'Nama Shift', value: props.shift.name },
    { label: 'Unit', value: props.shift.unit_name },
    { label: 'Jam Kerja', value: timeRange.value },
    { label: 'Lintas Tengah Malam', value: props.shift.end_day_offset ? 'Ya' : 'Tidak' },
    { label: 'Status', value: props.shift.status },
    { label: 'Dibuat', value: props.shift.created_at },
    { label: 'Diperbarui', value: props.shift.updated_at },
]);

function toggleActive() {
    const action = props.shift.is_active ? 'Nonaktifkan' : 'Aktifkan';
    const note = props.shift.is_active
        ? ' Shift yang nonaktif tidak akan digunakan untuk jadwal baru, tetapi jadwal dan histori lama tidak berubah.'
        : '';

    if (confirm(`${action} shift "${props.shift.name}"?${note}`)) {
        router.patch(`/admin/shift/${props.shift.id}/toggle`);
    }
}

function destroy() {
    if (confirm(`Hapus shift "${props.shift.name}"? Shift yang sudah digunakan oleh pola rotasi atau jadwal tidak dapat dihapus.`)) {
        router.delete(`/admin/shift/${props.shift.id}`);
    }
}
</script>

<template>
    <AdminLayout title="Detail Shift Kerja">
        <div class="space-y-6">
            <PageHeader
                title="Shift Kerja"
                :breadcrumb="[
                    { label: 'Shift Kerja', href: '/admin/shift' },
                    { label: 'Detail' },
                ]"
            >
                <template #actions>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-xs transition"
                        :class="props.shift.is_active
                            ? 'bg-amber-500 text-white hover:bg-amber-600'
                            : 'bg-emerald-600 text-white hover:bg-emerald-700'"
                        @click="toggleActive"
                    >
                        {{ props.shift.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-red-600 px-4 py-2 text-xs text-white transition hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2"
                        @click="destroy"
                    >
                        Hapus
                    </button>
                    <Link
                        :href="`/admin/shift/${props.shift.id}/edit`"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs text-white transition hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                    >
                        Edit
                    </Link>
                    <Link
                        href="/admin/shift"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Kembali
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <Card>
                <table class="mt-4 w-full text-left">
                    <tbody>
                        <tr
                            v-for="item in details"
                            :key="item.label"
                            class="border-b border-gray-100 last:border-0"
                        >
                            <th class="w-48 py-2 pr-4 align-top text-xs font-medium text-gray-900">
                                {{ item.label }}
                            </th>
                            <td class="py-2 text-sm text-gray-700">
                                <Badge
                                    v-if="item.label === 'Status'"
                                    :variant="props.shift.is_active ? 'emerald' : 'gray'"
                                >
                                    {{ item.value }}
                                </Badge>
                                <template v-else>{{ item.value }}</template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </Card>
        </div>
    </AdminLayout>
</template>
