<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { CheckIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    meta: { type: Object, default: null },
});

defineEmits(['page-change', 'delete']);

const openStatusId = ref(null);
const updatingId = ref(null);
const dropdownStyle = ref({});

const statusOptions = [
    { value: 'draft', label: 'Draft', dot: 'bg-gray-400', badge: 'bg-gray-100 text-gray-700 ring-gray-200' },
    { value: 'active', label: 'Aktif', dot: 'bg-emerald-500', badge: 'bg-emerald-50 text-emerald-700 ring-emerald-200' },
    { value: 'completed', label: 'Selesai', dot: 'bg-blue-500', badge: 'bg-blue-50 text-blue-700 ring-blue-200' },
];

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

const openRow = computed(() => props.rows.find((row) => row.id === openStatusId.value) || null);

function statusOption(status) {
    return statusOptions.find((item) => item.value === status) || statusOptions[0];
}

function toggleStatus(row, event) {
    if (openStatusId.value === row.id) {
        openStatusId.value = null;
        return;
    }

    const rect = event.currentTarget.getBoundingClientRect();
    const menuWidth = 144;
    const menuHeight = 124;
    const spaceBelow = window.innerHeight - rect.bottom;
    const openUp = spaceBelow < menuHeight && rect.top > menuHeight;
    const left = Math.min(rect.left, window.innerWidth - menuWidth - 8);

    dropdownStyle.value = {
        left: `${Math.max(8, left)}px`,
        top: openUp ? `${rect.top - menuHeight - 6}px` : `${rect.bottom + 6}px`,
    };
    openStatusId.value = row.id;
}

function closeStatus(event) {
    if (event?.target?.closest?.('[data-status-dropdown]')) return;
    if (event?.target?.closest?.('[data-status-dropdown-menu]')) return;
    openStatusId.value = null;
}

function closeOnScroll(event) {
    if (event?.target?.closest?.('[data-status-dropdown-menu]')) return;
    openStatusId.value = null;
}

function updateStatus(row, status) {
    if (row.status === status || updatingId.value === row.id) {
        openStatusId.value = null;
        return;
    }

    updatingId.value = row.id;
    router.patch(`/admin/schedule-period/${row.id}/status`, { status }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            updatingId.value = null;
            openStatusId.value = null;
        },
    });
}

function goToDetail(row) {
    router.visit(`/admin/schedule-period/${row.id}`);
}

onMounted(() => {
    document.addEventListener('click', closeStatus);
    document.addEventListener('scroll', closeOnScroll, true);
    window.addEventListener('resize', closeStatus);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeStatus);
    document.removeEventListener('scroll', closeOnScroll, true);
    window.removeEventListener('resize', closeStatus);
});
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
            <td class="px-2 py-3.5" @click.stop>
                <div class="inline-block" data-status-dropdown>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset transition hover:brightness-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 disabled:cursor-wait disabled:opacity-60"
                        :class="statusOption(row.status).badge"
                        :disabled="updatingId === row.id"
                        @click="toggleStatus(row, $event)"
                    >
                        <span class="h-1.5 w-1.5 rounded-full" :class="statusOption(row.status).dot"></span>
                        <span>{{ statusOption(row.status).label }}</span>
                        <ChevronDownIcon class="h-3.5 w-3.5 opacity-70 transition-transform" :class="{ 'rotate-180': openStatusId === row.id }" />
                    </button>
                </div>
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
                    <button
                        v-if="row.status !== 'completed'"
                        type="button"
                        class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-red-600 transition hover:bg-red-50 hover:text-red-700"
                        @click.stop="$emit('delete', row)"
                    >
                        Hapus
                    </button>
                    <span
                        v-else
                        class="inline-flex items-center justify-center rounded-md px-2 py-1 text-xs text-gray-400"
                        title="Periode selesai/dikunci tidak dapat dihapus"
                        @click.stop
                    >
                        Terkunci
                    </span>
                </div>
            </td>
        </tr>

        <template #pagination>
            <Pagination v-if="meta" :meta="meta" @change="$emit('page-change', $event)" />
        </template>
    </DataTable>

    <Teleport to="body">
        <div
            v-if="openRow"
            data-status-dropdown-menu
            class="fixed z-[70] w-36 rounded-xl border border-gray-200 bg-white p-1 shadow-xl ring-1 ring-black/5"
            :style="dropdownStyle"
        >
            <button
                v-for="option in statusOptions"
                :key="option.value"
                type="button"
                class="flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-left text-xs transition hover:bg-gray-50"
                :class="option.value === openRow.status ? 'text-gray-900' : 'text-gray-600'"
                @click="updateStatus(openRow, option.value)"
            >
                <span class="inline-flex items-center gap-2">
                    <span class="h-1.5 w-1.5 rounded-full" :class="option.dot"></span>
                    {{ option.label }}
                </span>
                <CheckIcon v-if="option.value === openRow.status" class="h-4 w-4 text-blue-600" />
            </button>
        </div>
    </Teleport>
</template>
