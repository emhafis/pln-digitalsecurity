<script setup>
import { Link, router } from '@inertiajs/vue3';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';

defineProps({
    rows: { type: Array, default: () => [] },
    meta: { type: Object, default: null },
});

defineEmits(['page-change']);

function goToDetail(row) {
    router.visit(`/admin/hydrant/${row.id}`);
}

function toggleActive(row) {
    const action = row.is_active ? 'menonaktifkan' : 'mengaktifkan';
    if (confirm(`Yakin ${action} Hydrant "${row.hydrant_code}"?`)) {
        router.post(`/admin/hydrant/${row.id}/toggle-active`, {}, { preserveScroll: true });
    }
}
</script>

<template>
    <DataTable :has-rows="rows.length > 0">
        <template #head>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Kode</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Nama</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Jenis</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Merek</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Model</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Lokasi</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Unit</th>
            <th class="px-2 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
            <th class="px-2 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Aksi</th>
        </template>

        <tr
            v-for="row in rows"
            :key="row.id"
            class="cursor-pointer hover:bg-gray-50/60"
            @click="goToDetail(row)"
        >
            <td class="px-2 py-3.5 text-sm font-medium text-gray-700">{{ row.hydrant_code }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.name }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.hydrant_type_label }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.brand }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.model }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.placement }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.unit_name }}</td>
            <td class="px-2 py-3.5 text-center">
                <span
                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="row.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600'"
                >
                    {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
            </td>
            <td class="px-2 py-3.5 text-center">
                <div class="flex items-center justify-center gap-1">
                    <Link
                        :href="`/admin/hydrant/${row.id}/edit`"
                        class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-gray-600 transition hover:bg-gray-100 hover:text-gray-900"
                        @click.stop
                    >
                        Edit
                    </Link>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs transition"
                        :class="row.is_active ? 'text-amber-700 hover:bg-amber-50' : 'text-green-700 hover:bg-green-50'"
                        @click.stop="toggleActive(row)"
                    >
                        {{ row.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                    </button>
                </div>
            </td>
        </tr>

        <template #pagination>
            <Pagination v-if="meta" :meta="meta" @change="$emit('page-change', $event)" />
        </template>
    </DataTable>
</template>
