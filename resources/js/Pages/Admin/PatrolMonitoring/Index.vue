<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Badge from '@/Components/Admin/Badge.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import Pagination from '@/Components/Admin/Pagination.vue';
import SearchSelect from '@/Components/Admin/SearchSelect.vue';
import Select from '@/Components/Admin/Select.vue';
import TextInput from '@/Components/Admin/TextInput.vue';

const props = defineProps({
    sessions: { type: Array, default: () => [] },
    meta: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    posts: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
    requires_unit_selection: { type: Boolean, default: false },
});

const filters = ref({ ...props.filters });

const statusVariants = {
    waiting: 'amber',
    pending: 'amber',
    not_started: 'gray',
    missed: 'red',
    active: 'blue',
    completed: 'emerald',
    incomplete: 'red',
};

const sortColumns = [
    { value: 'work_date', label: 'Tanggal' },
    { value: 'unit', label: 'Unit' },
    { value: 'status', label: 'Status' },
    { value: 'started_at', label: 'Waktu Mulai' },
];

const dirOptions = [
    { value: 'desc', label: 'Terbaru' },
    { value: 'asc', label: 'Terlama' },
];

const visiblePosts = computed(() => {
    if (!filters.value.unit_id) return props.posts;
    return props.posts.filter((p) => String(p.unit_id) === String(filters.value.unit_id));
});

const visibleTeams = computed(() => {
    let list = props.teams;
    if (filters.value.unit_id) list = list.filter((t) => String(t.unit_id) === String(filters.value.unit_id));
    if (filters.value.security_post_id) list = list.filter((t) => String(t.security_post_id) === String(filters.value.security_post_id));
    return list;
});

const visibleShifts = computed(() => {
    if (!filters.value.unit_id) return props.shifts;
    return props.shifts.filter((s) => String(s.unit_id) === String(filters.value.unit_id));
});

function onUnitChange() {
    filters.value.security_post_id = '';
    filters.value.security_team_id = '';
    filters.value.shift_id = '';
}

function onPostChange() {
    filters.value.security_team_id = '';
}

function queryParams(page = null) {
    const params = {
        date_from: filters.value.date_from || undefined,
        date_to: filters.value.date_to || undefined,
        unit_id: filters.value.unit_id || undefined,
        security_post_id: filters.value.security_post_id || undefined,
        security_team_id: filters.value.security_team_id || undefined,
        shift_id: filters.value.shift_id || undefined,
        status: filters.value.status || undefined,
        sort: filters.value.sort || undefined,
        dir: filters.value.dir || undefined,
    };

    if (page) params.page = page;

    return Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    );
}

function applyFilter(page = null) {
    router.get('/admin/patrol-session', queryParams(page), { preserveState: true, preserveScroll: true });
}

function resetFilter() {
    filters.value = {
        date_from: '',
        date_to: '',
        unit_id: '',
        security_post_id: '',
        security_team_id: '',
        shift_id: '',
        status: '',
        sort: 'work_date',
        dir: 'desc',
    };
    const params = {
        unit_id: filters.value.unit_id || undefined,
        security_post_id: filters.value.security_post_id || undefined,
        security_team_id: filters.value.security_team_id || undefined,
        shift_id: filters.value.shift_id || undefined,
        status: filters.value.status || undefined,
        sort: filters.value.sort || undefined,
        dir: filters.value.dir || undefined,
    };
    router.get('/admin/patrol-session', Object.fromEntries(
        Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined),
    ), { preserveState: true, preserveScroll: true });
}

function goToPage(page) {
    applyFilter(page);
}

function formatTeamLabel(session) {
    return session.team_code ? `${session.team_code} - ${session.team_name}` : session.team_name;
}
</script>

