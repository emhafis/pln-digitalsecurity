<script setup>
import DataTable from '@/Components/Admin/DataTable.vue';
import Badge from '@/Components/Admin/Badge.vue';

defineProps({
    rows: { type: Array, default: () => [] },
});

defineEmits(['select']);
</script>

<template>
    <DataTable :has-rows="rows.length > 0" empty-title="Belum ada jadwal" empty-description="Tidak ada jadwal pada bulan dan filter yang dipilih.">
        <template #head>
            <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Tanggal</th>
            <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Satpam</th>
            <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Regu / Pos</th>
            <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Shift</th>
            <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Sumber</th>
            <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Catatan</th>
            <th class="px-3 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Terakhir Diubah</th>
            <th class="px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wider text-gray-500">Aksi</th>
        </template>

        <tr v-for="row in rows" :key="row.id" class="cursor-pointer hover:bg-gray-50/60" @click="$emit('select', row)">
            <td class="px-3 py-2.5 text-sm text-gray-700">{{ row.date_label }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-700">
                <p class="font-medium text-gray-800">{{ row.full_name }}</p>
                <p v-if="row.registration_number" class="text-xs text-gray-500">{{ row.registration_number }}</p>
            </td>
            <td class="px-3 py-2.5 text-sm text-gray-600">
                {{ row.team_name ?? '-' }}
                <span class="block text-xs text-gray-500">{{ row.post_name ?? '-' }}</span>
            </td>
            <td class="px-3 py-2.5 text-sm text-gray-700">
                <span class="flex items-center gap-1.5">
                    <span class="inline-block h-2 w-2 rounded-full" :style="{ backgroundColor: row.shift_color || '#94a3b8' }" />
                    {{ row.shift_name ?? '-' }}
                    <span v-if="row.is_off" class="rounded bg-emerald-50 px-1 text-[10px] font-medium text-emerald-700">Libur</span>
                </span>
                <span v-if="row.shift_time" class="block text-xs text-gray-500">{{ row.shift_time }}</span>
            </td>
            <td class="px-3 py-2.5">
                <Badge :variant="row.source === 'adjustment' ? 'amber' : 'gray'">
                    {{ row.source === 'adjustment' ? 'Penyesuaian' : 'Dihasilkan Sistem' }}
                </Badge>
            </td>
            <td class="px-3 py-2.5 text-xs text-gray-600">{{ row.notes ?? '-' }}</td>
            <td class="px-3 py-2.5 text-xs text-gray-600">
                <template v-if="row.updated_by_name">
                    {{ row.updated_by_name }}
                    <span class="block text-gray-500">{{ row.updated_at }}</span>
                </template>
                <template v-else>-</template>
            </td>
            <td class="px-3 py-2.5 text-center">
                <div class="flex items-center justify-center gap-1">
                    <button
                        type="button"
                        class="rounded-md px-2 py-1 text-xs text-gray-600 transition hover:bg-gray-100 hover:text-gray-900"
                        @click.stop="$emit('select', row)"
                    >
                        Detail
                    </button>
                    <a
                        v-if="row.can_edit"
                        :href="`/admin/schedule-period/${row.period_id}`"
                        class="rounded-md px-2 py-1 text-xs text-blue-600 transition hover:bg-blue-50"
                        @click.stop
                    >
                        Sesuaikan
                    </a>
                    <span v-else class="px-1 text-[10px] text-gray-400">Terkunci</span>
                </div>
            </td>
        </tr>
    </DataTable>
</template>
