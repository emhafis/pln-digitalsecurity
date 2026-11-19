<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    message: { type: String, default: 'QR Code tidak valid atau tidak dapat digunakan.' },
});
</script>

<template>
    <Head title="QR Tidak Valid" />
    <div class="min-h-screen bg-gray-50 px-4 py-10">
        <div class="mx-auto max-w-md rounded-xl border border-gray-200 bg-white p-6 text-center">
            <h1 class="text-lg font-semibold text-gray-800">Tautan Tidak Valid</h1>
            <p class="mt-2 text-sm text-gray-600">{{ message }}</p>
            <p class="mt-3 text-xs text-gray-500">Silakan scan ulang QR yang tersedia di lokasi unit.</p>
        </div>
    </div>
</template>
