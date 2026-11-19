<script setup>
import { router } from '@inertiajs/vue3';
import DataTable from '@/Components/Admin/DataTable.vue';
import Badge from '@/Components/Admin/Badge.vue';
import Pagination from '@/Components/Admin/Pagination.vue';

defineProps({
    rows: { type: Array, default: () => [] },
    meta: { type: Object, default: null },
});

defineEmits(['page-change']);

const roleLabels = {
    security: 'Security',
    admin: 'Admin',
    superadmin: 'Superadmin',
};

const roleVariants = {
    security: 'blue',
    admin: 'emerald',
    superadmin: 'amber',
};

function goToEdit(row) {
    router.visit(`/admin/user/${row.id}/edit`);
}

function destroy(row) {
    if (confirm(`Hapus user "${row.username}"?`)) {
        router.delete(`/admin/user/${row.id}`);
    }
}
</script>

<template>
    <DataTable :has-rows="rows.length > 0">
        <template #head>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Username</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Nama</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Unit</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Role</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
            <th class="px-2 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Aksi</th>
        </template>

        <tr
            v-for="row in rows"
            :key="row.id"
            class="cursor-pointer hover:bg-gray-50/60"
            @click="goToEdit(row)"
        >
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.username }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.name }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.unit_name }}</td>
            <td class="px-2 py-3.5">
                <Badge :variant="roleVariants[row.role] ?? 'gray'">{{ roleLabels[row.role] ?? row.role }}</Badge>
            </td>
            <td class="px-2 py-3.5">
                <Badge :variant="row.status === 'Aktif' ? 'emerald' : 'gray'">{{ row.status }}</Badge>
            </td>
            <td class="px-2 py-3.5 text-center">
                <div class="flex items-center justify-center gap-1">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-gray-600 transition hover:bg-gray-100 hover:text-gray-900"
                        @click.stop="goToEdit(row)"
                    >
                        Edit
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
