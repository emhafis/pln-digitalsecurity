<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Badge from '@/Components/Admin/Badge.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import InputError from '@/Components/Admin/InputError.vue';

const props = defineProps({
    period: { type: Object, required: true },
    preview: { type: Object, required: true },
});

const confirming = ref(false);
const form = useForm({
    mode: props.preview.mode,
});

watch(() => props.preview.mode, (mode) => {
    form.mode = mode;
    confirming.value = false;
});

const isRegenerate = computed(() => props.preview.mode === 'regenerate');
const hasErrors = computed(() => (props.preview.errors ?? []).length > 0);
const summary = computed(() => props.preview.summary);
const canGenerate = computed(() => !hasErrors.value && summary.value.total_changes > 0);

const actionMeta = {
    create: { label: 'Buat', variant: 'emerald' },
    update: { label: 'Perbarui', variant: 'blue' },
    delete: { label: 'Hapus Sistem', variant: 'red' },
    unchanged: { label: 'Tetap', variant: 'gray' },
    skip: { label: 'Dilewati', variant: 'gray' },
    keep_manual: { label: 'Penyesuaian', variant: 'amber' },
    conflict: { label: 'Konflik', variant: 'red' },
};

const summaryItems = computed(() => {
    const items = [
        { label: 'Anggota', value: summary.value.members_count, tone: 'text-gray-900' },
        { label: 'Hari Periode', value: summary.value.days_count, tone: 'text-gray-900' },
        { label: 'Total Kandidat', value: summary.value.total_candidates, tone: 'text-gray-900' },
        { label: 'Jadwal Baru', value: summary.value.create_count, tone: 'text-emerald-700' },
    ];

    if (isRegenerate.value) {
        items.push(
            { label: 'Diperbarui', value: summary.value.update_count, tone: 'text-blue-700' },
            { label: 'Dihapus', value: summary.value.delete_count, tone: 'text-red-700' },
            { label: 'Tidak Berubah', value: summary.value.unchanged_count, tone: 'text-gray-600' },
            { label: 'Penyesuaian Dipertahankan', value: summary.value.manual_count, tone: 'text-amber-700' }
        );
    } else {
        items.push(
            { label: 'Dilewati', value: summary.value.skip_count, tone: 'text-gray-600' },
            { label: 'Penyesuaian Dipertahankan', value: summary.value.manual_count, tone: 'text-amber-700' }
        );
    }

    if (summary.value.conflict_count > 0) {
        items.push({ label: 'Konflik', value: summary.value.conflict_count, tone: 'text-red-700' });
    }

    return items;
});

function actionLabel(action) {
    return actionMeta[action]?.label ?? action;
}

function actionVariant(action) {
    return actionMeta[action]?.variant ?? 'gray';
}

function sourceLabel(source) {
    if (source === 'adjustment') {
        return 'Penyesuaian';
    }

    if (source === 'system_generated') {
        return 'Dihasilkan Sistem';
    }

    return '-';
}

function submit() {
    form.mode = props.preview.mode;

    form.post(`/admin/schedule-period/${props.period.id}/generate`, {
        preserveScroll: true,
        onSuccess: () => {
            confirming.value = false;
        },
    });
}
</script>

<template>
    <AdminLayout title="Generate Jadwal">
        <div class="space-y-6">
            <PageHeader
                title="Generate Jadwal"
                :breadcrumb="[
                    { label: 'Periode Jadwal', href: '/admin/schedule-period' },
                    { label: 'Detail', href: `/admin/schedule-period/${props.period.id}` },
                    { label: 'Generate Jadwal' },
                ]"
            >
                <template #actions>
                    <Link
                        :href="`/admin/schedule-period/${props.period.id}`"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Kembali ke Detail
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

              <Card>
                <form @submit.prevent="submit">
