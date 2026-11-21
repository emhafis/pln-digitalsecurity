<script setup>
import { ref, watch } from 'vue';
import SearchInput from '@/Components/Admin/SearchInput.vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    status: { type: String, default: '' },
    count: { type: Number, default: 0 },
});

const emit = defineEmits(['update:modelValue', 'update:status']);
const search = ref(props.modelValue);
const status = ref(props.status);

watch(() => props.modelValue, (v) => { search.value = v; });
watch(() => props.status, (v) => { status.value = v; });
watch(search, (v) => emit('update:modelValue', v));
watch(status, (v) => emit('update:status', v));
</script>

<template>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex w-full flex-col gap-3 sm:max-w-xl sm:flex-row">
            <div class="w-full sm:max-w-xs">
                <SearchInput v-model="search" placeholder="Cari pengumuman..." />
            </div>
            <select
                v-model="status"
                class="rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
                <option value="">Semua status</option>
                <option value="active">Aktif</option>
                <option value="inactive">Nonaktif</option>
            </select>
        </div>
        <p class="text-xs text-gray-500">{{ count }} pengumuman</p>
    </div>
</template>
