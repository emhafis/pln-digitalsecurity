<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { ArrowUpTrayIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    accept: { type: String, default: 'image/*' },
    existingUrl: { type: String, default: '' },
    hint: { type: String, default: '' },
});

const model = defineModel({ default: null });

const input = ref(null);
const objectUrl = ref('');

const fileName = computed(() => model.value?.name ?? '');
const previewUrl = computed(() => objectUrl.value || props.existingUrl);
const hasFile = computed(() => Boolean(model.value) || Boolean(props.existingUrl));

watch(model, (file) => {
    if (objectUrl.value) {
        URL.revokeObjectURL(objectUrl.value);
    }

    objectUrl.value = file ? URL.createObjectURL(file) : '';
});

onBeforeUnmount(() => {
    if (objectUrl.value) {
        URL.revokeObjectURL(objectUrl.value);
    }
});

function pick() {
    input.value?.click();
}

function onChange(event) {
    model.value = event.target.files?.[0] ?? null;
}

function clear() {
    model.value = null;

    if (input.value) {
        input.value.value = '';
    }
}
</script>

<template>
    <div class="rounded-md border border-gray-300 bg-white p-3">
        <input
            ref="input"
            type="file"
            :accept="accept"
            class="hidden"
            @change="onChange"
        >

        <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-md border border-gray-200 bg-gray-50">
                <img
                    v-if="previewUrl"
                    :src="previewUrl"
                    alt="Pratinjau"
                    class="h-full w-full object-cover"
                >
                <ArrowUpTrayIcon v-else class="h-5 w-5 text-gray-400" />
            </div>

            <div class="min-w-0 flex-1">
                <p class="truncate text-xs text-gray-700">
                    {{ fileName || (props.existingUrl ? 'File tersimpan (klik Ganti untuk mengganti)' : 'Belum ada file') }}
                </p>
                <p v-if="hint" class="mt-0.5 text-[11px] text-gray-400">{{ hint }}</p>
            </div>

            <div class="flex shrink-0 items-center gap-1">
                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-2.5 py-1.5 text-xs text-gray-600 transition hover:bg-gray-50"
                    @click="pick"
                >
                    <ArrowUpTrayIcon class="h-3.5 w-3.5" />
                    {{ hasFile ? 'Ganti' : 'Pilih' }}
                </button>
                <button
                    v-if="model"
                    type="button"
                    class="inline-flex items-center justify-center rounded-md px-1.5 py-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
                    aria-label="Batalkan pilihan"
                    @click="clear"
                >
                    <XMarkIcon class="h-4 w-4" />
                </button>
            </div>
        </div>
    </div>
</template>
