<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Badge from '@/Components/Admin/Badge.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import DataTable from '@/Components/Admin/DataTable.vue';
import ImageWithFallback from '@/Components/Admin/ImageWithFallback.vue';

const props = defineProps({
    session: { type: Object, required: true },
    members: { type: Array, default: () => [] },
    points: { type: Array, default: () => [] },
    incidents: { type: Array, default: () => [] },
});

const openCells = ref({}); // key: `${pointId}_${securityId}`
const openPoint = ref({});

function cellKey(pointId, securityId) {
    return `${pointId}_${securityId}`;
}

function toggleCell(pointId, securityId) {
    const k = cellKey(pointId, securityId);
    openCells.value = { ...openCells.value, [k]: !openCells.value[k] };
}

function isCellOpen(pointId, securityId) {
    return !!openCells.value[cellKey(pointId, securityId)];
}

function togglePoint(pointId) {
    openPoint.value = { ...openPoint.value, [pointId]: !openPoint.value[pointId] };
}

const statusVariants = {
    waiting: 'amber',
    pending: 'amber',
    not_started: 'gray',
    missed: 'red',
    active: 'blue',
    completed: 'emerald',
    incomplete: 'red',
};

function pointStatusVariant(point) {
    if (!point.fulfilled && !point.scanned) return 'gray';
    return point.point_status === 'incident' ? 'red' : 'emerald';
}

function pointStatusLabel(point) {
    if (!point.fulfilled && !point.scanned) return 'Belum Dipindai';
    if (point.point_status_label) return point.point_status_label;
    return point.point_status === 'incident' ? 'Ada Insiden' : 'Sudah Dipindai';
}

function formatGps(scan) {
    if (!scan || (scan.latitude === null || scan.latitude === undefined || scan.latitude === '')) return '-';
    const lat = scan.latitude;
    const lng = scan.longitude ?? '-';
    const acc = scan.accuracy !== null && scan.accuracy !== undefined && scan.accuracy !== '' ? ` (akurasi ${scan.accuracy} m)` : '';
    return `${lat}, ${lng}${acc}`;
}
</script>

