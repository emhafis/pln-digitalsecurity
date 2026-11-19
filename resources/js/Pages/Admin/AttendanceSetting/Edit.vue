<script setup>
import { useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Badge from '@/Components/Admin/Badge.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import InputError from '@/Components/Admin/InputError.vue';

const props = defineProps({
    settings: { type: Object, required: true },
    defaults: { type: Object, required: true },
    updater: { type: String, default: null },
    updated_at: { type: String, default: null },
});

const form = useForm({
    gps_radius_meters: props.settings.gps_radius_meters,
    check_in_open_minutes: props.settings.check_in_open_minutes,
    late_tolerance_minutes: props.settings.late_tolerance_minutes,
    early_checkout_tolerance_minutes: props.settings.early_checkout_tolerance_minutes,
    checkout_close_minutes: props.settings.checkout_close_minutes,
    max_gps_accuracy_meters: props.settings.max_gps_accuracy_meters,
});

const fields = [
    {
        key: 'gps_radius_meters',
        label: 'Radius Lokasi (meter)',
        hint: 'Jarak maksimal perangkat dari Pos Jaga agar absensi diterima.',
        min: 1,
    },
    {
        key: 'max_gps_accuracy_meters',
        label: 'Akurasi Lokasi Maksimal (meter)',
        hint: 'Nilai akurasi perangkat yang lebih besar dari batas ini akan ditolak.',
        min: 1,
    },
    {
        key: 'check_in_open_minutes',
        label: 'Check-in Dibuka (menit sebelum shift)',
        hint: 'Berapa menit sebelum jam mulai shift check-in dapat dilakukan.',
        min: 0,
    },
    {
        key: 'late_tolerance_minutes',
        label: 'Toleransi Keterlambatan (menit)',
        hint: 'Check-in setelah batas ini dianggap terlambat.',
        min: 0,
    },
    {
        key: 'early_checkout_tolerance_minutes',
        label: 'Toleransi Check-out Awal (menit)',
        hint: 'Isi 0 agar check-out hanya dapat dilakukan setelah shift selesai.',
        min: 0,
    },
    {
        key: 'checkout_close_minutes',
        label: 'Check-out Ditutup (menit setelah shift)',
        hint: 'Setelah batas ini, check-out tidak dapat dilakukan dan data ditandai Tidak Hadir bila belum check-in.',
        min: 0,
    },
];

function submit() {
    form.put('/admin/attendance-setting', { preserveScroll: true });
}

function resetDefault() {
    Object.keys(props.defaults).forEach((key) => {
        form[key] = props.defaults[key];
    });
}
</script>

<template>
    <AdminLayout title="Pengaturan Absensi">
        <div class="space-y-6">
            <PageHeader
                title="Pengaturan Absensi"
                description="Pengaturan global yang digunakan sebagai snapshot saat data absensi dibuat."
                :breadcrumb="[{ label: 'Absensi' }, { label: 'Pengaturan Absensi' }]"
            >
                <template #actions>
                    <Badge :variant="settings.is_valid ? 'emerald' : 'amber'">
                        {{ settings.is_saved ? (settings.is_valid ? 'Aktif' : 'Perlu Diperbaiki') : 'Belum Diisi' }}
                    </Badge>
                </template>
            </PageHeader>

            <FlashMessage />

            <div
                v-if="settings.is_saved && settings.validation_issues.length"
                class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                <p class="font-semibold">Pengaturan saat ini belum valid:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li v-for="(issue, index) in settings.validation_issues" :key="index">{{ issue.message }}</li>
                </ul>
            </div>

            <Card title="Nilai Pengaturan">
                <form class="space-y-5" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label v-for="field in fields" :key="field.key" class="block">
                            <span class="text-sm font-medium text-gray-700">{{ field.label }}</span>
                            <input
                                v-model.number="form[field.key]"
                                type="number"
                                :min="field.min"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm"
                                required
                            />
                            <span class="mt-1 block text-xs text-gray-500">{{ field.hint }}</span>
                            <span class="mt-1 block text-xs text-gray-400">Bawaan: {{ defaults[field.key] }}</span>
                            <InputError :messages="form.errors[field.key]" class="!mt-1" />
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4">
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Pengaturan' }}
                        </Button>
                        <Button variant="secondary" :disabled="form.processing" @click="resetDefault">
                            Gunakan Nilai Bawaan
                        </Button>
                        <p v-if="updater" class="text-xs text-gray-500">
                            Terakhir diperbarui oleh {{ updater }}<span v-if="updated_at">, {{ updated_at }}</span>
                        </p>
                    </div>
                </form>
            </Card>

            <Card title="Catatan">
                <ul class="list-disc space-y-1 pl-5 text-sm text-gray-600">
                    <li>Pengaturan ini hanya dapat diubah oleh Superadmin.</li>
                    <li>Perubahan pengaturan tidak mengubah snapshot data absensi yang sudah dibuat.</li>
                    <li>Snapshot akan digunakan saat pembuatan atau sinkronisasi data absensi berikutnya.</li>
                </ul>
            </Card>
        </div>
    </AdminLayout>
</template>
