<script setup>
import { ref, watch, computed, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CheckCircleIcon, ExclamationTriangleIcon, XCircleIcon, XMarkIcon } from '@heroicons/vue/24/outline';

const page = usePage();

const visible = ref(false);
const type = ref('success');
const message = ref('');
let timer = null;

const flash = computed(() => page.props.flash ?? {});

const classes = {
    success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    warning: 'border-amber-200 bg-amber-50 text-amber-800',
    error: 'border-red-200 bg-red-50 text-red-700',
};

function show() {
    if (flash.value.success) {
        type.value = 'success';
        message.value = flash.value.success;
    } else if (flash.value.warning) {
        type.value = 'warning';
        message.value = flash.value.warning;
    } else if (flash.value.error) {
        type.value = 'error';
        message.value = flash.value.error;
    } else {
        visible.value = false;
        return;
    }

    visible.value = true;
    clearTimeout(timer);
    timer = setTimeout(() => {
        visible.value = false;
    }, 10000);
}

function dismiss() {
    visible.value = false;
    clearTimeout(timer);
}

watch(() => [flash.value.success, flash.value.warning, flash.value.error], show, { immediate: true });

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div
        v-if="visible && message"
        class="flex items-center gap-3 rounded-md border px-4 py-3 text-sm font-medium"
        :class="classes[type]"
        role="status"
    >
        <CheckCircleIcon v-if="type === 'success'" class="h-5 w-5 shrink-0" />
        <ExclamationTriangleIcon v-else-if="type === 'warning'" class="h-5 w-5 shrink-0" />
        <XCircleIcon v-else class="h-5 w-5 shrink-0" />
        <p class="min-w-0 flex-1">{{ message }}</p>
        <button
            type="button"
            class="shrink-0 rounded-md p-1 transition hover:bg-black/5"
            aria-label="Tutup pesan"
            @click="dismiss"
        >
            <XMarkIcon class="h-4 w-4" />
        </button>
    </div>
</template>