<div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">{{ period.name }}</h3>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ period.unit_name }} - {{ period.post_name }} - {{ period.team_name }}
                        </p>
                    </div>
                    <div class="text-xs text-gray-500">
                        <p>{{ preview.period.start_date_label }} s/d {{ preview.period.end_date_label }}</p>
                        <p class="mt-1">{{ preview.period.schedules_count }} jadwal tersimpan saat ini</p>
                    </div>
                </div>
                </form>
            </Card>

            <div class="grid gap-3 lg:grid-cols-2">
                <Link
                    :href="`/admin/schedule-period/${props.period.id}/generate?mode=fill`"
                    class="rounded-lg border px-4 py-3 transition"
                    :class="!isRegenerate
                        ? 'border-blue-300 bg-blue-50/70 ring-1 ring-blue-200'
                        : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50'"
                >
                    <p class="text-sm font-semibold text-gray-800">Isi Jadwal yang Belum Ada</p>
                    <p class="mt-1 text-xs text-gray-500">
                        Hanya membuat jadwal baru. Jadwal rotasi dan manual yang sudah tersimpan tidak diubah.
                    </p>
                </Link>

                <Link
                    :href="`/admin/schedule-period/${props.period.id}/generate?mode=regenerate`"
                    class="rounded-lg border px-4 py-3 transition"
                    :class="isRegenerate
                        ? 'border-amber-300 bg-amber-50/70 ring-1 ring-amber-200'
                        : 'border-gray-200 bg-white hover:border-gray-300 hover:bg-gray-50'"
                >
                    <p class="text-sm font-semibold text-gray-800">Regenerate Jadwal Rotasi</p>
                    <p class="mt-1 text-xs text-gray-500">
                        Menghitung ulang dan mengganti jadwal hasil rotasi saja. Jadwal manual selalu dipertahankan.
                    </p>
                </Link>
            </div>

            <div
                v-if="hasErrors"
                class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
                <p class="font-semibold">Generate belum dapat dijalankan:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li v-for="(error, index) in preview.errors" :key="index">{{ error }}</li>
                </ul>
            </div>

            <InputError :messages="form.errors?.generation" />

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="item in summaryItems"
                    :key="item.label"
                    class="rounded-lg border border-gray-200 bg-white px-4 py-3"
                >
                    <p class="text-xs text-gray-500">{{ item.label }}</p>
                    <p class="mt-1 text-xl font-semibold" :class="item.tone">{{ item.value }}</p>
                </div>
            </div>

              <Card>
                <form @submit.prevent="submit">
<div class="mb-3">
                    <h3 class="text-sm font-semibold text-gray-800">Anggota & Pola Rotasi</h3>
                    <p class="mt-0.5 text-xs text-gray-500">
                        Offset 0 berarti siklus dimulai dari hari pertama pola pada tanggal awal periode.
                    </p>
                </div>

                <div class="overflow-hidden rounded-md border border-gray-200">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Satpam</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Pola</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Siklus</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Hari Rotasi Awal</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Shift Pertama</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Hari Digenerate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="member in preview.members"
                                :key="member.id"
                                class="border-t border-gray-100 odd:bg-white even:bg-gray-50/60"
                            >
                                <td class="px-4 py-2.5">
                                    <p class="text-gray-700">{{ member.full_name }}</p>
                                    <p class="text-xs text-gray-400">{{ member.registration_number ?? '-' }}</p>
                                </td>
                                <td class="px-4 py-2.5 text-gray-600">{{ member.pattern_name ?? 'Tanpa pola' }}</td>
                                <td class="px-4 py-2.5 text-gray-600">
                                    {{ member.cycle_length ? `${member.cycle_length} hari` : '-' }}
                                </td>
                                <td class="px-4 py-2.5 text-gray-600">
                                    Hari {{ member.rotation_day ?? (member.start_offset + 1) }}
                                </td>
                                <td class="px-4 py-2.5 text-gray-600">{{ member.first_shift_name ?? '-' }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ member.days_count }} hari</td>
                            </tr>
                            <tr v-if="preview.members.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Belum ada anggota pada snapshot periode ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </form>
            </Card>

              <Card>
                <form @submit.prevent="submit">
