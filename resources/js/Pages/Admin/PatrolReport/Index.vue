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
import SearchSelect from '@/Components/Admin/SearchSelect.vue';
import Select from '@/Components/Admin/Select.vue';
import TextInput from '@/Components/Admin/TextInput.vue';

const props = defineProps({
    summary: { type: Object, required: true },
    by_unit: { type: Array, default: () => [] },
    by_post: { type: Array, default: () => [] },
    by_team: { type: Array, default: () => [] },
    security_activity: { type: Array, default: () => [] },
    incidents: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    units: { type: Array, default: () => [] },
    posts: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
    securities: { type: Array, default: () => [] },
    filters: { type: Object, required: true },
    requires_unit_selection: { type: Boolean, default: false },
});

const filters = ref({ ...props.filters });

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
    filters.value.security_id = '';
}

function onPostChange() {
    filters.value.security_team_id = '';
}

function onScopeChange() {
    filters.value.security_id = '';
}

function queryParams() {
    const params = {
        date_from: filters.value.date_from || undefined,
        date_to: filters.value.date_to || undefined,
        unit_id: filters.value.unit_id || undefined,
        security_post_id: filters.value.security_post_id || undefined,
        security_team_id: filters.value.security_team_id || undefined,
        shift_id: filters.value.shift_id || undefined,
        status: filters.value.status || undefined,
        security_id: filters.value.security_id || undefined,
    };
    return Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined));
}

function applyFilter() {
    router.get('/admin/patrol-report', queryParams(), { preserveState: true, preserveScroll: true });
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
        security_id: '',
    };
    applyFilter();
}

function exportExcel() {
    const qs = new URLSearchParams(queryParams()).toString();
    window.location.href = '/admin/patrol-report/export' + (qs ? `?${qs}` : '');
}

const fmtPct = (v) => `${Number(v ?? 0).toFixed(2)}%`;
</script>

