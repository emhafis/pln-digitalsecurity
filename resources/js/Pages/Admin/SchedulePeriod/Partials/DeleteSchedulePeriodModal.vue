<script setup>
import Button from '@/Components/Admin/Button.vue';
import InputError from '@/Components/Admin/InputError.vue';
import InputLabel from '@/Components/Admin/InputLabel.vue';

defineProps({
    open: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
    summary: { type: Object, default: null },
    password: { type: String, default: '' },
    error: { type: String, default: '' },
    passwordError: { type: [String, Array], default: null },
    periodError: { type: [String, Array], default: null },
});

const emit = defineEmits(['close', 'confirm', 'update:password']);
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4 sm:p-6"
        @click.self="!processing && emit('close')"
        @keydown.esc="!processing && emit('close')"
    >
        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="delete-period-title"
            class="flex w-full max-w-[720px] max-h-[85vh] flex-col overflow-hidden rounded-lg bg-white shadow-xl"
        >
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-4 py-3 sm:px-5">
                <div>
                    <h3 id="delete-period-title" class="text-sm font-semibold text-gray-800">Hapus Periode Jadwal</h3>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Tindakan permanen. Data terkait dalam cakupan periode akan dihapus.
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400"
                    :disabled="processing"
                    aria-label="Tutup"
                    @click="emit('close')"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5">
                <div v-if="loading" class="rounded-md border border-gray-200 bg-gray-50 px-4 py-6 text-center text-sm text-gray-600">
                    Memuat rincian data...
                </div>

                <template v-else>
                    <div class="rounded-md border border-red-200 bg-red-50 px-3 py-2.5 text-xs text-red-700">
                        <p class="font-medium">Peringatan: Penghapusan bersifat permanen.</p>
                        <p class="mt-0.5">Pastikan periode ini memang salah dan perlu dihapus. Data periode lain tidak akan terpengaruh.</p>
                    </div>

                    <div v-if="summary?.period" class="mt-3 grid grid-cols-1 gap-x-4 gap-y-1.5 rounded-md border border-gray-200 px-3 py-2.5 text-xs text-gray-700 sm:grid-cols-2">
                        <div class="truncate"><span class="text-gray-500">Periode:</span> <span class="font-medium text-gray-800">{{ summary.period.name }}</span></div>
                        <div><span class="text-gray-500">Status:</span> <span class="font-medium text-gray-800">{{ summary.period.status_label }}</span></div>
                        <div class="sm:col-span-2"><span class="text-gray-500">Tanggal:</span> <span class="font-medium text-gray-800">{{ summary.period.start_date_label }} - {{ summary.period.end_date_label }}</span></div>
                        <div class="sm:col-span-2 truncate"><span class="text-gray-500">Unit/Pos/Regu:</span> <span class="font-medium text-gray-800">{{ summary.period.unit_name }} / {{ summary.period.post_name }} / {{ summary.period.team_name }}</span></div>
                    </div>

                    <div v-if="summary?.counts?.length" class="mt-3 overflow-hidden rounded-md border border-gray-200">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 font-semibold uppercase tracking-wider text-gray-500">Data yang akan dihapus</th>
                                    <th class="px-3 py-2 text-right font-semibold uppercase tracking-wider text-gray-500">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="item in summary.counts" :key="item.key" class="border-t border-gray-100">
                                    <td class="px-3 py-1.5 text-gray-700">{{ item.label }}</td>
                                    <td class="px-3 py-1.5 text-right font-medium tabular-nums text-gray-900">{{ item.count }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="error" class="mt-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">
                        {{ error }}
                    </div>
                    <InputError :messages="periodError" class="mt-2" />
                </template>
            </div>

            <div class="border-t border-gray-200 bg-white px-4 py-3 sm:px-5">
                <div>
                    <InputLabel for="delete-period-password" value="Password Akun Admin" />
                    <input
                        id="delete-period-password"
                        :value="password"
                        type="password"
                        autocomplete="current-password"
                        class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 disabled:bg-gray-50"
                        :disabled="processing || summary?.is_locked || loading"
                        placeholder="Masukkan password akun Anda"
                        @input="emit('update:password', $event.target.value)"
                    />
                    <InputError :messages="passwordError" class="mt-1" />
                </div>

                <div class="mt-3 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button variant="secondary" :disabled="processing" class="w-full sm:w-auto" @click="emit('close')">Batal</Button>
                    <Button
                        variant="danger"
                        class="w-full sm:w-auto"
                        :disabled="loading || processing || !summary || summary.is_locked || !password"
                        @click="emit('confirm')"
                    >
                        {{ processing ? 'Menghapus...' : 'Hapus Permanen' }}
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
