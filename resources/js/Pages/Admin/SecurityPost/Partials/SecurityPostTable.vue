<script setup>
import { Link, router } from '@inertiajs/vue3';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';

defineProps({
    rows: { type: Array, default: () => [] },
    meta: { type: Object, default: null },
});

defineEmits(['page-change']);

function destroy(row) {
    if (confirm(`Hapus post keamanan "${row.name}"?`)) {
        router.delete(`/admin/security-post/${row.id}`);
    }
}

function goToDetail(row) {
    router.visit(`/admin/security-post/${row.id}`);
}
</script>

<template>
    <DataTable :has-rows="rows.length > 0">
        <template #head>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Kode</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Nama Pos</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Alamat</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Unit</th>
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
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.address }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.unit_name }}</td>
            <td class="px-2 py-3.5 text-center">
                <div class="flex items-center justify-center gap-1">
                    <Link
                        :href="`/admin/security-post/${row.id}/edit`"
                        class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-gray-600 transition hover:bg-gray-100 hover:text-gray-900"
                        @click.stop
                    >
                        Edit
                    </Link>
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
