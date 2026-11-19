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

function toggleActive(row) {
    const action = row.is_active ? 'Nonaktifkan' : 'Aktifkan';
    const note = row.is_active
        ? ' Pola yang nonaktif tidak akan digunakan untuk periode jadwal baru, tetapi jadwal dan histori lama tidak berubah.'
        : '';

    if (confirm(`${action} pola rotasi "${row.name}"?${note}`)) {
        router.patch(`/admin/schedule-pattern/${row.id}/toggle`, {}, { preserveScroll: true });
    }
}

function destroy(row) {
    if (confirm(`Hapus pola rotasi "${row.name}"? Pola yang sudah digunakan oleh periode atau jadwal tidak dapat dihapus.`)) {
        router.delete(`/admin/schedule-pattern/${row.id}`);
    }
}

function goToDetail(row) {
    router.visit(`/admin/schedule-pattern/${row.id}`);
}
</script>

<template>
    <DataTable :has-rows="rows.length > 0">
        <template #head>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Nama Pola</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Unit</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Siklus</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Shift Digunakan</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
            <th class="px-2 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Aksi</th>
        </template>

        <tr
            v-for="row in rows"
            :key="row.id"
            class="cursor-pointer hover:bg-gray-50/60"
            @click="goToDetail(row)"
        >
            <td class="px-2 py-3.5 text-sm text-gray-600">
                <p class="font-medium text-gray-800">{{ row.name }}</p>
                <p v-if="row.description" class="mt-0.5 max-w-md truncate text-xs text-gray-400">
                    {{ row.description }}
                </p>
            </td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.unit_name }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.cycle_length }} hari</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.shifts_count }} shift</td>
            <td class="px-2 py-3.5">
                <Badge :variant="row.is_active ? 'emerald' : 'gray'">{{ row.status }}</Badge>
            </td>
            <td class="px-2 py-3.5 text-center">
                <div class="flex items-center justify-center gap-1">
                    <Link
                        :href="`/admin/schedule-pattern/${row.id}/edit`"
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
