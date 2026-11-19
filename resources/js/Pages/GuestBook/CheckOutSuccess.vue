<script setup>
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Check-out Berhasil" />
    <div class="min-h-screen bg-gray-50 px-4 py-10">
        <div class="mx-auto max-w-md rounded-xl border border-gray-200 bg-white p-6 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-50 text-green-600">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
            </div>
            <h1 class="mt-4 text-lg font-semibold text-gray-800">Check-out Berhasil</h1>
            <p class="mt-2 text-sm text-gray-600">Kunjungan Anda telah tercatat selesai. Terima kasih.</p>
            <p class="mt-3 text-xs text-gray-500">Anda dapat menutup halaman ini.</p>
        </div>
    </div>
</template>