<template>
    <AdminLayout title="Laporan Patroli">
        <div class="space-y-6">
            <PageHeader
                title="Laporan Patroli"
                description="Rekapitulasi dan evaluasi pelaksanaan patroli berdasarkan data aktual."
                :breadcrumb="[
                    { label: 'Penjadwalan' },
                    { label: 'Laporan Patroli' },
                ]"
            >
                <template #actions>
                    <Button v-if="!requires_unit_selection" variant="secondary" @click="exportExcel">Ekspor Excel</Button>
                </template>
            </PageHeader>

            <FlashMessage />

            <Card title="Filter Laporan">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Tanggal Mulai</label>
                        <TextInput v-model="filters.date_from" type="date" @change="onScopeChange" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Tanggal Selesai</label>
                        <TextInput v-model="filters.date_to" type="date" @change="onScopeChange" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Unit</label>
                        <SearchSelect v-model="filters.unit_id" :options="units" placeholder="Pilih Unit" none-label="Semua Unit" search-placeholder="Cari unit..." @update:model-value="() => { onUnitChange(); onScopeChange(); }" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Pos Jaga</label>
                        <SearchSelect v-model="filters.security_post_id" :options="visiblePosts" placeholder="Semua Pos" none-label="Semua Pos" search-placeholder="Cari pos..." @update:model-value="() => { onPostChange(); onScopeChange(); }" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Regu</label>
                        <SearchSelect v-model="filters.security_team_id" :options="visibleTeams" placeholder="Semua Regu" none-label="Semua Regu" search-placeholder="Cari regu..." @update:model-value="onScopeChange" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Shift</label>
                        <SearchSelect v-model="filters.shift_id" :options="visibleShifts" placeholder="Semua Shift" none-label="Semua Shift" search-placeholder="Cari shift..." />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Status Sesi</label>
                        <Select v-model="filters.status" :options="statuses" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-gray-600">Satpam</label>
                        <SearchSelect v-model="filters.security_id" :options="securities" placeholder="Semua Satpam" none-label="Semua Satpam" search-placeholder="Cari satpam..." />
                    </div>
                    <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-4">
                        <Button @click="applyFilter">Terapkan</Button>
                        <Button variant="ghost" @click="resetFilter">Reset</Button>
                    </div>
                </div>
            </Card>

            <div v-if="requires_unit_selection" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-6 text-center text-sm text-amber-700">
                Silakan pilih unit untuk menampilkan laporan patroli.
            </div>

            <template v-else>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                    <Card><p class="text-xs text-gray-500">Total Sesi</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.total_sessions }}</p></Card>
                    <Card><p class="text-xs text-gray-500">Sesi Selesai</p><p class="mt-1 text-xl font-semibold text-emerald-700">{{ summary.completed_sessions }}</p></Card>
                    <Card><p class="text-xs text-gray-500">Sedang Berlangsung</p><p class="mt-1 text-xl font-semibold text-blue-700">{{ summary.active_sessions }}</p></Card>
                    <Card><p class="text-xs text-gray-500">Tidak Selesai</p><p class="mt-1 text-xl font-semibold text-red-700">{{ summary.incomplete_sessions }}</p></Card>
                    <Card><p class="text-xs text-gray-500">Belum Dimulai</p><p class="mt-1 text-xl font-semibold text-gray-700">{{ summary.not_started_sessions }}</p></Card>
                </div>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Card><p class="text-xs text-gray-500">Titik Wajib</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.total_required_points }}</p></Card>
                    <Card><p class="text-xs text-gray-500">Titik Dipindai</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ summary.total_scanned_points }}</p></Card>
                    <Card><p class="text-xs text-gray-500">Penyelesaian</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ fmtPct(summary.completion_percentage) }}</p></Card>
                    <Card><p class="text-xs text-gray-500">Total Insiden</p><p class="mt-1 text-xl font-semibold text-amber-700">{{ summary.total_incidents }}</p></Card>
                </div>

                <Card title="Rekap per Unit">
                    <DataTable :has-rows="by_unit.length > 0" empty-title="Tidak ada data" empty-description="Tidak ada rekap unit untuk filter ini.">
                        <template #head>
                            <th>Unit</th><th>Total Sesi</th><th>Selesai</th><th>Belum Selesai</th><th>Titik Wajib</th><th>Titik Dipindai</th><th>Penyelesaian</th><th>Insiden</th>
                        </template>
                        <tr v-for="row in by_unit" :key="`u-${row.unit_name}`">
                            <td class="text-sm text-gray-800">{{ row.unit_name }}</td>
                            <td>{{ row.total_sessions }}</td><td>{{ row.completed_sessions }}</td><td>{{ row.not_completed_sessions }}</td>
                            <td>{{ row.required_points }}</td><td>{{ row.scanned_points }}</td>
                            <td class="font-medium">{{ fmtPct(row.completion_percentage) }}</td><td>{{ row.incidents }}</td>
                        </tr>
                    </DataTable>
                </Card>

                <Card title="Rekap per Pos Jaga">
                    <DataTable :has-rows="by_post.length > 0" empty-title="Tidak ada data" empty-description="Tidak ada rekap pos jaga untuk filter ini.">
                        <template #head>
                            <th>Pos Jaga</th><th>Unit</th><th>Total Sesi</th><th>Selesai</th><th>Titik Wajib</th><th>Titik Dipindai</th><th>Penyelesaian</th><th>Insiden</th>
                        </template>
                        <tr v-for="(row, i) in by_post" :key="`p-${i}`">
                            <td class="text-sm text-gray-800">{{ row.post_name }}</td><td class="text-sm text-gray-700">{{ row.unit_name }}</td>
                            <td>{{ row.total_sessions }}</td><td>{{ row.completed_sessions }}</td>
                            <td>{{ row.required_points }}</td><td>{{ row.scanned_points }}</td>
                            <td class="font-medium">{{ fmtPct(row.completion_percentage) }}</td><td>{{ row.incidents }}</td>
                        </tr>
                    </DataTable>
                </Card>

                <Card title="Rekap per Regu">
                    <DataTable :has-rows="by_team.length > 0" empty-title="Tidak ada data" empty-description="Tidak ada rekap regu untuk filter ini.">
                        <template #head>
                            <th>Regu</th><th>Pos Jaga</th><th>Total Sesi</th><th>Selesai</th><th>Titik Wajib</th><th>Titik Dipindai</th><th>Penyelesaian</th><th>Insiden</th>
                        </template>
                        <tr v-for="(row, i) in by_team" :key="`t-${i}`">
                            <td class="text-sm text-gray-800">{{ row.team_name }}</td><td class="text-sm text-gray-700">{{ row.post_name }}</td>
                            <td>{{ row.total_sessions }}</td><td>{{ row.completed_sessions }}</td>
                            <td>{{ row.required_points }}</td><td>{{ row.scanned_points }}</td>
                            <td class="font-medium">{{ fmtPct(row.completion_percentage) }}</td><td>{{ row.incidents }}</td>
                        </tr>
                    </DataTable>
                </Card>

                <Card title="Rekap Aktivitas Satpam">
                    <DataTable :has-rows="security_activity.length > 0" empty-title="Tidak ada data" empty-description="Tidak ada aktivitas satpam untuk filter ini.">
                        <template #head>
                            <th>Nama Satpam</th><th>No. Registrasi</th><th>Sesi Ditugaskan</th><th>Sesi Diikuti</th><th>Titik Dipindai</th><th>Insiden Dilaporkan</th>
                        </template>
                        <tr v-for="row in security_activity" :key="`s-${row.security_id}`">
                            <td class="text-sm text-gray-800">{{ row.security_name }}</td>
                            <td class="text-sm text-gray-700">{{ row.security_registration_number || '-' }}</td>
                            <td>{{ row.assigned_sessions }}</td><td>{{ row.participated_sessions }}</td>
                            <td>{{ row.scanned_points }}</td><td>{{ row.incident_reports }}</td>
                        </tr>
                    </DataTable>
                </Card>

                <Card title="Laporan Insiden">
                    <DataTable :has-rows="incidents.length > 0" empty-title="Tidak ada insiden" empty-description="Tidak ada laporan insiden untuk filter ini.">
                        <template #head>
                            <th>Tanggal/Waktu</th><th>Unit</th><th>Pos Jaga</th><th>Regu</th><th>Titik Patroli</th><th>Pelapor</th><th>Deskripsi</th><th>Foto</th><th class="text-right">Aksi</th>
                        </template>
                        <tr v-for="row in incidents" :key="`i-${row.scan_id}`">
                            <td class="whitespace-nowrap text-sm text-gray-700">{{ row.scanned_at || '-' }}</td>
                            <td class="text-sm text-gray-800">{{ row.unit_name }}</td>
                            <td class="text-sm text-gray-700">{{ row.post_name }}</td>
                            <td class="text-sm text-gray-700">{{ row.team_name }}</td>
                            <td class="text-sm text-gray-800">{{ row.patrol_point_name }}</td>
                            <td class="text-sm text-gray-700">{{ row.security_name }}</td>
                            <td class="max-w-xs text-sm text-gray-700">{{ row.incident_description || '-' }}</td>
                            <td><Badge :variant="row.has_photo ? 'emerald' : 'gray'">{{ row.photo_label }}</Badge></td>
                            <td class="text-right">
                                <Link :href="row.detail_url" class="inline-flex items-center rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">Detail Sesi</Link>
                            </td>
                        </tr>
                    </DataTable>
                </Card>
            </template>
        </div>
    </AdminLayout>
</template>
