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

function destroy(row) {
    if (confirm(`Hapus pengumuman "${row.title}"?`)) {
        router.delete(`/admin/announcements/${row.id}`, { preserveScroll: true });
    }
}
</script>

<template>
    <DataTable :has-rows="rows.length > 0" empty-title="Belum ada pengumuman" empty-description="Pengumuman yang dibuat akan tampil di sini dan di aplikasi security.">
        <template #head>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Pengumuman</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Terbit</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Dibuat Oleh</th>
            <th class="px-2 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Aksi</th>
        </template>

        <tr v-for="row in rows" :key="row.id" class="hover:bg-gray-50/60">
            <td class="px-2 py-3.5 text-sm text-gray-700">
                <div class="flex items-center gap-3">
                    <img v-if="row.image_url" :src="row.image_url" :alt="row.title" class="h-12 w-12 shrink-0 rounded-md border border-gray-200 object-cover" />
                    <div v-else class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md border border-gray-200 bg-gray-50 text-[10px] text-gray-400">No Img</div>
                    <div class="min-w-0">
                        <p class="font-medium text-gray-900">{{ row.title }}</p>
                        <p class="mt-0.5 line-clamp-2 text-xs text-gray-500">{{ row.excerpt }}</p>
                    </div>
                </div>
            </td>
            <td class="px-2 py-3.5">
                <Badge :variant="row.is_active ? 'emerald' : 'gray'">{{ row.status }}</Badge>
            </td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.published_at || '-' }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.creator_name }}</td>
            <td class="px-2 py-3.5 text-center">
                <div class="flex items-center justify-center gap-1">
                    <Link
                        :href="`/admin/announcements/${row.id}/edit`"
                        class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-gray-600 transition hover:bg-gray-100 hover:text-gray-900"
                    >
                        Edit
                    </Link>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-red-600 transition hover:bg-red-50 hover:text-red-700"
                        @click="destroy(row)"
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
