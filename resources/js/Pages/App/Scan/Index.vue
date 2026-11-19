<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppHeader from '@/Components/App/AppHeader.vue';
import BottomNavigation from '@/Components/App/Home/BottomNavigation.vue';
import { ChevronLeftIcon } from '@heroicons/vue/24/outline';
import { Html5Qrcode } from 'html5-qrcode';

const status = ref('Meminta izin kamera...');
const error = ref('');
const processing = ref(false);
const scanned = ref(false);
let scanner = null;
let started = false;

async function startScanner() {
    error.value = '';
    status.value = 'Meminta izin kamera...';
    try {
        scanner = new Html5Qrcode('qr-reader');
        await scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            onScanSuccess,
            () => {}
        );
        started = true;
        status.value = 'Kamera aktif. Arahkan kamera ke QR Code APAR atau Hydrant.';
    } catch (e) {
        status.value = '';
        error.value = 'Kamera tidak tersedia atau izin ditolak. Periksa pengaturan browser Anda.';
    }
}

async function stopScanner() {
    if (scanner && started) {
        try {
            await scanner.stop();
            scanner.clear();
        } catch (e) {}
        started = false;
    }
}

async function onScanSuccess(decodedText) {
    if (scanned.value || processing.value) return;
    scanned.value = true;
    processing.value = true;
    status.value = 'Memproses QR Code...';
    await stopScanner();

    try {
        const res = await fetch('/app/qr/resolve', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ qr_code: decodedText }),
        });
        const data = await res.json();
        if (!res.ok) {
            error.value = data.message || 'QR Code tidak dikenali.';
            status.value = '';
            processing.value = false;
            scanned.value = false;
            await startScanner();
            return;
        }
        router.visit(data.url);
    } catch (e) {
        error.value = 'Terjadi kesalahan saat memproses QR Code.';
        status.value = '';
        processing.value = false;
        scanned.value = false;
        await startScanner();
    }
}

function retry() {
    error.value = '';
    scanned.value = false;
    processing.value = false;
    startScanner();
}

onMounted(() => {
    startScanner();
});

onBeforeUnmount(() => {
    stopScanner();
});
</script>

<template>
    <AppLayout>
        <div class="min-h-full bg-gray-50">
            <AppHeader title="Scan QR Code" description="Arahkan kamera ke QR Code APAR atau Hydrant" />

            <main class="space-y-4 px-4 pb-8 pt-4">
                <div class="overflow-hidden rounded-xl border border-[#E8E8EC] bg-white p-3">
                    <div id="qr-reader" class="w-full"></div>
                </div>

                <p v-if="status" class="text-center text-sm text-[#263746]">{{ status }}</p>

                <div v-if="error" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    <p>{{ error }}</p>
                    <button type="button" class="mt-2 rounded-lg bg-red-600 px-3 py-1.5 text-xs font-medium text-white" @click="retry">Coba Lagi</button>
                </div>
            </main>
        </div>

        <template #bottom>
            <BottomNavigation active="home" />
        </template>
    </AppLayout>
</template>
