<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Badge from '@/Components/Admin/Badge.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import InputLabel from '@/Components/Admin/InputLabel.vue';
import InputError from '@/Components/Admin/InputError.vue';

const props = defineProps({
    period: { type: Object, required: true },
    schedules: { type: Array, default: () => [] },
    shiftOptions: { type: Array, default: () => [] },
    isLocked: { type: Boolean, default: false },
});

const securityFilter = ref('');
const editing = ref(null);
const confirming = ref(false);

const form = useForm({ shift_id: '', notes: '' });

const securityOptions = computed(() => [
    { value: '', label: 'Semua satpam' },
    ...props.period.members.map((member) => ({ value: String(member.security_id), label: member.full_name })),
]);

const filteredSchedules = computed(() => props.schedules.filter(
    (row) => securityFilter.value === '' || String(row.security_id) === securityFilter.value
));

const replacementOptions = computed(() => [
    { value: '', label: 'Pilih shift pengganti' },
    ...props.shiftOptions.filter((option) => option.value !== editing.value?.shift_id),
]);

const replacementLabel = computed(
    () => props.shiftOptions.find((option) => String(option.value) === String(form.shift_id))?.label ?? '-'
);

function openEdit(row) {
    editing.value = row;
    confirming.value = false;
    form.reset();
    form.clearErrors();
}

function closeEdit() {
    editing.value = null;
    confirming.value = false;
}

