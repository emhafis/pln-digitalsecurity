<script setup>
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import DataTable from '@/Components/Admin/DataTable.vue';
import Badge from '@/Components/Admin/Badge.vue';
import Pagination from '@/Components/Admin/Pagination.vue';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    meta: { type: Object, default: null },
});

defineEmits(['page-change']);

const groupedRows = computed(() => {
    const result = [];
    const rows = props.rows || [];
    for (let i = 0; i < rows.length; i++) {
        if (i > 0 && rows[i].name === rows[i - 1].name) {
            result.push({ ...rows[i], _nameRowspan: 0 });
        } else {
            let span = 1;
            for (let j = i + 1; j < rows.length && rows[j].name === rows[i].name; j++) span++;
            result.push({ ...rows[i], _nameRowspan: span });
        }
    }
    return result;
});

function statusVariant(status) {
    if (status === 'active') {
        return 'emerald';
    }

    if (status === 'completed') {
        return 'blue';
    }

    return 'gray';
}

function goToDetail(row) {
    router.visit(`/admin/schedule-period/${row.id}`);
}
</script>

<template>
    <DataTable :has-rows="rows.length > 0">
        <template #head>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Nama Periode</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Unit</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Pos</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Regu</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Tanggal</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Anggota</th>
            <th class="px-2 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
            <th class="px-2 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Aksi</th>
        </template>

        <tr
            v-for="row in groupedRows"
            :key="row.id"
            class="cursor-pointer hover:bg-gray-50/60"
            @click="goToDetail(row)"
        >
            <td
                v-if="row._nameRowspan"
                :rowspan="row._nameRowspan"
                class="px-2 py-3.5 align-top text-sm font-medium text-gray-800"
            >
                {{ row.name }}
            </td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.unit_name }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.post_name }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.team_name }}</td>
            <td class="px-2 py-3.5 text-sm text-gray-600">
                {{ row.start_date_label }} - {{ row.end_date_label }}
            </td>
            <td class="px-2 py-3.5 text-sm text-gray-600">{{ row.members_count }} anggota</td>
            <td class="px-2 py-3.5">
                <Badge :variant="statusVariant(row.status)">{{ row.status_label }}</Badge>
            </td>
            <td class="px-2 py-3.5 text-center">
                <div class="flex items-center justify-center gap-1">
                    <p v-if="row.schedules_count > 0" class="mr-1 hidden text-[11px] text-amber-600 sm:block">
                        {{ row.schedules_count }} jadwal
                    </p>
                    <Link
                        :href="`/admin/schedule-period/${row.id}/edit`"
                        class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-gray-600 transition hover:bg-gray-100 hover:text-gray-900"
                        @click.stop
                    >
                        Edit
                    </Link>
                </div>
            </td>
        </tr>

        <template #pagination>
            <Pagination v-if="meta" :meta="meta" @change="$emit('page-change', $event)" />
        </template>
    </DataTable>
</template>
