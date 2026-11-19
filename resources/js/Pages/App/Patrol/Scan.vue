<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppHeader from '@/Components/App/AppHeader.vue';
import PatrolQrScanner from '@/Components/App/PatrolQrScanner.vue';
import PatrolCameraCapture from '@/Components/App/PatrolCameraCapture.vue';
import {
    CheckCircleIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon,
    MapPinIcon,
    QrCodeIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    session: { type: Object, required: true },
    routes: { type: Object, required: true },
    max_photos: { type: Number, default: 5 },
});

function generateToken() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();
    return 'scan-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
}

const sessionState = ref({ ...props.session });
const scannerEnabled = ref(sessionState.value.status === 'active');
const scanPaused = ref(false);
const preview = ref(null); // { point, qr_code }
const previewLoading = ref(false);
const submitting = ref(false);
const starting = ref(false);
const feedback = ref({ type: '', message: '' });
const condition = ref('safe');
const incidentDescription = ref('');
const incidentPhotos = ref([]);
const cameraRef = ref(null);
const submitToken = ref(null);

const isActive = computed(() => sessionState.value.status === 'active');
const canScan = computed(() => isActive.value && !preview.value && !previewLoading.value && !submitting.value);
const requiredPoints = computed(() => Number(sessionState.value.required_points || 0));
const fulfilledPoints = computed(() => Number(sessionState.value.fulfilled_points ?? sessionState.value.scanned_points ?? 0));
const totalScans = computed(() => Number(sessionState.value.total_scans || 0));
const progress = computed(() => (requiredPoints.value > 0 ? Math.round((fulfilledPoints.value / requiredPoints.value) * 100) : 0));

function setFeedback(type, message) {
    feedback.value = { type, message };
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
        if (resp.status === 401) detail = 'Sesi login berakhir. Silakan login kembali.';
        else if (resp.status === 403) detail = 'Anda tidak memiliki izin untuk aksi ini.';
        else if (resp.status === 404) detail = 'Data tidak ditemukan. Muat ulang halaman.';
        else if (resp.status === 419) detail = 'Sesi atau token keamanan kedaluwarsa. Muat ulang halaman.';
        else if (resp.status === 422) detail = 'Data tidak valid. Periksa isian Anda.';
        else if (resp.status >= 500) detail = 'Terjadi kesalahan pada server.';
    }
    return detail ? `${fallback} ${detail}` : `${fallback} (kode ${resp.status})`;
}

async function startSession() {
    if (starting.value) return;
    starting.value = true;
    setFeedback('', '');
    try {
        const { data } = await window.axios.post(props.routes.start, {}, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        sessionState.value = { ...sessionState.value, status: data.session?.status || 'active', started_at: data.session?.started_at || sessionState.value.started_at };
        scannerEnabled.value = sessionState.value.status === 'active';
        setFeedback('success', data?.message || 'Sesi patroli dimulai.');
    } catch (err) {
        setFeedback('error', extractError(err, 'Gagal memulai sesi patroli.'));
    } finally {
        starting.value = false;
    }
}

function getLocation() {
    return new Promise((resolve) => {
        if (!navigator.geolocation) return resolve(null);
        navigator.geolocation.getCurrentPosition(
            (pos) => resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude, accuracy: pos.coords.accuracy }),
            () => resolve(null),
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 }
        );
    });
}