function submit() {
    form.put(`/admin/schedule-period/${props.period.id}/schedule/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: closeEdit,
        onError: () => {
            confirming.value = false;
        },
    });
}

const info = computed(() => [
    { label: 'Nama Periode', value: props.period.name },
    { label: 'Unit', value: props.period.unit_name },
    { label: 'Pos Jaga', value: props.period.post_name },
    { label: 'Regu', value: props.period.team_name },
    { label: 'Tanggal Mulai', value: props.period.start_date_label },
    { label: 'Tanggal Selesai', value: props.period.end_date_label },
    { label: 'Status', value: props.period.status_label, badge: true },
    { label: 'Jumlah Anggota', value: `${props.period.members.length} anggota` },
    { label: 'Jadwal Tersimpan', value: `${props.period.schedules_count} jadwal` },
    { label: 'Dibuat', value: props.period.created_at },
    { label: 'Diperbarui', value: props.period.updated_at },
]);

function statusVariant(status) {
    if (status === 'active') {
        return 'emerald';
    }

    if (status === 'completed') {
        return 'blue';
    }

    return 'gray';
}
</script>

<template>
    <AdminLayout title="Detail Periode Jadwal">
        <div class="space-y-6">
            <PageHeader
                title="Periode Jadwal"
                :breadcrumb="[
                    { label: 'Periode Jadwal', href: '/admin/schedule-period' },
                    { label: 'Detail' },
                ]"
            >
                <template #actions>
                    <Link
                        :href="`/admin/schedule-period/${props.period.id}/generate`"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-emerald-600 px-4 py-2 text-xs text-white transition hover:bg-emerald-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2"
                    >
                        Generate Jadwal
                    </Link>
                    <Link
                        :href="`/admin/schedule-period/${props.period.id}/edit`"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs text-white transition hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                    >
                        Edit
                    </Link>
                    <Link
                        href="/admin/schedule-period"
                        class="inline-flex items-center justify-center gap-2 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-400 focus-visible:ring-offset-2"
                    >
                        Kembali
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <div
                v-if="period.has_schedules"
                class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700"
            >
                Periode ini sudah memiliki jadwal. Unit, pos, regu, tanggal, dan snapshot anggota terkunci
                untuk menjaga konsistensi jadwal yang sudah tersimpan.
            </div>

            <Card>
                <table class="w-full text-left">
                    <tbody>
                        <tr
                            v-for="item in info"
                            :key="item.label"
                            class="border-b border-gray-100 last:border-0"
                        >
                            <th class="w-52 py-2 pr-4 align-top text-xs font-medium text-gray-900">
                                {{ item.label }}
                            </th>
                            <td class="py-2 text-sm text-gray-700">
                                <Badge
                                    v-if="item.badge"
                                    :variant="statusVariant(props.period.status)"
                                >
                                    {{ item.value }}
                                </Badge>
                                <template v-else>{{ item.value }}</template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </Card>

            <Card>
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Snapshot Anggota</h3>
                        <p class="mt-0.5 text-xs text-gray-500">
                            Data tersimpan saat periode dibuat, bukan pembacaan keanggotaan regu terkini.
                        </p>
                    </div>
                </div>

                <div class="overflow-hidden rounded-md border border-gray-200">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">No</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Nama Satpam</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">NIK</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Pola Rotasi</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Siklus</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Offset</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(member, index) in period.members"
                                :key="member.id"
                                class="border-t border-gray-100 odd:bg-white even:bg-gray-50/60"
                            >
                                <td class="px-4 py-2.5 text-gray-600">{{ index + 1 }}</td>
                                <td class="px-4 py-2.5 text-gray-700">{{ member.full_name }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ member.registration_number ?? '-' }}</td>
                                <td class="px-4 py-2.5 text-gray-600">{{ member.pattern_name ?? 'Tanpa pola' }}</td>
                                <td class="px-4 py-2.5 text-gray-600">
                                    {{ member.pattern_cycle_length ? `${member.pattern_cycle_length} hari` : '-' }}
                                </td>
                                <td class="px-4 py-2.5 text-gray-600">{{ member.start_offset }}</td>
                            </tr>
                            <tr v-if="period.members.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Belum ada anggota pada snapshot periode ini.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>

            <Card>
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-800">Jadwal Tersimpan</h3>
                        <p class="mt-0.5 text-xs text-gray-500">
                            Penyesuaian manual hanya mengubah satu baris jadwal dan tidak ditimpa saat generate ulang.
                        </p>
                    </div>
                    <div class="w-full sm:w-64">
                        <label for="security-filter" class="sr-only">Filter satpam</label>
                        <select
                            id="security-filter"
                            v-model="securityFilter"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        >
                            <option v-for="option in securityOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div
                    v-if="isLocked"
                    class="mb-4 rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-xs text-blue-700"
                >
                    Periode sudah selesai. Jadwal terkunci dan tidak dapat disesuaikan.
                </div>

                <InputError :messages="form.errors?.schedule" class="mb-3" />

                <div class="overflow-x-auto rounded-md border border-gray-200">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Tanggal</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Satpam</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Shift</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Sumber</th>
                                <th class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">Terakhir Diubah</th>
                                <th v-if="!isLocked" class="px-4 py-2.5 text-xs font-semibold uppercase tracking-wider text-gray-500">
                                    <span class="sr-only">Aksi</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in filteredSchedules"
                                :key="row.id"
                                class="border-t border-gray-100 odd:bg-white even:bg-gray-50/60"
                            >
                                <td class="px-4 py-2.5 text-gray-700">{{ row.date_label }}</td>
                                <td class="px-4 py-2.5 text-gray-700">{{ row.full_name }}</td>
                                <td class="px-4 py-2.5 text-gray-700">
                                    {{ row.shift_name ?? '-' }}
                                    <span v-if="row.shift_time" class="block text-xs text-gray-500">{{ row.shift_time }}</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <Badge :variant="row.source === 'adjustment' ? 'amber' : 'gray'">
                                        {{ row.source === 'adjustment' ? 'Penyesuaian' : 'Dihasilkan Sistem' }}
                                    </Badge>
                                    <span v-if="row.notes" class="mt-1 block text-xs text-gray-500">{{ row.notes }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-xs text-gray-600">
                                    <template v-if="row.updated_by_name">
                                        {{ row.updated_by_name }}
                                        <span class="block text-gray-500">{{ row.updated_at }}</span>
                                    </template>
                                    <template v-else>-</template>
                                </td>
                                <td v-if="!isLocked" class="px-4 py-2.5 text-right">
                                    <Button variant="secondary" @click="openEdit(row)">
                                        Edit<span class="sr-only"> jadwal {{ row.full_name }} {{ row.date_label }}</span>
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="filteredSchedules.length === 0">
                                <td :colspan="isLocked ? 5 : 6" class="px-4 py-8 text-center text-sm text-gray-500">
                                    Belum ada jadwal tersimpan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>

        <div
            v-if="editing"
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4"
            @click.self="closeEdit"
            @keydown.esc="closeEdit"
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="edit-schedule-title"
                class="w-full max-w-lg rounded-lg bg-white p-5 shadow-xl"
            >
                <h3 id="edit-schedule-title" class="text-sm font-semibold text-gray-800">Edit Jadwal</h3>
                <p class="mt-1 text-xs text-gray-500">{{ editing.full_name }} — {{ editing.date_label }}</p>

                <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                    <div class="rounded-md border border-gray-200 p-3">
                        <dt class="text-xs text-gray-500">Shift sebelumnya</dt>
                        <dd class="mt-1 font-medium text-gray-800">{{ editing.shift_name ?? '-' }}</dd>
                        <dd v-if="editing.shift_time" class="text-xs text-gray-500">{{ editing.shift_time }}</dd>
                    </div>
                    <div class="rounded-md border border-emerald-200 bg-emerald-50/50 p-3">
                        <dt class="text-xs text-gray-500">Shift pengganti</dt>
                        <dd class="mt-1 font-medium text-gray-800">{{ replacementLabel }}</dd>
                    </div>
                </dl>

                <div class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="shift_id" value="Shift Pengganti" />
                        <select
                            id="shift_id"
                            v-model="form.shift_id"
                            :disabled="confirming"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        >
                            <option v-for="option in replacementOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                        <InputError :messages="form.errors?.shift_id" />
                    </div>
                    <div>
                        <InputLabel for="notes" value="Alasan Perubahan" />
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            rows="3"
                            maxlength="1000"
                            :disabled="confirming"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            placeholder="Penyesuaian kebutuhan operasional"
                        />
                        <InputError :messages="form.errors?.notes" />
                    </div>
                </div>

                <div
                    v-if="confirming"
                    class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700"
                    role="alert"
                >
                    Ubah shift {{ editing.shift_name ?? '-' }} menjadi {{ replacementLabel }}? Jadwal akan bertanda
                    Manual dan tidak ditimpa saat generate ulang.
                </div>

                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <Button variant="secondary" :disabled="form.processing" @click="confirming ? (confirming = false) : closeEdit()">
                        {{ confirming ? 'Kembali' : 'Batal' }}
                    </Button>
                    <Button
                        v-if="!confirming"
                        :disabled="!form.shift_id || !form.notes.trim()"
                        @click="confirming = true"
                    >
                        Lanjutkan
                    </Button>
                    <Button v-else :disabled="form.processing" @click="submit">
                        {{ form.processing ? 'Menyimpan...' : 'Ya, Simpan' }}
                    </Button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
