<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppHeader from '@/Components/App/AppHeader.vue';
import BottomNavigation from '@/Components/App/Home/BottomNavigation.vue';
import {
    CheckCircleIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    MapPinIcon,
    QrCodeIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    session: { type: Object, required: true },
    points: { type: Array, default: () => [] },
    last_scan: { type: Object, default: null },
});

const sessionState = ref({ ...props.session });
const pointList = ref(props.points.map((p) => ({ ...p })));
const lastScan = ref(props.last_scan ? { ...props.last_scan } : null);
const starting = ref(false);
const feedback = ref({ type: '', message: '' });
const expanded = ref({});

const statusMap = {
    pending: { label: 'Menunggu', cls: 'bg-amber-50 text-amber-700 border-amber-200' },
    active: { label: 'Aktif', cls: 'bg-[#E8F7F6] text-[#1D567F] border-[#BFE9E6]' },
    completed: { label: 'Selesai', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
    incomplete: { label: 'Tidak Lengkap', cls: 'bg-red-50 text-red-700 border-red-200' },
    not_started: { label: 'Tidak Dimulai', cls: 'bg-gray-100 text-gray-600 border-gray-200' },
};

const statusInfo = computed(() => statusMap[sessionState.value.status] || { label: sessionState.value.status, cls: 'bg-gray-100 text-gray-600 border-gray-200' });
const startWindow = computed(() => sessionState.value.start_window || null);
const canStart = computed(() => sessionState.value.status === 'pending' && !!startWindow.value?.can_start_now && !starting.value);
const isActive = computed(() => sessionState.value.status === 'active');
const isCompleted = computed(() => sessionState.value.status === 'completed');
const requiredCount = computed(() => Number(sessionState.value.required_points || pointList.value.length || 0));
const fulfilledCount = computed(() => {
    if (typeof sessionState.value.fulfilled_points === 'number') return sessionState.value.fulfilled_points;
    return pointList.value.filter((p) => p.scanned || p.fulfilled || (p.scan_count || 0) > 0).length;
});
const totalScans = computed(() => {
    if (typeof sessionState.value.total_scans === 'number') return sessionState.value.total_scans;
    return pointList.value.reduce((a, p) => a + (p.scan_count || 0), 0);
});
const progress = computed(() => (requiredCount.value > 0 ? Math.round((fulfilledCount.value / requiredCount.value) * 100) : (sessionState.value.progress || 0)));

function setFeedback(type, message) {
    feedback.value = { type, message };
}

function formatIso(iso) {
    if (!iso) return '-';
    try {
        return new Date(iso).toLocaleString('id-ID', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: 'short', year: 'numeric' });
    } catch (e) {
        return iso;
    }
}

function pointStatusLabel(p) {
    const scanned = (p.scan_count || 0) > 0 || p.scanned || p.fulfilled;
    if (!scanned) return 'Belum Dipindai';
    return 'Sudah Dipindai';
}

function pointStatusClass(p) {
    const scanned = (p.scan_count || 0) > 0 || p.scanned || p.fulfilled;
    if (!scanned) return 'bg-gray-100 text-gray-600';
    return p.has_incident ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700';
}

function extractError(err, fallback) {
    const resp = err?.response;
    if (!resp) {
        const msg = err?.message || '';
        const isNetwork = err?.code === 'ERR_NETWORK' || msg === 'Network Error' || /network|failed to fetch|load failed/i.test(msg);
        if (isNetwork) return `${fallback} Tidak dapat terhubung ke server. Periksa koneksi internet Anda.`;
        return `${fallback} ${msg ? '(' + msg + ')' : ''}`.trim();
    }
    const data = resp.data;
    let detail = '';
    if (data?.errors && typeof data.errors === 'object') detail = Object.values(data.errors).flat().filter(Boolean).join(' ');
    else if (typeof data?.message === 'string' && data.message.trim()) detail = data.message.trim();
    if (!detail) {
        if (resp.status === 419) detail = 'Sesi kedaluwarsa. Muat ulang halaman.';
        else if (resp.status === 401) detail = 'Sesi login berakhir. Silakan login kembali.';
        else if (resp.status === 403) detail = 'Anda tidak memiliki izin.';
        else if (resp.status >= 500) detail = 'Terjadi kesalahan pada server.';
    }
    return detail ? `${fallback} ${detail}` : `${fallback} (kode ${resp.status})`;
}