<div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Preview Jadwal</h3>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ preview.rows.length }} baris preview.
                            <template v-if="isRegenerate">
                                Baris manual ditandai dan tidak akan diubah.
                            </template>
                        </p>
                    </div>
                    <Button :disabled="!canGenerate || form.processing" @click="confirming = true">
                        {{ isRegenerate ? 'Regenerate Jadwal Rotasi' : 'Generate Jadwal' }}
                    </Button>
                </div>

                <div
                    v-if="confirming && canGenerate"
                    class="mb-4 rounded-lg border border-blue-200 bg-blue-50/70 px-4 py-3"
                >
                    <p class="text-sm font-semibold text-gray-800">Konfirmasi {{ isRegenerate ? 'regenerate' : 'generate' }}</p>
                    <p class="mt-1 text-xs text-gray-600">
                        <template v-if="isRegenerate">
                            {{ summary.create_count }} jadwal baru, {{ summary.update_count }} diperbarui,
                            {{ summary.delete_count }} dihapus, dan {{ summary.manual_count }} jadwal manual dipertahankan.
                        </template>
                        <template v-else>
                            {{ summary.create_count }} jadwal baru akan dibuat. {{ summary.skip_count }} jadwal yang
                            sudah tersedia dan {{ summary.manual_count }} jadwal manual tidak diubah.
                        </template>
                    </p>
                    <div class="mt-3 flex gap-2">
                        <Button :disabled="form.processing" type="submit">
                            {{ form.processing ? 'Memproses...' : 'Ya, Lanjutkan' }}
                        </Button>
                        <Button variant="secondary" :disabled="form.processing" @click="confirming = false">
                            Batal
                        </Button>
                    </div>
                </div>

                <div
                    v-if="!hasErrors && !canGenerate"
                    class="mb-4 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-600"
                >
                    Tidak ada perubahan jadwal yang perlu disimpan pada mode ini.
                </div>

                <div class="max-h-[36rem] overflow-auto rounded-md border border-gray-200">
                    <table class="w-full min-w-[860px] text-left text-sm">
                        <thead class="sticky top-0 bg-gray-50">
                            <tr>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Tanggal</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Satpam</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Hari Pola</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Shift Baru</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Shift Lama</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(row, index) in preview.rows"
                                :key="`${row.date}-${row.security_id}-${index}`"
                                class="border-t border-gray-100 odd:bg-white even:bg-gray-50/60"
                            >
                                <td class="px-4 py-2.5 text-gray-600">
                                    <p>{{ row.date_label }}</p>
                                    <p class="text-xs text-gray-400">{{ row.weekday }}</p>
                                </td>
                                <td class="px-4 py-2.5">
                                    <p class="text-gray-700">{{ row.full_name }}</p>
                                    <p class="text-xs text-gray-400">{{ row.pattern_name ?? '-' }}</p>
                                </td>
                                <td class="px-4 py-2.5 text-gray-600">{{ row.pattern_day_order ?? '-' }}</td>
                                <td class="px-4 py-2.5 text-gray-600">
                                    <p>{{ row.shift_name ?? '-' }}</p>
                                    <p class="text-xs text-gray-400">{{ row.shift_time ?? '' }}</p>
                                </td>
                                <td class="px-4 py-2.5 text-gray-600">
                                    <p>{{ row.existing_shift_name ?? '-' }}</p>
                                    <p class="text-xs text-gray-400">
                                        {{ row.existing_shift_time ?? '' }}
                                        <template v-if="row.existing_source">
                                            ({{ sourceLabel(row.existing_source) }})
                                        </template>
                                    </p>
                                </td>
                                <td class="px-4 py-2.5">
                                    <Badge :variant="actionVariant(row.action)">
                                        {{ actionLabel(row.action) }}
                                    </Badge>
                                </td>
                            </tr>
                            <tr v-if="preview.rows.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Preview belum tersedia. Periksa konfigurasi anggota dan pola rotasi.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                </form>
            </Card>
        </div>
    </AdminLayout>
</template>

