<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Html5Qrcode } from 'html5-qrcode';
import { CameraIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    active: { type: Boolean, default: false },
    paused: { type: Boolean, default: false },
});

const emit = defineEmits(['decoded', 'error', 'camera-ready']);

const readerId = `qr-reader-${Math.random().toString(36).slice(2)}`;
const starting = ref(false);
const cameraError = ref('');
const isRunning = ref(false);
let scanner = null;
let lastDecodeAt = 0;
let mounted = false;

async function stopCamera() {
    if (!scanner) {
        isRunning.value = false;
        return;
    }
    try {
        if (isRunning.value) {
            await scanner.stop();
        }
        await scanner.clear();
    } catch (e) {
        // ignore stop/clear errors
    }
    isRunning.value = false;
}

async function startCamera() {
    if (!mounted || !props.active || props.paused || starting.value || isRunning.value) return;
    cameraError.value = '';

    if (!window.isSecureContext && window.location.hostname !== 'localhost') {
        cameraError.value = 'Kamera memerlukan koneksi aman (HTTPS).';
        emit('error', cameraError.value);
        return;
    }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        cameraError.value = 'Perangkat tidak mendukung akses kamera.';
        emit('error', cameraError.value);
        return;
    }

    starting.value = true;
    try {
        if (!scanner) {
            scanner = new Html5Qrcode(readerId);
        }
        const config = { fps: 10, qrbox: { width: 220, height: 220 }, aspectRatio: 1.0 };
        await scanner.start(
            { facingMode: 'environment' },
            config,
            (decodedText) => {
                if (props.paused || !props.active) return;
                const now = Date.now();
                if (now - lastDecodeAt < 1500) return;
                lastDecodeAt = now;
                emit('decoded', decodedText);
            },
            () => {}
        );
        isRunning.value = true;
        emit('camera-ready');
    } catch (err) {
        isRunning.value = false;
        const name = err?.name || '';
        const msg = String(err?.message || err || '');
        if (name === 'NotAllowedError' || /permission|denied/i.test(msg)) {
            cameraError.value = 'Izin kamera ditolak. Aktifkan izin kamera untuk memindai QR.';
        } else if (name === 'NotFoundError' || /not found|no camera/i.test(msg)) {
            cameraError.value = 'Kamera tidak tersedia pada perangkat ini.';
        } else {
            cameraError.value = 'Gagal mengakses kamera. Pastikan kamera tidak digunakan aplikasi lain.';
        }
        emit('error', cameraError.value);
        try { await scanner?.clear(); } catch (e) {}
    } finally {
        starting.value = false;
    }
}

watch(() => [props.active, props.paused], async ([active, paused]) => {
    if (!mounted) return;
    if (active && !paused) {
        await startCamera();
    } else {
        await stopCamera();
    }
});

onMounted(async () => {
    mounted = true;
    if (props.active && !props.paused) {
        await startCamera();
    }
});

onBeforeUnmount(async () => {
    mounted = false;
    await stopCamera();
    scanner = null;
});

defineExpose({ stopCamera, startCamera });
</script>

<template>
    <div class="rounded-xl border border-[#E8E8EC] bg-white p-3">
        <div class="mb-2 flex items-center gap-2 text-sm font-medium text-[#263746]">
            <CameraIcon class="h-5 w-5 text-[#1D567F]" />
            <span>Pindai QR Titik Patroli</span>
        </div>

        <div v-if="cameraError" class="mb-3 flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700">
            <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0" />
            <p>{{ cameraError }}</p>
        </div>

        <div class="relative overflow-hidden rounded-lg bg-gray-900" style="min-height: 260px;">
            <div :id="readerId" class="w-full"></div>
            <div v-if="!active" class="absolute inset-0 flex items-center justify-center bg-gray-100 text-sm text-[#9299A2]">
                Kamera tidak aktif.
            </div>
            <div v-else-if="paused" class="absolute inset-0 flex items-center justify-center bg-black/50 text-sm font-medium text-white">
                Memproses scan...
            </div>
            <div v-else-if="starting" class="absolute inset-0 flex items-center justify-center bg-black/40 text-sm text-white">
                Mengaktifkan kamera...
            </div>
        </div>
        <p class="mt-2 text-xs text-[#9299A2]">Arahkan kamera ke kode QR titik patroli. Pastikan pencahayaan cukup.</p>
    </div>
</template>