<template>
    <AdminLayout title="Detail Sesi Patroli">
        <div class="space-y-6">
            <PageHeader
                title="Detail Sesi Patroli"
                description="Monitoring partisipasi dan riwayat scan per titik berdasarkan snapshot sesi."
                :breadcrumb="[
                    { label: 'Penjadwalan' },
                    { label: 'Monitoring Patroli', href: '/admin/patrol-session' },
                    { label: 'Detail Sesi' },
                ]"
            >
                <template #actions>
                    <Link href="/admin/patrol-session">
                        <Button variant="secondary">Kembali</Button>
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <Card>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <p class="text-sm text-gray-500">Sesi #{{ session.id }} · {{ session.work_date_label }}</p>
                        <h2 class="mt-1 text-lg font-semibold text-gray-900">{{ session.unit_name }} <span v-if="session.unit_code" class="text-sm font-normal text-gray-500">({{ session.unit_code }})</span></h2>
                        <p class="mt-1 text-sm text-gray-700">{{ session.post_name }} <span v-if="session.post_code" class="text-gray-500">({{ session.post_code }})</span> · Regu {{ session.team_name }}</p>
                    </div>
                    <Badge :variant="session.status_variant ?? statusVariants[session.display_status] ?? 'gray'">{{ session.status_label }}</Badge>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Shift & Jam</p>
                        <p class="mt-1 text-gray-800">{{ session.shift_name }} <span v-if="session.shift_code" class="text-gray-500">({{ session.shift_code }})</span></p>
                        <p class="text-gray-600">{{ session.shift_time }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Tanggal</p>
                        <p class="mt-1 text-gray-800">{{ session.work_date_label }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Waktu Mulai / Selesai</p>
                        <p class="mt-1 text-gray-800">Mulai: {{ session.started_at ?? '-' }}</p>
                        <p class="text-gray-600">Selesai: {{ session.ended_at ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Pemenuhan Titik (unik)</p>
                        <p class="mt-1 font-medium text-gray-800">{{ session.fulfilled_points ?? session.scanned_points }} / {{ session.required_points }} titik ({{ session.progress }}%)</p>
                        <p class="text-xs text-gray-500">Total aktivitas scan: {{ session.total_scans ?? '-' }}</p>
                        <div class="mt-2 h-2 w-full max-w-[180px] overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-emerald-500" :style="{ width: `${session.progress}%` }"></div>
                        </div>
                    </div>
                </div>
            </Card>

            <Card title="Anggota Regu (snapshot)">
                <DataTable :has-rows="members.length > 0" empty-title="Tidak ada anggota" empty-description="Data anggota snapshot tidak tersedia.">
                    <template #head>
                        <th>Nama Satpam</th>
                        <th>No. Registrasi</th>
                        <th>Total Aktivitas Scan</th>
                    </template>
                    <tr v-for="member in members" :key="member.id">
                        <td class="text-sm text-gray-800">{{ member.security_name ?? '-' }}</td>
                        <td class="text-sm text-gray-700">{{ member.security_registration_number ?? '-' }}</td>
                        <td class="text-sm">
                            <Badge :variant="member.scan_count > 0 ? 'emerald' : 'gray'">{{ member.scan_activity }}</Badge>
                        </td>
                    </tr>
                </DataTable>
            </Card>

            <Card title="Matriks Partisipasi Satpam per Titik">
                <p class="mb-3 text-sm text-gray-600">Baris: titik patroli, kolom: anggota sesi. Sel menampilkan jumlah scan atau <strong>Belum Scan</strong>. Klik sel untuk melihat seluruh riwayat aktivitas.</p>
                <div v-if="points.length === 0" class="text-sm text-gray-500">Sesi ini tidak memiliki snapshot titik patroli.</div>
                <div v-else class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full border-collapse text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="sticky left-0 z-10 border-b border-r border-gray-200 bg-gray-50 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Titik</th>
                                <th class="border-b border-r border-gray-200 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Status Titik</th>
                                <th class="border-b border-r border-gray-200 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">Ringkasan</th>
                                <th v-for="m in members" :key="'mh-'+m.security_id" class="min-w-[160px] border-b border-r border-gray-200 px-3 py-2 text-left text-xs font-semibold text-gray-700">
                                    <div>{{ m.security_name }}</div>
                                    <div class="font-normal text-gray-500">{{ m.security_registration_number || '-' }}</div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="point in points" :key="point.id">
                                <tr class="align-top">
                                    <td class="sticky left-0 z-[1] border-b border-r border-gray-200 bg-white px-3 py-2">
                                        <div class="font-medium text-gray-800">{{ point.sequence }}. {{ point.name }}</div>
                                    </td>
                                    <td class="border-b border-r border-gray-200 px-3 py-2">
                                        <Badge :variant="pointStatusVariant(point)">{{ pointStatusLabel(point) }}</Badge>
                                    </td>
                                    <td class="border-b border-r border-gray-200 px-3 py-2 text-xs text-gray-700">
                                        <div>Total scan: <strong>{{ point.total_scans || 0 }}</strong></div>
                                        <div>Sudah memindai: {{ point.members_scanned_count || 0 }}</div>
                                        <div>Belum memindai: {{ point.members_not_scanned_count || 0 }}</div>
                                        <div v-if="point.last_scanned_at" class="text-gray-500">Terakhir: {{ point.last_scanned_at }}</div>
                                    </td>
                                    <td v-for="cell in (point.member_cells || [])" :key="`c-${point.id}-${cell.security_id}`" class="border-b border-r border-gray-200 px-3 py-2">
                                        <button
                                            v-if="cell.scan_count > 0"
                                            type="button"
                                            class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100"
                                            @click="toggleCell(point.id, cell.security_id)"
                                        >
                                            {{ cell.scan_count }} scan · {{ isCellOpen(point.id, cell.security_id) ? 'Tutup' : 'Detail' }}
                                        </button>
                                        <span v-else class="inline-flex rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600">Belum Scan</span>
                                    </td>
                                </tr>
                                <tr v-for="cell in (point.member_cells || [])" :key="`d-${point.id}-${cell.security_id}`" v-show="isCellOpen(point.id, cell.security_id) && cell.scan_count > 0">
                                    <td :colspan="3 + members.length" class="border-b border-gray-200 bg-gray-50/70 px-3 py-3">
                                        <div class="mb-2 text-sm font-medium text-gray-800">Riwayat scan — {{ point.name }} · {{ cell.security_name }} <span class="font-normal text-gray-500">({{ cell.security_registration_number || '-' }})</span></div>
                                        <div class="space-y-3">
                                            <div v-for="scan in (cell.scans || [])" :key="scan.id" class="rounded-md border border-gray-200 bg-white p-3">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <Badge :variant="scan.has_incident ? 'red' : 'emerald'">{{ scan.condition_label || (scan.has_incident ? 'Ada Insiden' : 'Aman') }}</Badge>
                                                    <span class="text-xs text-gray-600">Waktu: {{ scan.scanned_at || '-' }}</span>
                                                </div>
                                                <div class="mt-2 grid grid-cols-1 gap-2 text-xs text-gray-700 sm:grid-cols-2 lg:grid-cols-3">
                                                    <div><span class="text-gray-500">Satpam:</span> {{ scan.security_name }} <span v-if="scan.security_registration_number">({{ scan.security_registration_number }})</span></div>
                                                    <div><span class="text-gray-500">GPS/Akurasi:</span> {{ formatGps(scan) }}</div>
                                                    <div v-if="scan.has_incident" class="sm:col-span-2 lg:col-span-3"><span class="text-gray-500">Deskripsi insiden:</span>
                                                        <p class="mt-0.5 whitespace-pre-wrap">{{ scan.incident_description || '-' }}</p>
                                                    </div>
                                                </div>
                                                <div v-if="scan.photos && scan.photos.length" class="mt-2">
                                                    <p class="mb-1 text-xs text-gray-500">Foto insiden ({{ scan.photos.length }})</p>
                                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
                                                        <a v-for="ph in scan.photos" :key="`ph-${scan.id}-${ph.id}-${ph.sequence}`" :href="ph.url" target="_blank" class="block overflow-hidden rounded border border-gray-200 bg-white">
                                                            <ImageWithFallback :src="ph.url" alt="Foto insiden" class="h-24 w-full object-cover" loading="lazy" />
                                                        </a>
                                                    </div>
                                                </div>
                                                <div v-else-if="scan.has_incident" class="mt-1 text-xs text-gray-500">Tidak ada foto insiden.</div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </Card>

            <Card v-if="incidents.length > 0" title="Ringkasan Insiden (semua aktivitas)">
                <div class="space-y-4">
                    <div v-for="incident in incidents" :key="incident.id" class="rounded-lg border border-red-100 bg-red-50/40 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <Badge variant="red">Insiden</Badge>
                                    <span class="text-sm font-medium text-gray-900">{{ incident.patrol_point_name }}</span>
                                </div>
                                <p class="mt-1 text-xs text-gray-600">Waktu laporan: {{ incident.reported_at ?? '-' }}</p>
                                <p class="text-xs text-gray-600">Pelapor: {{ incident.reported_by_name ?? '-' }} <span v-if="incident.reported_by_registration_number">({{ incident.reported_by_registration_number }})</span></p>
                            </div>
                        </div>
                        <p class="mt-3 whitespace-pre-wrap text-sm text-gray-800">{{ incident.description || '-' }}</p>
                        <div v-if="incident.photos && incident.photos.length" class="mt-3">
                            <p class="mb-2 text-xs font-medium text-gray-600">Foto Bukti ({{ incident.photos.length }})</p>
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                                <a v-for="ph in incident.photos" :key="`inc-${incident.id}-${ph.id}-${ph.sequence}`" :href="ph.url" target="_blank" class="block overflow-hidden rounded-md border border-gray-200 bg-white">
                                    <ImageWithFallback :src="ph.url" alt="Foto insiden" class="h-28 w-full object-cover" loading="lazy" />
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>
        </div>
    </AdminLayout>
</template>
