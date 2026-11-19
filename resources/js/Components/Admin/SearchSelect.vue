<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { MagnifyingGlassIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Pilih...' },
    noneLabel: { type: String, default: '' },
    searchPlaceholder: { type: String, default: 'Cari...' },
});

const model = defineModel({ type: [String, Number], default: '' });

const open = ref(false);
const search = ref('');
const root = ref(null);

const selectedLabel = computed(() => {
    if (model.value === '' || model.value === null) {
        return '';
    }
    return props.options.find((o) => String(o.value) === String(model.value))?.label ?? '';
});

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) {
        return props.options;
    }
    return props.options.filter((o) => String(o.label ?? '').toLowerCase().includes(q));
});

function select(value) {
    model.value = value;
    open.value = false;
    search.value = '';
}

function toggle() {
    open.value = !open.value;
    search.value = '';
}

function onDocumentClick(event) {
    if (root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}

watch(open, (value) => {
    if (value) {
        document.addEventListener('click', onDocumentClick);
    } else {
        document.removeEventListener('click', onDocumentClick);
    }
});

onBeforeUnmount(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div ref="root" class="relative w-full">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-left text-sm focus:outline-none focus:ring-1 focus:ring-[#155e75]"
            @click="toggle"
        >
            <span :class="selectedLabel ? 'text-slate-900' : 'text-slate-400'">
                {{ selectedLabel || placeholder }}
            </span>
            <ChevronDownIcon
                class="h-4 w-4 shrink-0 text-slate-400 transition-transform"
                :class="{ 'rotate-180': open }"
            />
        </button>

        <div
            v-if="open"
            class="absolute left-0 right-0 z-20 mt-1 rounded-md border border-gray-200 bg-white shadow-lg"
        >
            <div class="relative border-b border-gray-200">
                <MagnifyingGlassIcon class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                    v-model="search"
                    type="text"
                    :placeholder="searchPlaceholder"
                    class="w-full rounded-t-md py-2 pl-9 pr-3 text-sm focus:outline-none"
                    autofocus
                >
            </div>
            <ul class="max-h-60 overflow-y-auto py-1">
                <li v-if="noneLabel">
                    <button
                        type="button"
                        class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100"
                        :class="{ 'bg-blue-50 font-medium text-blue-700': model === '' }"
                        @click="select('')"
                    >
                        {{ noneLabel }}
                    </button>
                </li>
                <li v-for="option in filtered" :key="option.value">
                    <button
                        type="button"
                        class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100"
                        :class="{ 'bg-blue-50 font-medium text-blue-700': String(model) === String(option.value) }"
                        @click="select(option.value)"
                    >
                        {{ option.label }}
                    </button>
                </li>
                <li v-if="filtered.length === 0" class="px-3 py-2 text-sm text-slate-400">
                    Tidak ada data.
                </li>
            </ul>
        </div>
    </div>
</template>
