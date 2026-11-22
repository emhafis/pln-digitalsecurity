<script setup>
import { onMounted, ref } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import { compressImage, validateImageFile } from '@/Utils/imageCompressor';

const props = defineProps({
    qrCode: { type: String, required: true },
    unit: { type: Object, required: true },
});

const form = useForm({
    full_name: '',
    phone_number: '',
    company_name: '',
    position: '',
    visit_purpose: '',
    person_to_meet: '',
    vehicle_plate_number: '',
    consent_given: false,
    selfie: null,
    signature: '',
});

const canvasRef = ref(null);
const drawing = ref(false);
const photoProcessing = ref(false);
const photoError = ref('');

function getPos(e) {
    const canvas = canvasRef.value;
    const rect = canvas.getBoundingClientRect();
    const touch = e.touches ? e.touches[0] : e;
    return { x: touch.clientX - rect.left, y: touch.clientY - rect.top };
}
function startDraw(e) { drawing.value = true; const c = canvasRef.value.getContext('2d'); const p = getPos(e); c.beginPath(); c.moveTo(p.x, p.y); }
function draw(e) { if (!drawing.value) return; e.preventDefault(); const c = canvasRef.value.getContext('2d'); const p = getPos(e); c.lineTo(p.x, p.y); c.strokeStyle = '#111'; c.lineWidth = 2; c.lineCap = 'round'; c.stroke(); }
function endDraw() { drawing.value = false; }
function clearSignature() { const canvas = canvasRef.value; const c = canvas.getContext('2d'); c.clearRect(0,0,canvas.width,canvas.height); form.signature = ''; }
function syncSignature() { const canvas = canvasRef.value; const blank = document.createElement('canvas'); blank.width = canvas.width; blank.height = canvas.height; if (canvas.toDataURL() !== blank.toDataURL()) { form.signature = canvas.toDataURL('image/png'); } else { form.signature = ''; } }
async function onSelfie(e) {
    const file = e.target.files?.[0] || null;
    photoError.value = '';
    form.clearErrors('selfie');
    if (!file) {
        form.selfie = null;
        return;
    }

    const validationError = validateImageFile(file, { maxInputBytes: 10 * 1024 * 1024 });
    if (validationError) {
        form.selfie = null;
        photoError.value = validationError;
        e.target.value = '';
        return;
    }

    photoProcessing.value = true;
    try {
        const result = await compressImage(file);
        form.selfie = result.file;
    } catch (err) {
        form.selfie = null;
        photoError.value = err instanceof Error ? err.message : 'Gagal memproses foto selfie.';
        e.target.value = '';
    } finally {
        photoProcessing.value = false;
    }
}

function submit() {
    if (photoProcessing.value) return;
    syncSignature();
    form.post(`/guest-book/${props.qrCode}`, { forceFormData: true, preserveScroll: true });
}

onMounted(() => {
    const canvas = canvasRef.value;
    if (canvas) {
        const ratio = window.devicePixelRatio || 1;
        const w = canvas.offsetWidth || 320;
        const h = 160;
        canvas.width = w * ratio; canvas.height = h * ratio;
        canvas.style.width = w + 'px'; canvas.style.height = h + 'px';
        canvas.getContext('2d').scale(ratio, ratio);
    }
});
</script>

<template>
    <Head title="Buku Tamu Digital" />
    <div class="min-h-screen bg-gray-50 px-4 py-6">
        <div class="mx-auto max-w-lg">
            <div class="mb-4 rounded-xl bg-[#1D567F] p-5 text-white">
                <p class="text-xs text-white/80">Buku Tamu Digital</p>
                <h1 class="mt-1 text-xl font-semibold">Form Kunjungan</h1>
                <div class="mt-3 rounded-lg bg-white/10 p-3 text-sm">
                    <p class="text-white/70">Unit Tujuan</p>
                    <p class="font-semibold">{{ unit.name }}</p>
                    <p class="text-xs text-white/80">{{ unit.code || '-' }}</p>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div v-if="Object.keys(form.errors).length" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    <p v-for="(msg,k) in form.errors" :key="k">{{ msg }}</p>
                </div>

                <section class="space-y-3 rounded-xl border border-gray-200 bg-white p-4">
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Nama Lengkap *</label>
                        <input v-model="form.full_name" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Nomor HP *</label>
                        <input v-model="form.phone_number" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Instansi / Perusahaan *</label>
                        <input v-model="form.company_name" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Jabatan *</label>
                        <input v-model="form.position" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Tujuan Kunjungan *</label>
                        <textarea v-model="form.visit_purpose" rows="3" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Orang yang Ingin Ditemui *</label>
                        <input v-model="form.person_to_meet" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Nomor Kendaraan (opsional)</label>
                        <input v-model="form.vehicle_plate_number" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                    </div>
                </section>

                <section class="space-y-3 rounded-xl border border-gray-200 bg-white p-4">
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Selfie (opsional)</label>
                        <input type="file" accept="image/*" capture="user" class="block w-full text-sm" @change="onSelfie" />
                        <p class="mt-1 text-[11px] text-gray-400">Semua format gambar didukung (termasuk HEIC), maks 10 MB. Foto akan dikonversi ke JPG dan dikompresi otomatis ke ukuran kecil sebelum dikirim.</p>
                        <p v-if="photoProcessing" class="mt-1 text-[11px] text-[#1D567F]">Memproses foto...</p>
                        <p v-if="photoError" class="mt-1 text-[11px] text-red-600">{{ photoError }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Tanda Tangan (opsional)</label>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-2">
                            <canvas ref="canvasRef" class="w-full touch-none bg-white" style="height:160px"
                                @mousedown="startDraw" @mousemove="draw" @mouseup="endDraw" @mouseleave="endDraw"
                                @touchstart.prevent="startDraw" @touchmove="draw" @touchend="endDraw"></canvas>
                        </div>
                        <button type="button" class="mt-2 text-xs font-medium text-[#1D567F]" @click="clearSignature">Hapus tanda tangan</button>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-4">
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input v-model="form.consent_given" type="checkbox" class="mt-1" />
                        <span>Saya menyetujui penggunaan data kunjungan ini untuk keperluan pencatatan dan keamanan unit. *</span>
                    </label>
                </section>

                <button type="submit" :disabled="form.processing || photoProcessing" class="flex w-full items-center justify-center rounded-xl bg-[#1D567F] px-4 py-3 text-sm font-semibold text-white disabled:opacity-60">
                    {{ photoProcessing ? 'Memproses foto...' : (form.processing ? 'Mengirim...' : 'Kirim Kunjungan') }}
                </button>
            </form>
        </div>
    </div>
</template>
