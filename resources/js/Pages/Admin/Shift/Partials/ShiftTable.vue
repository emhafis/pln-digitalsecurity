<script setup>
import { Link, router } from '@inertiajs/vue3';
import DataTable from '@/Components/Admin/DataTable.vue';
import Badge from '@/Components/Admin/Badge.vue';
import Pagination from '@/Components/Admin/Pagination.vue';

defineProps({
    rows: { type: Array, default: () => [] },
    meta: { type: Object, default: null },
});

defineEmits(['page-change']);

function timeRange(row) {
    if (row.type === 'day_off') {
        return 'Libur';
    }

    if (!row.start_time || !row.end_time) {
        return '-';
    }

    return `${row.start_time} - ${row.end_time}${row.end_day_offset ? ' (+1 hari)' : ''}`;
}

function toggleActive(row) {
    const action = row.is_active ? 'Nonaktifkan' : 'Aktifkan';
    const note = row.is_active
        ? ' Shift yang nonaktif tidak akan digunakan untuk jadwal baru, tetapi jadwal dan histori lama tidak berubah.'
        : '';

    if (confirm(`${action} shift "${row.name}"?${note}`)) {
        router.patch(`/admin/shift/${row.id}/toggle`, {}, { preserveScroll: true });
    }
}

function destroy(row) {
    if (confirm(`Hapus shift "${row.name}"? Shift yang sudah digunakan oleh pola rotasi atau jadwal tidak dapat dihapus.`)) {
        router.delete(`/admin/shift/${row.id}`);
    }
}

function goToDetail(row) {
    router.visit(`/admin/shift/${row.id}`);
}
</script>

<template>
    <DataTable :has-rows="rows.length > 0">
        <template #head>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Kode</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Nama Shift</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Unit</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Jam</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
            <th class="px-2 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Aksi</th>
        </template>

        <tr
            v-for="row in rows"
            :key="row.id"
            class="cursor-pointer hover:bg-gray-50/60"
            @click="goToDetail(row)"
        >
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.code }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.name }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.unit_name }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ timeRange(row) }}</td>
            <td class="px-2 py-3.5">
                <Badge :variant="row.is_active ? 'emerald' : 'gray'">{{ row.status }}</Badge>
            </td>
            <td class="px-2 py-3.5 text-center">
                <div class="flex items-center justify-center gap-1">
                    <Link
                        :href="`/admin/shift/${row.id}/edit`"
                        class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-gray-600 transition hover:bg-gray-100 hover:text-gray-900"
                        @click.stop
                    >
                        Edit
                    </Link>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs transition"
                        :class="row.is_active
                            ? 'text-amber-600 hover:bg-amber-50 hover:text-amber-700'
                            : 'text-emerald-600 hover:bg-emerald-50 hover:text-emerald-700'"
                        @click.stop="toggleActive(row)"
                    >
                        {{ row.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-red-600 transition hover:bg-red-50 hover:text-red-700"
                        @click.stop="destroy(row)"
                    >
                        Hapus
                    </button>
                </div>
            </td>
        </tr>

        <template #pagination>
            <Pagination v-if="meta" :meta="meta" @change="$emit('page-change', $event)" />
        </template>
    </DataTable>
</template>
