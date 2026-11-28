<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Badge from '@/Components/Admin/Badge.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';

const props = defineProps({
    record: { type: Object, required: true },
    corrections: { type: Array, default: () => [] },
    anomalies: { type: Array, default: () => [] },
});

const statusVariants = {
    scheduled: 'gray',
    pending: 'amber',
    in_progress: 'blue',
    overdue_checkout: 'amber',
    completed: 'emerald',
    absent: 'red',
    day_off: 'gray',
};

const correctionVariants = {
    pending: 'amber',
    approved: 'emerald',
    rejected: 'red',
};

function coordinate(value) {
    return value ?? '-';
}
</script>

<template>
    <AdminLayout title="Detail Absensi">
        <div class="space-y-6">
            <PageHeader
                title="Detail Absensi"
                :description="`${record.security_name} - ${record.work_date_label}`"
                :breadcrumb="[
                    { label: 'Absensi' },
                    { label: 'Data Absensi', href: '/admin/attendance-record' },
                    { label: 'Detail' },
                ]"
            >
                <template #actions>
                    <Link
                        :href="record.back_url"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50"
                    >
                        Kembali
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                    <Card title="Informasi Absensi">
                        <dl class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <dt class="text-xs text-gray-500">Satpam</dt>
                                <dd class="text-sm font-medium text-gray-900">{{ record.security_name }}</dd>
                                <dd class="text-xs text-gray-400">{{ record.registration_number ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Status</dt>
                                <dd class="mt-1 flex flex-wrap gap-1">
                                    <Badge :variant="statusVariants[record.status] ?? 'gray'">{{ record.status_label }}</Badge>
                                    <Badge v-if="record.late_label" :variant="record.is_late ? 'red' : 'emerald'">{{ record.late_label }}</Badge>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Unit</dt>
                                <dd class="text-sm text-gray-800">{{ record.unit_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Pos Jaga</dt>
                                <dd class="text-sm text-gray-800">{{ record.post_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Regu</dt>
                                <dd class="text-sm text-gray-800">{{ record.team_name }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Shift</dt>
                                <dd class="text-sm text-gray-800">
                                    {{ record.shift_name }} ({{ record.shift_code }})
                                    <span class="block text-xs text-gray-400">{{ record.shift_time ?? 'Libur' }}</span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Jadwal (WIB)</dt>
                                <dd class="text-sm text-gray-800">
                                    {{ record.scheduled_start ?? '-' }} s/d {{ record.scheduled_end ?? '-' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Snapshot Terkunci</dt>
                                <dd class="text-sm text-gray-800">{{ record.snapshot_locked_at ?? 'Belum terkunci' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Sinkronisasi Terakhir</dt>
                                <dd class="text-sm text-gray-800">{{ record.last_synced_at ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500">Ditandai Tidak Hadir</dt>
                                <dd class="text-sm text-gray-800">{{ record.absent_marked_at ?? '-' }}</dd>
                            </div>
                        </dl>
                    </Card>

                    <Card title="Aktivitas Check-in / Check-out">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-lg border border-gray-200 p-4">
                                <p class="text-sm font-semibold text-gray-800">Check-in</p>
                                <template v-if="record.check_in">
                                    <dl class="mt-2 space-y-1 text-xs text-gray-600">
                                        <div class="flex justify-between gap-2"><dt>Waktu</dt><dd>{{ record.check_in.at }}</dd></div>
                                        <div class="flex justify-between gap-2"><dt>Waktu Perangkat</dt><dd>{{ record.check_in.captured_at ?? '-' }}</dd></div>
                                        <div class="flex justify-between gap-2"><dt>Koordinat</dt><dd>{{ coordinate(record.check_in.latitude) }}, {{ coordinate(record.check_in.longitude) }}</dd></div>
                                        <div class="flex justify-between gap-2"><dt>Akurasi Lokasi</dt><dd>{{ record.check_in.accuracy_meters ?? '-' }} m</dd></div>
                                        <div class="flex justify-between gap-2"><dt>Jarak ke Pos</dt><dd>{{ record.check_in.distance_meters ?? '-' }} m</dd></div>
                                        <div class="flex justify-between gap-2"><dt>Dalam Radius</dt><dd>{{ record.check_in.geofence_valid ? 'Ya' : 'Tidak' }}</dd></div>
                                    </dl>
                                </template>
                                <p v-else class="mt-2 text-xs text-gray-500">Belum ada check-in.</p>
                            </div>

                            <div class="rounded-lg border border-gray-200 p-4">
                                <p class="text-sm font-semibold text-gray-800">Check-out</p>
                                <template v-if="record.check_out">
                                    <dl class="mt-2 space-y-1 text-xs text-gray-600">
                                        <div class="flex justify-between gap-2"><dt>Waktu</dt><dd>{{ record.check_out.at }}</dd></div>
                                        <div class="flex justify-between gap-2"><dt>Waktu Perangkat</dt><dd>{{ record.check_out.captured_at ?? '-' }}</dd></div>
                                        <div class="flex justify-between gap-2"><dt>Koordinat</dt><dd>{{ coordinate(record.check_out.latitude) }}, {{ coordinate(record.check_out.longitude) }}</dd></div>
                                        <div class="flex justify-between gap-2"><dt>Akurasi Lokasi</dt><dd>{{ record.check_out.accuracy_meters ?? '-' }} m</dd></div>
                                        <div class="flex justify-between gap-2"><dt>Jarak ke Pos</dt><dd>{{ record.check_out.distance_meters ?? '-' }} m</dd></div>
                                        <div class="flex justify-between gap-2"><dt>Dalam Radius</dt><dd>{{ record.check_out.geofence_valid ? 'Ya' : 'Tidak' }}</dd></div>
                                    </dl>
                                </template>
                                <p v-else class="mt-2 text-xs text-gray-500">Belum ada check-out.</p>
                            </div>
                        </div>

                        <div class="mt-4 rounded-lg border border-blue-100 bg-blue-50/60 p-4">
                            <p class="text-sm font-semibold text-gray-800">Nilai Koreksi yang Berlaku</p>
                            <dl class="mt-2 grid gap-2 text-xs text-gray-600 sm:grid-cols-3">
                                <div>
                                    <dt>Check-in</dt>
                                    <dd class="font-medium text-gray-800">{{ record.corrected_check_in ?? 'Tidak dikoreksi' }}</dd>
                                </div>
                                <div>
                                    <dt>Check-out</dt>
                                    <dd class="font-medium text-gray-800">{{ record.corrected_check_out ?? 'Tidak dikoreksi' }}</dd>
                                </div>
                                <div>
                                    <dt>Status</dt>
                                    <dd class="font-medium text-gray-800">{{ record.corrected_status_label ?? 'Tidak dikoreksi' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </Card>

                    <Card title="Riwayat Pengajuan Koreksi">
                        <div class="overflow-hidden rounded-md border border-gray-200">
                            <table class="w-full min-w-[720px] text-left text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Diajukan</th>
                                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Perubahan</th>
                                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Alasan</th>
                                        <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Keputusan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="correction in corrections" :key="correction.id" class="border-t border-gray-100 odd:bg-white even:bg-gray-50/60">
                                        <td class="px-4 py-2.5 text-gray-600">
                                            <p>{{ correction.requested_at }}</p>
                                            <p class="text-xs text-gray-400">{{ correction.requested_by ?? '-' }}</p>
                                        </td>
                                        <td class="px-4 py-2.5 text-xs text-gray-600">
                                            <p>Check-in: {{ correction.requested_check_in ?? '-' }}</p>
                                            <p>Check-out: {{ correction.requested_check_out ?? '-' }}</p>
                                            <p>Status: {{ correction.requested_status ?? '-' }}</p>
                                        </td>
                                        <td class="px-4 py-2.5 text-gray-600">{{ correction.reason }}</td>
                                        <td class="px-4 py-2.5">
                                            <Badge :variant="correctionVariants[correction.status] ?? 'gray'">{{ correction.status_label }}</Badge>
                                            <p class="mt-1 text-xs text-gray-500">
                                                {{ correction.reviewed_by ?? '-' }}
                                                <span v-if="correction.reviewed_at">, {{ correction.reviewed_at }}</span>
                                            </p>
                                            <p v-if="correction.review_note" class="text-xs text-gray-500">{{ correction.review_note }}</p>
                                        </td>
                                    </tr>
                                    <tr v-if="corrections.length === 0">
                                        <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">Belum ada pengajuan koreksi.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </Card>
                </div>

                <div class="space-y-4">
                    <Card title="Pengaturan Snapshot">
                        <dl class="space-y-2 text-xs text-gray-600">
                            <div class="flex justify-between gap-2"><dt>Radius Lokasi</dt><dd>{{ record.settings.gps_radius_meters }} m</dd></div>
                            <div class="flex justify-between gap-2"><dt>Check-in Dibuka</dt><dd>{{ record.settings.check_in_open_minutes }} menit</dd></div>
                            <div class="flex justify-between gap-2"><dt>Toleransi Absen Masuk</dt><dd>{{ record.settings.check_in_tolerance_minutes ?? record.settings.late_tolerance_minutes }} menit</dd></div>
                            <div class="flex justify-between gap-2"><dt>Toleransi Check-out Awal</dt><dd>{{ record.settings.early_checkout_tolerance_minutes }} menit</dd></div>
                            <div class="flex justify-between gap-2"><dt>Check-out Ditutup</dt><dd>{{ record.settings.checkout_close_minutes }} menit</dd></div>
                            <div class="flex justify-between gap-2"><dt>Akurasi Lokasi Maksimal</dt><dd>{{ record.settings.max_gps_accuracy_meters }} m</dd></div>
                        </dl>
                    </Card>

                    <Card title="Koordinat Pos Jaga">
                        <dl class="space-y-2 text-xs text-gray-600">
                            <div class="flex justify-between gap-2"><dt>Latitude</dt><dd>{{ coordinate(record.post_coordinates.latitude) }}</dd></div>
                            <div class="flex justify-between gap-2"><dt>Longitude</dt><dd>{{ coordinate(record.post_coordinates.longitude) }}</dd></div>
                            <div class="flex justify-between gap-2"><dt>Snapshot Terkunci</dt><dd>{{ record.is_locked ? 'Ya' : 'Belum' }}</dd></div>
                        </dl>
                    </Card>

                    <Card title="Data Bermasalah">
                        <ul v-if="anomalies.length" class="space-y-3">
                            <li v-for="anomaly in anomalies" :key="anomaly.id" class="rounded-lg border border-gray-200 p-3">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="text-sm font-medium text-gray-800">{{ anomaly.type_label }}</p>
                                    <Badge :variant="anomaly.is_active ? 'amber' : 'emerald'">
                                        {{ anomaly.is_active ? 'Aktif' : 'Selesai' }}
                                    </Badge>
                                </div>
                                <p class="mt-1 text-xs text-gray-600">{{ anomaly.details }}</p>
                                <p class="mt-1 text-xs text-gray-400">
                                    Terdeteksi {{ anomaly.detected_at }}
                                    <span v-if="anomaly.resolved_at"> - Selesai {{ anomaly.resolved_at }}</span>
                                </p>
                                <p v-if="anomaly.resolution_note" class="mt-1 text-xs text-gray-500">{{ anomaly.resolution_note }}</p>
                            </li>
                        </ul>
                        <p v-else class="text-xs text-gray-500">Tidak ada data bermasalah pada jadwal ini.</p>
                    </Card>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
