<script setup>
import { Link, router } from '@inertiajs/vue3';
import DataTable from '@/Components/Admin/DataTable.vue';
import Badge from '@/Components/Admin/Badge.vue';
import Pagination from '@/Components/Admin/Pagination.vue';

defineProps({
    rows: { type: Array, default: () => [] },
    meta: { type: Object, default: null },
    canManage: { type: Boolean, default: false },
});

defineEmits(['page-change']);

function destroy(row) {
    if (confirm(`Hapus unit "${row.name}"?`)) {
        router.delete(`/admin/unit/${row.id}`);
    }
}

function goToDetail(row) {
    router.visit(`/admin/unit/${row.id}`);
}
</script>

<template>
    <DataTable :has-rows="rows.length > 0">
        <template #head>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Kode</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Nama Unit</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Level</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
            <th v-if="canManage" class="px-2 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Aksi</th>
        </template>

        <tr
            v-for="row in rows"
            :key="row.id"
            class="cursor-pointer hover:bg-gray-50/60"
            @click="goToDetail(row)"
        >
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.code }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.name }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.level }}</td>
            <td class="px-2 py-3.5">
                <Badge :variant="row.status === 'Aktif' ? 'emerald' : 'gray'">{{ row.status }}</Badge>
            </td>
            <td v-if="canManage" class="px-2 py-3.5 text-center">
                <div class="flex items-center justify-center gap-1">
                    <Link
                        :href="`/admin/unit/${row.id}/edit`"
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
