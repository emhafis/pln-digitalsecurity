<script setup>
import { onBeforeUnmount, ref } from 'vue';
import { compressImage, formatBytes, validateImageFile } from '@/Utils/imageCompressor';
import { CameraIcon, ExclamationTriangleIcon, PhotoIcon, TrashIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    maxPhotos: { type: Number, default: 5 },
});

const emit = defineEmits(['update:photos']);

const fileInputRef = ref(null);
const cameraError = ref('');
const processing = ref(false);
const photos = ref([]); // { id, file, previewUrl, size }

function setPhotos(next) {
    photos.value = next;
    emit('update:photos', next.map((p) => p.file));
}

function openCamera() {
    if (processing.value) return;
    if (photos.value.length >= props.maxPhotos) {
        cameraError.value = `Maksimal ${props.maxPhotos} foto insiden.`;
        return;
    }
    cameraError.value = '';
    fileInputRef.value?.click();
}

async function onFileChange(event) {
    const input = event.target;
    const file = input.files && input.files[0] ? input.files[0] : null;
    input.value = '';
    if (!file) return;

    if (photos.value.length >= props.maxPhotos) {
        cameraError.value = `Maksimal ${props.maxPhotos} foto insiden.`;
        return;
    }

    const validationError = validateImageFile(file, { maxInputBytes: 10 * 1024 * 1024 });
    if (validationError) {
        cameraError.value = validationError;
        return;
    }

    processing.value = true;
    cameraError.value = '';
    try {
        const result = await compressImage(file);
        const previewUrl = URL.createObjectURL(result.blob);
        const item = { id: `${Date.now()}-${Math.random().toString(36).slice(2)}`, file: result.file, previewUrl, size: result.compressedSize };
        setPhotos([...photos.value, item]);
    } catch (err) {
        cameraError.value = err instanceof Error ? err.message : 'Gagal memproses foto.';
    } finally {
        processing.value = false;
    }
}

function removePhoto(id) {
    const target = photos.value.find((p) => p.id === id);
    if (target?.previewUrl) URL.revokeObjectURL(target.previewUrl);
    setPhotos(photos.value.filter((p) => p.id !== id));
}

function clearAll() {
    photos.value.forEach((p) => { if (p.previewUrl) URL.revokeObjectURL(p.previewUrl); });
    setPhotos([]);
    cameraError.value = '';
}

function stopCamera() {}

function startCamera() {
    openCamera();
}

onBeforeUnmount(() => {
    photos.value.forEach((p) => { if (p.previewUrl) URL.revokeObjectURL(p.previewUrl); });
});

defineExpose({ stopCamera, startCamera, openCamera, clearAll });
</script>

<template>
    <div class="space-y-3">
        <div v-if="cameraError" class="flex items-start gap-2 rounded-lg bg-red-50 p-3 text-sm text-red-700">
            <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0" />
            <p>{{ cameraError }}</p>
        </div>

        <input
            ref="fileInputRef"
            type="file"
            accept="image/*"
            capture="environment"
            class="hidden"
            @change="onFileChange"
        />

        <button
            type="button"
            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-[#1D567F] px-3 py-3 text-sm font-medium text-white disabled:opacity-50"
            :disabled="processing || photos.length >= maxPhotos"
            @click="openCamera"
        >
            <CameraIcon class="h-5 w-5" />
            {{ processing ? 'Memproses...' : 'Buka Kamera' }}
        </button>
        <p class="text-xs text-[#9299A2]">Foto diambil langsung dari kamera HP. Semua format gambar didukung (termasuk HEIC), maksimal 10 MB, dan akan dikonversi ke JPG terkompresi ukuran kecil sebelum dikirim. Maksimal {{ maxPhotos }} foto. {{ photos.length }}/{{ maxPhotos }} terpakai.</p>

        <div v-if="photos.length" class="space-y-2">
            <p class="text-xs font-medium text-[#263746]">Foto yang akan dikirim</p>
            <div class="grid grid-cols-3 gap-2">
                <div v-for="p in photos" :key="p.id" class="relative overflow-hidden rounded-lg border border-[#E8E8EC] bg-gray-50">
                    <img :src="p.previewUrl" alt="Preview foto insiden" class="h-24 w-full object-cover" />
                    <button type="button" class="absolute right-1 top-1 rounded-full bg-white/90 p-1 shadow" @click="removePhoto(p.id)" title="Hapus foto">
                        <TrashIcon class="h-4 w-4 text-red-600" />
                    </button>
                    <div class="px-1 py-0.5 text-[10px] text-[#9299A2]">{{ formatBytes(p.size) }}</div>
                </div>
            </div>
        </div>
        <div v-else class="flex items-center gap-2 rounded-lg border border-dashed border-[#E8E8EC] p-3 text-xs text-[#9299A2]">
            <PhotoIcon class="h-5 w-5" />
            <span>Belum ada foto. Klik "Buka Kamera" untuk mengambil foto bila diperlukan.</span>
        </div>
    </div>
</template>
