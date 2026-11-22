<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppHeader from '@/Components/App/AppHeader.vue';
import BottomNavigation from '@/Components/App/Home/BottomNavigation.vue';
import { compressImage, validateImageFile } from '@/Utils/imageCompressor';

const props = defineProps({
    apar: { type: Object, required: true },
    checklist: { type: Array, default: () => [] },
    maxPhotos: { type: Number, default: 5 },
});

const form = useForm({
    items: (props.checklist || []).map((item) => ({
        item_name: item.item_name,
        item_label: item.item_label,
        sort_order: item.sort_order,
        is_good: null,
        note: '',
    })),
    notes: '',
    photos: [],
});

const anyNotGood = computed(() => form.items.some((item) => item.is_good === false || item.is_good === '0' || item.is_good === 0));
const photoProcessing = ref(false);
const photoError = ref('');

async function onPhotosChange(e) {
    const files = Array.from(e.target.files || []);
    photoError.value = '';
    form.clearErrors('photos');
    if (!files.length) {
        form.photos = [];
        return;
    }

    photoProcessing.value = true;
    const compressed = [];
    try {
        for (const file of files) {
            const validationError = validateImageFile(file, { maxInputBytes: 10 * 1024 * 1024 });
            if (validationError) throw new Error(validationError);
            const result = await compressImage(file, { maxWidth: 1280, maxHeight: 1280, quality: 0.72, maxOutputBytes: 1024 * 1024 });
            compressed.push(result.file);
        }
        form.photos = compressed;
    } catch (err) {
        form.photos = [];
        photoError.value = err instanceof Error ? err.message : 'Gagal memproses foto inspeksi.';
        e.target.value = '';
    } finally {
        photoProcessing.value = false;
    }
}

function submit() {
    if (photoProcessing.value) return;
    form.transform((data) => ({
        ...data,
        items: data.items.map((item) => ({
            item_name: item.item_name,
            is_good: item.is_good === true || item.is_good === '1' || item.is_good === 1,
            note: item.note,
        })),
    })).post(`/app/apar/${props.apar.id}/inspect`, {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>

<template>
    <AppLayout>
        <div class="min-h-full bg-gray-50">
            <AppHeader title="Inspeksi APAR" :description="`${apar.apar_code} - ${apar.placement}`" :backUrl="`/app/apar/${apar.id}`" backLabel="Detail APAR" />

            <main class="space-y-4 px-4 pb-28 pt-4">
                <div v-if="Object.keys(form.errors).length" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    <p v-for="(msg, key) in form.errors" :key="key">{{ msg }}</p>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <section class="rounded-xl border border-[#E8E8EC] bg-white p-4">
                        <h2 class="mb-1 text-sm font-semibold text-[#263746]">Checklist Inspeksi</h2>
                        <p class="mb-4 text-xs text-[#9299A2]">Pilih kondisi setiap item. Waktu inspeksi dicatat otomatis oleh sistem saat disimpan.</p>

                        <div class="space-y-4">
                            <div v-for="(item, index) in form.items" :key="item.item_name" class="rounded-lg border border-[#E8E8EC] p-3">
                                <div class="mb-2 flex items-start justify-between gap-3">
                                    <p class="text-sm font-medium text-[#263746]">{{ item.sort_order }}. {{ item.item_label }}</p>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <button
                                        type="button"
                                        class="flex h-12 items-center justify-center gap-2 rounded-xl border text-sm font-semibold transition"
                                        :class="form.items[index].is_good === true ? 'border-green-600 bg-green-600 text-white' : 'border-[#E8E8EC] bg-white text-[#263746] hover:bg-green-50'"
                                        @click="form.items[index].is_good = true"
                                    >
                                        ? Baik
                                    </button>
                                    <button
                                        type="button"
                                        class="flex h-12 items-center justify-center gap-2 rounded-xl border text-sm font-semibold transition"
                                        :class="form.items[index].is_good === false ? 'border-red-600 bg-red-600 text-white' : 'border-[#E8E8EC] bg-white text-[#263746] hover:bg-red-50'"
                                        @click="form.items[index].is_good = false"
                                    >
                                        ? Tidak Baik
                                    </button>
                                </div>
                                <div v-if="form.items[index].is_good === false" class="mt-2">
                                    <label class="mb-1 block text-xs text-[#9299A2]">Catatan item (opsional)</label>
                                    <input v-model="form.items[index].note" type="text" maxlength="1000" class="w-full rounded-lg border border-[#E8E8EC] px-3 py-2 text-sm text-[#263746] focus:border-[#1D567F] focus:outline-none" placeholder="Keterangan kondisi item" />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-[#E8E8EC] bg-white p-4">
                        <h2 class="mb-1 text-sm font-semibold text-[#263746]">Catatan Inspeksi</h2>
                        <p class="mb-2 text-xs text-[#9299A2]">
                            <span v-if="anyNotGood" class="font-medium text-red-600">Wajib diisi karena ada item tidak baik.</span>
                            <span v-else>Opsional jika semua item baik.</span>
                        </p>
                        <textarea
                            v-model="form.notes"
                            rows="4"
                            maxlength="2000"
                            class="w-full rounded-lg border border-[#E8E8EC] px-3 py-2 text-sm text-[#263746] focus:border-[#1D567F] focus:outline-none"
                            placeholder="Tulis catatan hasil inspeksi"
                        ></textarea>
                    </section>

                    <section class="rounded-xl border border-[#E8E8EC] bg-white p-4">
                        <h2 class="mb-1 text-sm font-semibold text-[#263746]">Foto Inspeksi</h2>
                        <p class="mb-3 text-xs text-[#9299A2]">Opsional, maksimal {{ maxPhotos }} foto. Semua format gambar didukung (termasuk HEIC), maks 10 MB per foto, dan akan dikonversi ke JPG terkompresi sebelum dikirim.</p>
                        <input
                            type="file"
                            accept="image/*"
                            multiple
                            class="block w-full text-sm text-[#263746]"
                            @change="onPhotosChange"
                        />
                        <p v-if="photoProcessing" class="mt-2 text-xs text-[#1D567F]">Memproses foto...</p>
                        <p v-if="form.photos.length" class="mt-2 text-xs text-[#9299A2]">{{ form.photos.length }} foto siap dikirim.</p>
                        <p v-if="photoError" class="mt-2 text-xs text-red-600">{{ photoError }}</p>
                    </section>

                    <button
                        type="submit"
                        :disabled="form.processing || photoProcessing"
                        class="flex w-full items-center justify-center rounded-xl bg-[#1D567F] px-4 py-3 text-sm font-semibold text-white disabled:opacity-60"
                    >
                        {{ photoProcessing ? 'Memproses foto...' : (form.processing ? 'Menyimpan...' : 'Simpan Inspeksi') }}
                    </button>
                </form>
            </main>
        </div>

        <template #bottom>
            <BottomNavigation active="apar" />
        </template>
    </AppLayout>
</template>
