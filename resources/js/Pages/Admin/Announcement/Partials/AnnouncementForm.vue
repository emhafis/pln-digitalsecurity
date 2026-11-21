<script setup>
import InputLabel from '@/Components/Admin/InputLabel.vue';
import InputError from '@/Components/Admin/InputError.vue';
import TextInput from '@/Components/Admin/TextInput.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    existingImageUrl: { type: String, default: null },
});

const fileInput = ref(null);
const previewUrl = ref(null);

const displayImage = computed(() => {
    if (previewUrl.value) return previewUrl.value;
    if (props.form.remove_image) return null;
    return props.existingImageUrl;
});

watch(() => props.form.image, (file, oldFile) => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = file instanceof File ? URL.createObjectURL(file) : null;
    if (file instanceof File) props.form.remove_image = false;
    void oldFile;
});

function onImageChange(e) {
    props.form.image = e.target.files?.[0] ?? null;
}

function clearImage() {
    props.form.image = null;
    if (fileInput.value) fileInput.value.value = '';
}
</script>

<template>
    <div class="space-y-5">
        <div>
            <InputLabel value="Judul Pengumuman" :required="true" />
            <TextInput id="title" v-model="form.title" placeholder="Judul pengumuman" />
            <InputError :messages="form.errors?.title" />
        </div>

        <div>
            <InputLabel value="Isi Pengumuman" :required="true" />
            <textarea
                id="content"
                v-model="form.content"
                rows="6"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                placeholder="Tulis isi pengumuman..."
            />
            <InputError :messages="form.errors?.content" />
        </div>

        <div>
            <InputLabel value="Gambar (opsional)" />
            <div v-if="displayImage" class="mb-3">
                <img :src="displayImage" alt="Preview gambar pengumuman" class="h-40 w-full max-w-sm rounded-md border border-gray-200 object-cover" />
            </div>
            <input
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:text-gray-700 hover:file:bg-gray-200"
                @change="onImageChange"
            />
            <p class="mt-1 text-xs text-gray-500">JPG/PNG/WebP, maks 2 MB.</p>
            <div class="mt-2 flex flex-wrap gap-3 text-sm text-gray-600">
                <label v-if="existingImageUrl && !form.image" class="flex items-center gap-2">
                    <input v-model="form.remove_image" type="checkbox" class="rounded border-gray-300 text-blue-600" />
                    Hapus gambar saat disimpan
                </label>
                <button v-if="form.image" type="button" class="text-red-600 hover:text-red-700" @click="clearImage">Batalkan gambar baru</button>
            </div>
            <InputError :messages="form.errors?.image" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel value="Tanggal Terbit (opsional)" />
                <input
                    v-model="form.published_at"
                    type="datetime-local"
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
                <p class="mt-1 text-xs text-gray-500">Kosongkan untuk terbit segera.</p>
                <InputError :messages="form.errors?.published_at" />
            </div>
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                    Aktifkan pengumuman
                </label>
                <InputError :messages="form.errors?.is_active" />
            </div>
        </div>
    </div>
</template>
