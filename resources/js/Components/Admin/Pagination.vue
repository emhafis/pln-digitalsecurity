<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    meta: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['change']);

const pageInput = ref(String(props.meta.current_page));

watch(
    () => props.meta.current_page,
    (value) => {
        pageInput.value = String(value);
    },
);

function goToPage() {
    const parsed = Number.parseInt(pageInput.value, 10);
    const last = props.meta.last_page;

    if (Number.isNaN(parsed)) {
        pageInput.value = String(props.meta.current_page);
        return;
    }

    const page = Math.min(Math.max(parsed, 1), last);
    pageInput.value = String(page);

    if (page !== props.meta.current_page) {
        emit('change', page);
    }
}
</script>

<template>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-xs text-gray-500">
            Menampilkan {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} dari {{ meta.total }} data
        </p>
        <div class="flex items-center gap-2">
            <button
                type="button"
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="meta.current_page <= 1"
                @click="emit('change', meta.current_page - 1)"
            >
                ‹ Sebelumnya
            </button>
            <span class="flex items-center gap-1.5 text-xs text-gray-500">
                Halaman
                <input
                    v-model="pageInput"
                    type="number"
                    min="1"
                    :max="meta.last_page"
                    class="w-14 rounded-md border border-gray-300 bg-white px-2 py-1.5 text-center text-xs text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                    @keyup.enter="goToPage"
                    @blur="goToPage"
                />
                dari {{ meta.last_page }}
            </span>
            <button
                type="button"
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="meta.current_page >= meta.last_page"
                @click="emit('change', meta.current_page + 1)"
            >
                Berikutnya ›
            </button>
        </div>
    </div>
</template>