async function onDecoded(qrCode) {
    if (!canScan.value) return;
    previewLoading.value = true;
    scanPaused.value = true;
    setFeedback('', '');
    try {
        const { data } = await window.axios.post(props.routes.preview, { qr_code: qrCode }, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        preview.value = { point: data.preview?.point || null, qr_code: qrCode };
        submitToken.value = generateToken();
        scannerEnabled.value = false;
        if (data?.message) setFeedback('info', data.message);
    } catch (err) {
        setFeedback('error', extractError(err, 'QR tidak valid.'));
        preview.value = null;
        submitToken.value = null;
        scannerEnabled.value = isActive.value;
        scanPaused.value = false;
    } finally {
        previewLoading.value = false;
    }
}

function stopCameraIfAny() {
    if (cameraRef.value?.stopCamera) {
        try { cameraRef.value.stopCamera(); } catch (e) {}
    }
}

function clearCameraData() {
    if (cameraRef.value?.clearAll) {
        try { cameraRef.value.clearAll(); } catch (e) {}
    }
}

function resetConfirmation({ keepFeedback = false, regenerateToken = false } = {}) {
    clearCameraData();
    condition.value = 'safe';
    incidentDescription.value = '';
    incidentPhotos.value = [];
    preview.value = null;
    scanPaused.value = false;
    scannerEnabled.value = sessionState.value.status === 'active' && !submitting.value;
    if (regenerateToken) submitToken.value = generateToken();
    else if (!preview.value) submitToken.value = null;
    if (!keepFeedback) setFeedback('', '');
}

function cancelPreview() {
    submitToken.value = null;
    resetConfirmation({ keepFeedback: false });
}

function onPhotosUpdate(files) {
    incidentPhotos.value = Array.isArray(files) ? files : [];
}

watch(condition, (val) => {
    if (val !== 'incident') {
        clearCameraData();
        incidentDescription.value = '';
        incidentPhotos.value = [];
    }
});

function applySessionFromResponse(data) {
    const s = data?.session || {};
    sessionState.value = {
        ...sessionState.value,
        status: s.status ?? sessionState.value.status,
        ended_at: s.ended_at ?? sessionState.value.ended_at,
        required_points: s.required_points ?? sessionState.value.required_points,
        fulfilled_points: s.fulfilled_points ?? s.scanned_points ?? sessionState.value.fulfilled_points,
        scanned_points: s.fulfilled_points ?? s.scanned_points ?? sessionState.value.scanned_points,
        total_scans: s.total_scans ?? sessionState.value.total_scans,
        progress: s.progress ?? sessionState.value.progress,
    };
}

async function submitInspection() {
    if (!preview.value || submitting.value) return;
    if (!submitToken.value) submitToken.value = generateToken();
    if (condition.value === 'incident' && !incidentDescription.value.trim()) {
        setFeedback('error', 'Deskripsi kejadian wajib diisi jika ada insiden.');
        return;
    }
    submitting.value = true;
    scanPaused.value = true;
    setFeedback('', '');
    try {
        const loc = await getLocation();
        const form = new FormData();
        form.append('qr_code', preview.value.qr_code);
        form.append('idempotency_key', submitToken.value);
        form.append('condition', condition.value);
        if (condition.value === 'incident') {
            form.append('has_incident', '1');
            form.append('incident_description', incidentDescription.value.trim());
            incidentPhotos.value.forEach((file) => form.append('photos[]', file, file.name));
        } else {
            form.append('has_incident', '0');
        }
        if (loc) {
            form.append('latitude', String(loc.latitude));
            form.append('longitude', String(loc.longitude));
            if (loc.accuracy != null) form.append('accuracy', String(loc.accuracy));
        }

        const { data } = await window.axios.post(props.routes.submit, form, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        applySessionFromResponse(data);
        const successMsg = data?.message || 'Hasil pemeriksaan berhasil disimpan.';

        // submit berhasil / idempoten -> token lama tidak dipakai lagi; reset untuk scan baru (sengaja)
        submitToken.value = null;
        resetConfirmation({ keepFeedback: true });
        setFeedback('success', successMsg);

        if (data?.completed || sessionState.value.status === 'completed') {
            scannerEnabled.value = false;
            scanPaused.value = true;
        } else {
            scannerEnabled.value = true;
            scanPaused.value = false;
        }
    } catch (err) {
        // token tetap sama agar retry/double-click request sama tidak membuat scan ganda
        setFeedback('error', extractError(err, 'Gagal mengirim hasil pemeriksaan. Tidak ada data yang disimpan.'));
    } finally {
        submitting.value = false;
        if (preview.value) scanPaused.value = true; else if (sessionState.value.status === 'active') scanPaused.value = false;
    }
}

function goBack() {
    stopCameraIfAny();
    router.visit(props.routes.show);
}

onBeforeUnmount(() => {
    stopCameraIfAny();
});
</script>

<template>
    <AppLayout>
        <div class="min-h-full bg-gray-50">
            <AppHeader
                title="Scan Titik Patroli"
                :description="`${sessionState.post_name} · ${sessionState.shift_name}`"
                :backUrl="routes.show"
                backLabel="Detail Sesi"
            />


            <main class="space-y-4 px-4 pb-8 pt-4">
                <section class="rounded-xl border border-[#E8E8EC] bg-white p-4">
                    <p class="text-sm font-semibold text-[#263746]">{{ sessionState.post_name }}</p>
                    <p class="mt-0.5 text-xs text-[#9299A2]">Regu {{ sessionState.team_name }} · {{ sessionState.work_date_label }} · {{ sessionState.shift_time }}</p>
                    <p class="mt-1 text-xs text-[#9299A2]">Titik terpenuhi: {{ fulfilledPoints }}/{{ requiredPoints }} · Progres: {{ progress }}% · Aktivitas scan: {{ totalScans }}</p>
                    <p class="mt-0.5 text-xs text-[#9299A2]">Status: {{ sessionState.status }}</p>
                </section>

                <div v-if="feedback.message" class="flex items-start gap-2 rounded-lg p-3 text-sm" :class="feedback.type === 'success' ? 'bg-emerald-50 text-emerald-700' : feedback.type === 'info' ? 'bg-blue-50 text-blue-700' : 'bg-red-50 text-red-700'">
                    <CheckCircleIcon v-if="feedback.type === 'success'" class="mt-0.5 h-5 w-5 shrink-0" />
                    <InformationCircleIcon v-else-if="feedback.type === 'info'" class="mt-0.5 h-5 w-5 shrink-0" />
                    <ExclamationTriangleIcon v-else class="mt-0.5 h-5 w-5 shrink-0" />
                    <p>{{ feedback.message }}</p>
                </div>

                <section v-if="sessionState.status === 'pending'" class="rounded-xl border border-[#E8E8EC] bg-white p-4 space-y-2">
                    <p class="text-sm text-[#263746]">
                        {{ sessionState.start_window?.state === 'after' ? 'Waktu untuk memulai sesi telah berakhir.' : (sessionState.start_window?.state === 'before' ? 'Sesi belum dapat dimulai sesuai jadwal.' : 'Sesi belum dimulai. Mulai patroli saat waktu shift untuk melakukan scan.') }}
                    </p>
                    <p v-if="sessionState.start_window?.start_at_wib" class="text-xs text-[#9299A2]">Jadwal mulai: {{ sessionState.start_window.start_at_wib }}</p>
                    <p v-if="sessionState.start_window?.end_at_wib" class="text-xs text-[#9299A2]">Batas waktu mulai: sebelum {{ sessionState.start_window.end_at_wib }}</p>
                    <p v-if="sessionState.start_window?.message && sessionState.start_window?.state !== 'open'" class="text-xs font-medium" :class="sessionState.start_window?.state === 'after' ? 'text-red-700' : 'text-amber-700'">{{ sessionState.start_window.message }}</p>
                    <button v-if="sessionState.start_window?.state !== 'after'" type="button" class="mt-1 flex w-full items-center justify-center rounded-lg bg-[#1D567F] px-4 py-3 text-sm font-semibold text-white disabled:opacity-50" :disabled="starting || !sessionState.start_window?.can_start_now" @click="startSession">
                        {{ starting ? 'Memulai...' : 'Mulai Patroli' }}
                    </button>
                </section>

                <template v-if="isActive">
                    <section v-if="!preview" class="space-y-3">
                        <PatrolQrScanner :active="scannerEnabled" :paused="scanPaused || previewLoading || submitting" @decoded="onDecoded" />
                        <p v-if="previewLoading" class="text-center text-xs text-[#9299A2]">Memvalidasi QR...</p>
                        <p class="text-center text-xs text-[#9299A2]">Titik yang sudah terpenuhi tetap dapat dipindai kembali untuk menambah aktivitas.</p>
                    </section>

                    <section v-else class="rounded-xl border border-[#E8E8EC] bg-white p-4 space-y-4">
                        <div class="flex items-start gap-2">
                            <MapPinIcon class="mt-0.5 h-5 w-5 text-[#1D567F]" />
                            <div>
                                <p class="text-xs text-[#9299A2]">Titik terdeteksi (belum disimpan)</p>
                                <p class="text-base font-semibold text-[#263746]">{{ preview.point?.name }}</p>
                                <p class="text-xs text-[#9299A2]">Urutan: {{ preview.point?.sequence }} · Kode tervalidasi</p>
                                <p v-if="preview.point?.already_scanned" class="mt-1 text-xs font-medium text-amber-700">Titik ini sudah dipindai {{ preview.point?.scan_count || 0 }} kali. Scan baru tetap dapat ditambahkan.</p>
                                <p v-else class="mt-1 text-xs font-medium text-emerald-700">Titik ini belum pernah dipindai.</p>
                            </div>
                        </div>

                        <div class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
                            Hasil pemeriksaan <strong>belum disimpan</strong>. Pilih kondisi dan tekan <strong>Kirim Hasil Pemeriksaan</strong> untuk mencatat scan.
                        </div>

                        <div>
                            <p class="text-sm font-medium text-[#263746]">Kondisi titik</p>
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border p-3 text-sm" :class="condition === 'safe' ? 'border-emerald-400 bg-emerald-50 text-emerald-700' : 'border-[#E8E8EC] text-[#263746]'">
                                    <input v-model="condition" type="radio" value="safe" class="sr-only" />
                                    <span class="font-medium">Aman</span>
                                </label>
                                <label class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border p-3 text-sm" :class="condition === 'incident' ? 'border-red-400 bg-red-50 text-red-700' : 'border-[#E8E8EC] text-[#263746]'">
                                    <input v-model="condition" type="radio" value="incident" class="sr-only" />
                                    <span class="font-medium">Ada Insiden</span>
                                </label>
                            </div>
                        </div>

                        <div v-if="condition === 'incident'" class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-[#263746]">Deskripsi kejadian <span class="text-red-500">*</span></label>
                                <textarea v-model="incidentDescription" rows="3" class="mt-1 w-full rounded-lg border border-[#E8E8EC] p-2 text-sm focus:border-[#38BDB5] focus:outline-none focus:ring-1 focus:ring-[#38BDB5]" placeholder="Jelaskan kejadian/temuan di titik ini"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-[#263746] mb-1">Foto bukti (opsional, langsung dari kamera HP)</label>
                                <PatrolCameraCapture ref="cameraRef" :max-photos="max_photos" @update:photos="onPhotosUpdate" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-2 pt-1">
                            <button type="button" class="flex w-full items-center justify-center rounded-lg bg-[#1D567F] px-4 py-3 text-sm font-semibold text-white disabled:opacity-50" :disabled="submitting" @click="submitInspection">
                                {{ submitting ? 'Mengirim...' : 'Kirim Hasil Pemeriksaan' }}
                            </button>
                            <button type="button" class="flex w-full items-center justify-center rounded-lg border border-[#E8E8EC] px-4 py-3 text-sm font-medium text-[#263746] disabled:opacity-50" :disabled="submitting" @click="cancelPreview">
                                Batal / Scan Ulang
                            </button>
                        </div>
                    </section>

                    <button v-if="sessionState.status === 'completed'" type="button" class="flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-3 text-sm font-semibold text-white" @click="goBack">
                        <QrCodeIcon class="h-5 w-5" /> Kembali ke Detail Sesi
                    </button>
                </template>

                <section v-if="sessionState.status === 'completed'" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                    <div class="flex items-center gap-2 text-emerald-700">
                        <CheckCircleIcon class="h-6 w-6" />
                        <p class="text-sm font-semibold">Sesi patroli selesai</p>
                    </div>
                    <p class="mt-1 text-xs text-emerald-700">Seluruh titik wajib unik telah memiliki minimal satu scan berhasil.</p>
                </section>

                <section v-if="['incomplete','not_started'].includes(sessionState.status)" class="rounded-xl border border-[#E8E8EC] bg-white p-4">
                    <p class="text-sm text-[#263746]">Sesi ini tidak dapat menerima scan pada status saat ini.</p>
                    <button type="button" class="mt-3 w-full rounded-lg border border-[#E8E8EC] px-4 py-2.5 text-sm font-medium text-[#1D567F]" @click="goBack">Kembali ke Detail</button>
                </section>
            </main>
        </div>
    </AppLayout>
</template>