async function startSession() {
    if (starting.value) return;
    starting.value = true;
    setFeedback('', '');
    try {
        const { data } = await window.axios.post(`/app/patrol/${sessionState.value.id}/start`, {}, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (data?.session) {
            sessionState.value = { ...sessionState.value, status: data.session.status ?? sessionState.value.status, started_at: data.session.started_at ?? sessionState.value.started_at };
        }
        setFeedback('success', data?.message || 'Sesi patroli dimulai.');
    } catch (err) {
        setFeedback('error', extractError(err, 'Gagal memulai sesi patroli.'));
    } finally {
        starting.value = false;
    }
}

function togglePoint(id) {
    expanded.value = { ...expanded.value, [id]: !expanded.value[id] };
}

function goScan() {
    router.visit(`/app/patrol/${sessionState.value.id}/scan`);
}

</script>

<template>
    <AppLayout>
        <div class="min-h-full bg-gray-50">
            <AppHeader
                :title="sessionState.post_name"
                :description="`Regu ${sessionState.team_name} · ${sessionState.unit_name}`"
                backUrl="/app/patrol"
                backLabel="Patroli"
            />


            <main class="space-y-4 px-4 pb-8 pt-4">
                <section class="rounded-xl border border-[#E8E8EC] bg-white p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-[#263746]">{{ sessionState.shift_name }}</p>
                            <p class="mt-0.5 text-xs text-[#9299A2]">{{ sessionState.work_date_label }}<span v-if="sessionState.shift_time && sessionState.shift_time !== '-'"> · {{ sessionState.shift_time }}</span></p>
                            <p v-if="sessionState.started_at" class="mt-1 text-xs text-[#9299A2]">Mulai: {{ formatIso(sessionState.started_at) }}</p>
                            <p v-if="sessionState.ended_at" class="mt-0.5 text-xs text-[#9299A2]">Selesai: {{ formatIso(sessionState.ended_at) }}</p>
                        </div>
                        <span class="shrink-0 rounded-full border px-2 py-0.5 text-[11px] font-medium" :class="statusInfo.cls">{{ statusInfo.label }}</span>
                    </div>

                    <div class="mt-4">
                        <div class="mb-1 flex items-center justify-between text-xs">
                            <span class="text-[#9299A2]">Pemenuhan titik unik</span>
                            <span class="font-semibold text-[#1D567F]">{{ fulfilledCount }}/{{ requiredCount }} titik ({{ progress }}%)</span>
                        </div>
                        <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-[#38BDB5]" :style="{ width: `${progress}%` }"></div>
                        </div>
                        <p class="mt-1 text-xs text-[#9299A2]">Total aktivitas scan: {{ totalScans }}</p>
                    </div>
                </section>

                <div v-if="feedback.message" class="flex items-start gap-2 rounded-lg p-3 text-sm" :class="feedback.type === 'success' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'">
                    <CheckCircleIcon v-if="feedback.type === 'success'" class="mt-0.5 h-5 w-5 shrink-0" />
                    <ExclamationTriangleIcon v-else class="mt-0.5 h-5 w-5 shrink-0" />
                    <p>{{ feedback.message }}</p>
                </div>

                <section v-if="sessionState.status === 'pending'" class="rounded-xl border border-[#E8E8EC] bg-white p-4 space-y-2">
                    <p class="text-sm text-[#263746]">
                        {{ startWindow?.state === 'after' ? 'Waktu untuk memulai sesi telah berakhir.' : (startWindow?.state === 'before' ? 'Sesi belum dapat dimulai sesuai jadwal.' : 'Sesi patroli belum dimulai. Tekan tombol di bawah untuk memulai saat waktu shift.') }}
                    </p>
                    <p v-if="startWindow?.start_at_wib" class="text-xs text-[#9299A2]">Jadwal mulai: {{ startWindow.start_at_wib }}</p>
                    <p v-if="startWindow?.end_at_wib" class="text-xs text-[#9299A2]">Batas waktu mulai: sebelum {{ startWindow.end_at_wib }}</p>
                    <p v-if="startWindow?.message && startWindow?.state !== 'open'" class="text-xs font-medium" :class="startWindow?.state === 'after' ? 'text-red-700' : 'text-amber-700'">{{ startWindow.message }}</p>
                    <button v-if="startWindow?.state !== 'after'" type="button" class="mt-1 flex w-full items-center justify-center rounded-lg bg-[#1D567F] px-4 py-3 text-sm font-semibold text-white disabled:opacity-50" :disabled="!canStart" @click="startSession">
                        {{ starting ? 'Memulai...' : 'Mulai Patroli' }}
                    </button>
                </section>

                <section v-if="isActive" class="rounded-xl border border-[#BFE9E6] bg-[#F4FBFA] p-4">
                    <p class="text-sm text-[#263746]">Sesi aktif. Titik yang sudah terpenuhi tetap dapat dipindai kembali untuk menambah aktivitas.</p>
                    <button type="button" class="mt-3 flex w-full items-center justify-center gap-2 rounded-lg bg-[#38BDB5] px-4 py-3 text-sm font-semibold text-white active:bg-[#2fa8a1]" @click="goScan">
                        <QrCodeIcon class="h-5 w-5" />
                        Scan Titik Patroli
                    </button>
                </section>

                <section v-if="isCompleted" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <div class="flex items-center gap-2 text-emerald-700">
                        <CheckCircleIcon class="h-6 w-6" />
                        <p class="text-sm font-semibold">Patroli selesai</p>
                    </div>
                    <p class="mt-2 text-sm text-emerald-800">Seluruh {{ requiredCount }} titik wajib unik telah memiliki minimal satu scan berhasil.</p>
                    <p v-if="sessionState.started_at" class="mt-1 text-xs text-emerald-700">Mulai: {{ formatIso(sessionState.started_at) }}</p>
                    <p v-if="sessionState.ended_at" class="mt-0.5 text-xs text-emerald-700">Selesai: {{ formatIso(sessionState.ended_at) }}</p>
                </section>

                <section v-if="sessionState.status === 'incomplete'" class="rounded-xl border border-red-200 bg-red-50 p-4">
                    <p class="text-sm font-semibold text-red-700">Sesi patroli tidak lengkap</p>
                    <p class="mt-1 text-xs text-red-700">Progres: {{ fulfilledCount }}/{{ requiredCount }} titik terpenuhi.</p>
                </section>

                <section v-if="sessionState.status === 'not_started'" class="rounded-xl border border-[#E8E8EC] bg-white p-4">
                    <p class="text-sm font-medium text-[#263746]">Sesi tidak dimulai</p>
                    <p class="mt-1 text-xs text-[#9299A2]">Sesi ini tidak dapat dimulai lagi. Hubungi admin jika diperlukan.</p>
                </section>

                <section v-if="lastScan" class="rounded-xl border border-[#E8E8EC] bg-white p-4">
                    <h3 class="text-sm font-semibold text-[#263746]">Hasil scan terakhir</h3>
                    <p class="mt-2 text-sm text-[#263746]">{{ lastScan.patrol_point_name }}</p>
                    <p class="mt-0.5 text-xs text-[#9299A2]">Waktu: {{ lastScan.scanned_at_label || formatIso(lastScan.scanned_at) }}</p>
                    <p v-if="lastScan.has_incident" class="mt-1 text-xs font-medium text-red-700">Disertai laporan insiden</p>
                </section>

                <section class="rounded-xl border border-[#E8E8EC] bg-white p-4">
                    <div class="mb-3 flex items-center gap-2">
                        <MapPinIcon class="h-5 w-5 text-[#1D567F]" />
                        <h3 class="text-sm font-semibold text-[#263746]">Daftar Titik Patroli</h3>
                    </div>
                    <ul class="space-y-2">
                        <li v-for="p in pointList" :key="p.id" class="rounded-lg border p-3" :class="(p.scan_count || 0) > 0 ? (p.has_incident ? 'border-red-200 bg-red-50/30' : 'border-emerald-200 bg-emerald-50/30') : 'border-[#E8E8EC] bg-white'">
                            <button type="button" class="flex w-full items-start gap-3 text-left" @click="(p.scan_count || 0) > 0 ? togglePoint(p.id) : null" :class="(p.scan_count || 0) > 0 ? 'cursor-pointer' : 'cursor-default'">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold" :class="(p.scan_count || 0) > 0 ? (p.has_incident ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white') : 'bg-gray-100 text-[#9299A2]'">{{ p.sequence }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-sm font-medium text-[#263746]">{{ p.name }}</p>
                                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-medium" :class="pointStatusClass(p)">{{ pointStatusLabel(p) }}</span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-[#9299A2]">
                                        <template v-if="(p.scan_count || 0) > 0">
                                            {{ p.scan_count }} scan · Terakhir: {{ p.scanned_at_label || '-' }}
                                        </template>
                                        <template v-else>Belum dipindai</template>
                                    </p>
                                    <p v-if="(p.scan_count || 0) > 0 && p.has_incident" class="mt-0.5 text-xs text-red-700">Ada laporan insiden</p>
                                </div>
                                <template v-if="(p.scan_count || 0) > 0">
                                    <ChevronUpIcon v-if="expanded[p.id]" class="h-4 w-4 shrink-0 text-[#9299A2]" />
                                    <ChevronDownIcon v-else class="h-4 w-4 shrink-0 text-[#9299A2]" />
                                </template>
                                <ClockIcon v-else class="h-5 w-5 shrink-0 text-[#9299A2]" />
                            </button>

                            <div v-if="(p.scan_count || 0) > 0 && expanded[p.id]" class="mt-3 space-y-2 border-t border-[#E8E8EC] pt-3 text-xs text-[#263746]">
                                <p><span class="text-[#9299A2]">Jumlah scan:</span> {{ p.scan_count }}</p>
                                <p><span class="text-[#9299A2]">Scan terakhir:</span> {{ p.scanned_at_label || '-' }}</p>
                                <p><span class="text-[#9299A2]">Status titik:</span> {{ pointStatusLabel(p) }}</p>
                                <p v-if="p.scanned_by_name"><span class="text-[#9299A2]">Pemindai terakhir:</span> {{ p.scanned_by_name }}<span v-if="p.scanned_by_registration_number"> ({{ p.scanned_by_registration_number }})</span></p>
                                <p v-if="p.latitude || p.longitude"><span class="text-[#9299A2]">Lokasi terakhir:</span> {{ p.latitude }}, {{ p.longitude }}<span v-if="p.accuracy"> (akurasi {{ p.accuracy }} m)</span></p>
                                <div v-if="p.has_incident">
                                    <p class="text-[#9299A2]">Deskripsi insiden (scan terakhir):</p>
                                    <p class="mt-0.5 whitespace-pre-line">{{ p.incident_description || '-' }}</p>
                                </div>
                                <div v-if="p.photos && p.photos.length" class="space-y-2">
                                    <p class="text-[#9299A2]">Foto insiden (scan terakhir):</p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <a v-for="ph in p.photos" :key="ph.id + '-' + ph.sequence" :href="ph.url" target="_blank" class="block overflow-hidden rounded-lg border border-[#E8E8EC]">
                                            <img :src="ph.url" alt="Foto insiden" class="h-28 w-full object-cover" loading="lazy" />
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
                </section>
            </main>
        </div>

        <template #bottom>
            <BottomNavigation active="patrol" />
        </template>
    </AppLayout>
</template>
