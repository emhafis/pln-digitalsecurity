<script setup>
import { ref } from 'vue';
import { ArrowUpTrayIcon, DocumentIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    modelValue: { type: [Object, null], default: null },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);
const inputRef = ref(null);

function onFileChange(event) {
    const file = event.target.files && event.target.files[0] ? event.target.files[0] : null;
    emit('update:modelValue', file);
}

function removeFile() {
    emit('update:modelValue', null);
    if (inputRef.value) {
        inputRef.value.value = '';
    }
}

function openPicker() {
    inputRef.value?.click();
}
</script>

<template>
    <div>
        <label class="mb-1.5 block text-sm font-medium text-[#263746]">Lampiran Bukti</label>
        <input
            ref="inputRef"
            type="file"
            accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
            class="hidden"
            @change="onFileChange"
        />

        <div
            v-if="!modelValue"
            class="flex cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-[#E5E7EB] bg-white px-4 py-6 text-center"
            @click="openPicker"
        >
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-50 text-[#9299A2]">
                <ArrowUpTrayIcon class="h-5 w-5" />
            </div>
            <p class="mt-2 text-sm font-medium text-[#263746]">Unggah Bukti</p>
            <p class="mt-1 text-xs text-[#9299A2]">Format: JPG, PNG, PDF</p>
        </div>

        <div v-else class="flex items-center justify-between rounded-lg border border-[#E5E7EB] bg-white px-3 py-3">
            <div class="flex min-w-0 items-center gap-2">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-gray-50 text-[#1D567F]">
                    <DocumentIcon class="h-5 w-5" />
                </div>
                <p class="truncate text-sm text-[#263746]">{{ modelValue.name }}</p>
            </div>
            <button type="button" class="ml-2 flex h-8 w-8 items-center justify-center rounded-full text-[#9299A2] hover:bg-gray-50" @click="removeFile" aria-label="Hapus file">
                <XMarkIcon class="h-5 w-5" />
            </button>
        </div>

        <p v-if="error" class="mt-1.5 text-xs text-[#DC5555]">{{ error }}</p>
    </div>
</template>
