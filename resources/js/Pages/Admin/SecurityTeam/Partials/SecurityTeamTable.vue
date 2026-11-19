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
        ? ' Regu yang nonaktif tidak dapat menerima anggota baru atau digunakan untuk periode jadwal baru, tetapi riwayat dan jadwal lama tidak berubah.'
        : '';

    if (confirm(`${action} regu "${row.name}"?${note}`)) {
        router.patch(`/admin/security-team/${row.id}/toggle`, {}, { preserveScroll: true });
    }
}

function destroy(row) {
    if (confirm(`Hapus regu "${row.name}"? Regu yang sudah memiliki riwayat anggota atau periode jadwal tidak dapat dihapus.`)) {
        router.delete(`/admin/security-team/${row.id}`);
    }
}

function goToDetail(row) {
    router.visit(`/admin/security-team/${row.id}`);
}
</script>

<template>
    <DataTable :has-rows="rows.length > 0">
        <template #head>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Kode</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Nama Regu</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Pos / Unit</th>
            <th class="px-2 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Anggota</th>
            <th class="px-2 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Riwayat</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
            <th class="px-2 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Aksi</th>
        </template>

        <tr
            v-for="row in rows"
            :key="row.id"
            class="cursor-pointer"
            @click="goToDetail(row)"
        >
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.code }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">
                <span class="font-medium text-gray-800">{{ row.name }}</span>
                <span v-if="row.description" class="mt-0.5 block max-w-xs truncate text-xs text-gray-500">
                    {{ row.description }}
                </span>
            </td>
            <td class="px-2 py-3.5 text-sm text-gray-600">
                {{ row.post_name }}
                <span class="mt-0.5 block text-xs text-gray-500">{{ row.unit_name }}</span>
            </td>
            <td class="px-2 py-3.5 text-center text-sm text-gray-600">
                {{ row.active_members_count }}
                <span class="block text-xs text-gray-400">/ {{ row.members_count }} total</span>
            </td>
            <td class="px-2 py-3.5 text-center text-sm text-gray-600">
                {{ row.schedule_periods_count }}
                <span class="block text-xs text-gray-400">periode</span>
            </td>
            <td class="px-2 py-3.5">
                <Badge :variant="row.is_active ? 'emerald' : 'gray'">{{ row.status }}</Badge>
            </td>
            <td class="px-2 py-3.5 text-center">
                <div class="flex flex-wrap items-center justify-center gap-1">
                    <Link
                        :href="`/admin/security-team/${row.id}/edit`"
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
                        class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs transition"
                        :class="row.is_referenced
                            ? 'cursor-not-allowed text-gray-300'
                            : 'text-red-600 hover:bg-red-50 hover:text-red-700'"
                        :disabled="row.is_referenced"
                        :title="row.is_referenced
                            ? 'Regu sudah memiliki riwayat anggota atau periode jadwal sehingga tidak dapat dihapus. Nonaktifkan saja.'
                            : 'Hapus regu'"
                        @click.stop="!row.is_referenced && destroy(row)"
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