<template>
    <AdminLayout title="Monitoring Patroli">
        <div class="space-y-6">
            <PageHeader
                title="Monitoring Patroli"
                description="Pantau pelaksanaan patroli Satpam berdasarkan jadwal, unit, dan status sesi."
                :breadcrumb="[
                    { label: 'Penjadwalan' },
                    { label: 'Monitoring Patroli' },
                ]"
            />

            <FlashMessage />

            <Card title="Filter">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Tanggal Mulai</label>
                        <TextInput v-model="filters.date_from" type="date" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Tanggal Selesai</label>
                        <TextInput v-model="filters.date_to" type="date" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Unit</label>
                        <SearchSelect
                            v-model="filters.unit_id"
                            :options="units"
                            placeholder="Pilih Unit"
                            none-label="Semua Unit"
                            search-placeholder="Cari unit..."
                            @update:model-value="onUnitChange"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Pos Jaga</label>
                        <SearchSelect
                            v-model="filters.security_post_id"
                            :options="visiblePosts"
                            placeholder="Semua Pos"
                            none-label="Semua Pos"
                            search-placeholder="Cari pos..."
                            @update:model-value="onPostChange"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Regu</label>
                        <SearchSelect
                            v-model="filters.security_team_id"
                            :options="visibleTeams"
                            placeholder="Semua Regu"
                            none-label="Semua Regu"
                            search-placeholder="Cari regu..."
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Shift</label>
                        <SearchSelect
                            v-model="filters.shift_id"
                            :options="visibleShifts"
                            placeholder="Semua Shift"
                            none-label="Semua Shift"
                            search-placeholder="Cari shift..."
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Status</label>
                        <Select v-model="filters.status" :options="statuses" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Urutkan</label>
                            <Select v-model="filters.sort" :options="sortColumns" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Arah</label>
                            <Select v-model="filters.dir" :options="dirOptions" />
                        </div>
                    </div>
                    <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-4">
                        <Button @click="applyFilter()">Terapkan</Button>
                        <Button variant="ghost" @click="resetFilter">Reset</Button>
                    </div>
                </div>
            </Card>

            <div
                v-if="requires_unit_selection"
                class="rounded-md border border-amber-200 bg-amber-50 px-4 py-6 text-center text-sm text-amber-700"
            >
                Silakan pilih unit untuk menampilkan data monitoring patroli.
            </div>

            <DataTable
                v-else
                :has-rows="sessions.length > 0"
                empty-title="Belum ada data sesi patroli"
                empty-description="Sesi patroli yang sesuai filter akan ditampilkan di sini."
            >
                <template #head>
                    <th>Tanggal</th>
                    <th>Unit</th>
                    <th>Pos Jaga</th>
                    <th>Regu</th>
                    <th>Shift dan Jam Kerja</th>
                    <th>Status</th>
                    <th>Progres</th>
                    <th>Mulai</th>
                    <th>Selesai</th>
                    <th class="text-right">Aksi</th>
                </template>

                <tr v-for="session in sessions" :key="session.id">
                    <td class="whitespace-nowrap text-sm text-gray-700">{{ session.work_date_label }}</td>
                    <td class="min-w-[140px] text-sm text-gray-800">
                        {{ session.unit_name }}
                        <span v-if="session.unit_code" class="text-xs text-gray-500">({{ session.unit_code }})</span>
                    </td>
                    <td class="min-w-[140px] text-sm text-gray-800">
                        {{ session.post_name }}
                    </td>
                    <td class="min-w-[120px] text-sm text-gray-800">{{ formatTeamLabel(session) }}</td>
                    <td class="min-w-[150px]">
                        <p class="text-gray-800">{{ session.shift_name }}</p>
                        <p class="text-xs text-gray-500">{{ session.shift_time }}</p>
                    </td>
                    <td>
                        <Badge :variant="session.status_variant ?? statusVariants[session.display_status] ?? 'gray'">{{ session.status_label }}</Badge>
                    </td>
                    <td class="min-w-[150px]">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium text-gray-700">{{ session.scanned_points }}/{{ session.required_points }}</span>
                            <div class="h-2 w-20 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full bg-emerald-500" :style="{ width: `${session.progress}%` }"></div>
                            </div>
                        </div>
                    </td>
                    <td class="whitespace-nowrap text-sm text-gray-700">{{ session.started_at ?? '-' }}</td>
                    <td class="whitespace-nowrap text-sm text-gray-700">{{ session.ended_at ?? '-' }}</td>
                    <td class="whitespace-nowrap text-right">
                        <Link
                            :href="`/admin/patrol-session/${session.id}`"
                            class="inline-flex items-center rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                        >
                            Detail
                        </Link>
                    </td>
                </tr>

                <template #pagination>
                    <Pagination :meta="meta" @change="goToPage" />
                </template>
            </DataTable>
        </div>
    </AdminLayout>
</template>
