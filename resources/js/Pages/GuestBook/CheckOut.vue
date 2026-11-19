<script setup>
import { computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    qrCode: { type: String, required: true },
    unit: { type: Object, required: true },
});

const page = usePage();
const candidates = computed(() => page.props.flash?.candidates || []);
const flashedPhone = computed(() => page.props.flash?.phone_number || '');

const lookupForm = useForm({
    phone_number: flashedPhone.value || '',
});

const confirmForm = useForm({
    phone_number: '',
    guest_visit_id: null,
});

function lookup() {
    lookupForm.post(`/guest-book/checkout/${props.qrCode}/lookup`, {
        preserveScroll: true,
        onSuccess: () => {
            // candidates come via flash
        },
    });
}

function confirm(visitId) {
    confirmForm.phone_number = flashedPhone.value || lookupForm.phone_number;
    confirmForm.guest_visit_id = visitId;
    confirmForm.post(`/guest-book/checkout/${props.qrCode}/confirm`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Check-out Tamu" />
    <div class="min-h-screen bg-gray-50 px-4 py-6">
        <div class="mx-auto max-w-lg">
            <div class="mb-4 rounded-xl bg-[#1D567F] p-5 text-white">
                <p class="text-xs text-white/80">Buku Tamu Digital</p>
                <h1 class="mt-1 text-xl font-semibold">Check-out Kunjungan</h1>
                <div class="mt-3 rounded-lg bg-white/10 p-3 text-sm">
                    <p class="text-white/70">Unit</p>
                    <p class="font-semibold">{{ unit.name }}</p>
                    <p class="text-xs text-white/80">{{ unit.code || '-' }}</p>
                </div>
            </div>

            <div v-if="Object.keys(lookupForm.errors).length || Object.keys(confirmForm.errors).length" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <p v-for="(msg,k) in { ...lookupForm.errors, ...confirmForm.errors }" :key="k">{{ msg }}</p>
            </div>

            <section class="mb-4 rounded-xl border border-gray-200 bg-white p-4">
                <h2 class="mb-2 text-sm font-semibold text-gray-800">Cari Kunjungan</h2>
                <p class="mb-3 text-xs text-gray-500">Masukkan nomor HP yang digunakan saat check-in.</p>
                <form @submit.prevent="lookup" class="space-y-3">
                    <div>
                        <label class="mb-1 block text-xs text-gray-500">Nomor HP *</label>
                        <input v-model="lookupForm.phone_number" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" />
                    </div>
                    <button type="submit" :disabled="lookupForm.processing" class="flex w-full items-center justify-center rounded-xl bg-[#1D567F] px-4 py-3 text-sm font-semibold text-white disabled:opacity-60">
                        {{ lookupForm.processing ? 'Mencari...' : 'Cari Kunjungan' }}
                    </button>
                </form>
            </section>

            <section v-if="candidates.length" class="rounded-xl border border-gray-200 bg-white p-4">
                <h2 class="mb-2 text-sm font-semibold text-gray-800">Konfirmasi Check-out</h2>
                <p class="mb-3 text-xs text-gray-500" v-if="candidates.length === 1">Pastikan data kunjungan berikut sudah benar.</p>
                <p class="mb-3 text-xs text-gray-500" v-else>Pilih kunjungan yang sesuai.</p>

                <div class="space-y-3">
                    <div v-for="c in candidates" :key="c.id" class="rounded-lg border border-gray-200 p-3">
                        <p class="text-sm font-semibold text-gray-800">{{ c.full_name }}</p>
                        <p class="text-xs text-gray-600">{{ c.company_name }}</p>
                        <p class="mt-1 text-xs text-gray-500">Check-in: {{ c.check_in_at || '-' }}</p>
                        <button type="button" :disabled="confirmForm.processing" class="mt-3 flex w-full items-center justify-center rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white disabled:opacity-60" @click="confirm(c.id)">
                            {{ confirmForm.processing && confirmForm.guest_visit_id === c.id ? 'Memproses...' : 'Konfirmasi Telah Pulang' }}
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
