<script setup>
import { computed, watch } from 'vue';
import InputLabel from '@/Components/Admin/InputLabel.vue';
import InputError from '@/Components/Admin/InputError.vue';
import TextInput from '@/Components/Admin/TextInput.vue';
import SearchSelect from '@/Components/Admin/SearchSelect.vue';
import Select from '@/Components/Admin/Select.vue';

const props = defineProps({
    form: { type: Object, required: true },
    units: { type: Array, default: () => [] },
    shifts: { type: Array, default: () => [] },
    isUsed: { type: Boolean, default: false },
    periodMembersCount: { type: Number, default: 0 },
    schedulesCount: { type: Number, default: 0 },
});

const statusOptions = [
    { value: '1', label: 'Aktif' },
    { value: '0', label: 'Nonaktif' },
];

const cycleLength = computed(() => {
    const value = Number(props.form.cycle_length);

    if (! Number.isFinite(value)) {
        return 0;
    }

    return Math.max(0, Math.min(366, Math.floor(value)));
});

const unitShifts = computed(() => props.shifts.filter(
    (shift) => String(shift.unit_id) === String(props.form.unit_id)
));

const shiftOptions = computed(() => [
    { value: '', label: 'Pilih shift...' },
    ...unitShifts.value.map((shift) => ({
        value: String(shift.id),
        label: `${shift.label}${shift.time_label ? ` (${shift.time_label})` : ''}`,
    })),
]);

const selectedUnitLabel = computed(
    () => props.units.find((unit) => String(unit.value) === String(props.form.unit_id))?.label ?? '-'
);

const rows = computed(() => (Array.isArray(props.form.details) ? props.form.details : []));

const preview = computed(() => rows.value.map((row, index) => {
    const shift = unitShifts.value.find((item) => String(item.id) === String(row.shift_id));

    return {
        day: index + 1,
        label: shift?.label ?? 'Belum dipilih',
        time: shift?.time_label ?? null,
    };
}));

const selectedCount = computed(
    () => rows.value.filter((row) => row.shift_id !== '' && row.shift_id !== null).length
);

function ensureDetails() {
    if (! Array.isArray(props.form.details)) {
        props.form.details = [];
    }

    const target = cycleLength.value;

    while (props.form.details.length < target) {
        props.form.details.push({
            day_order: props.form.details.length + 1,
            shift_id: '',
        });
    }

    if (props.form.details.length > target) {
        props.form.details.splice(target);
    }

    props.form.details.forEach((detail, index) => {
        detail.day_order = index + 1;

        if (detail.shift_id === null || detail.shift_id === undefined) {
            detail.shift_id = '';
        }
    });
}

function hasUnavailableShift(row) {
    if (row.shift_id === '' || row.shift_id === null) {
        return false;
    }

    return ! unitShifts.value.some((shift) => String(shift.id) === String(row.shift_id));
}

watch(cycleLength, ensureDetails, { immediate: true });

watch(() => props.form.unit_id, (value, previous) => {
    if (previous === undefined || String(value) === String(previous)) {
        return;
    }

    rows.value.forEach((row) => {
        row.shift_id = '';
    });
});
</script>

<template>
    <div class="space-y-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <InputLabel value="Unit" :required="true" />
                <SearchSelect
                    v-if="!isUsed"
                    v-model="form.unit_id"
                    :options="units"
                    placeholder="Pilih unit..."
                    search-placeholder="Cari unit..."
                />
                <TextInput v-else :model-value="selectedUnitLabel" disabled />
                <InputError :messages="form.errors?.unit_id" />
            </div>

            <div>
                <InputLabel value="Nama Pola" :required="true" />
                <TextInput
                    id="name"
                    v-model="form.name"
                    placeholder="Pola 8 Hari"
                />
                <InputError :messages="form.errors?.name" />
            </div>

            <div>
                <InputLabel value="Jumlah Hari Siklus" :required="true" />
                <TextInput
                    id="cycle_length"
                    v-model="form.cycle_length"
                    type="number"
                    min="1"
                    max="366"
                    :disabled="isUsed"
                />
                <p class="mt-1 text-xs text-gray-500">
                    Setiap hari dalam siklus wajib memiliki satu shift.
                </p>
                <InputError :messages="form.errors?.cycle_length" />
            </div>

            <div>
                <InputLabel value="Status" />
                <Select
                    id="is_active"
                    :model-value="form.is_active ? '1' : '0'"
                    :options="statusOptions"
                    @update:model-value="form.is_active = $event === '1'"
                />
                <InputError :messages="form.errors?.is_active" />
            </div>

            <div class="sm:col-span-2">
                <InputLabel value="Deskripsi" />
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="3"
                    placeholder="Keterangan singkat pola rotasi..."
                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-700 focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                />
                <InputError :messages="form.errors?.description" />
            </div>
        </div>

        <div v-if="isUsed" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700">
            Pola ini sudah digunakan oleh {{ periodMembersCount }} anggota periode dan
            {{ schedulesCount }} jadwal. Jumlah hari siklus dan unit tidak dapat diubah.
            Perubahan detail hanya mengubah template, bukan jadwal yang sudah tersimpan.
        </div>

        <div class="rounded-lg border border-gray-200">
            <div class="flex flex-col gap-2 border-b border-gray-200 bg-gray-50/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-800">Urutan Siklus</p>
                    <p class="mt-0.5 text-xs text-gray-500">
                        {{ selectedCount }} dari {{ cycleLength }} hari terisi.
                        {{ unitShifts.length }} shift aktif tersedia untuk unit ini.
                    </p>
                </div>
                <InputError :messages="form.errors?.details" />
            </div>

            <div v-if="!form.unit_id" class="px-4 py-8 text-center text-sm text-gray-500">
                Pilih unit terlebih dahulu untuk mengisi urutan shift.
            </div>

            <div v-else-if="unitShifts.length === 0" class="px-4 py-8 text-center text-sm text-gray-500">
                Belum ada shift aktif pada unit ini.
                <a href="/admin/shift/create" class="font-medium text-blue-600 hover:text-blue-700">
                    Tambah shift terlebih dahulu.
                </a>
            </div>

            <div v-else class="divide-y divide-gray-100">
                <div
                    v-for="(row, index) in rows"
                    :key="index"
                    class="grid gap-2 px-4 py-3 sm:grid-cols-[110px_1fr] sm:items-start"
                >
                    <div class="pt-2 text-sm font-medium text-gray-700">
                        Hari {{ index + 1 }}
                    </div>
                    <div>
                        <Select
                            :model-value="row.shift_id === null ? '' : String(row.shift_id)"
                            :options="shiftOptions"
                            @update:model-value="row.shift_id = $event"
                        />
                        <p v-if="hasUnavailableShift(row)" class="mt-1 text-xs text-amber-600">
                            Shift yang tersimpan sudah nonaktif atau tidak tersedia. Pilih shift aktif pengganti.
                        </p>
                        <InputError :messages="form.errors['details.' + index + '.shift_id']" />
                        <InputError :messages="form.errors['details.' + index + '.day_order']" />
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-blue-100 bg-blue-50/50">
            <div class="border-b border-blue-100 px-4 py-3">
                <p class="text-sm font-semibold text-gray-800">Preview Pola</p>
                <p class="mt-0.5 text-xs text-gray-500">Urutan hari dan shift sesuai siklus.</p>
            </div>
            <div class="max-h-72 overflow-y-auto px-4 py-3">
                <div
                    v-for="item in preview"
                    :key="item.day"
                    class="flex items-center gap-3 border-b border-blue-100/60 py-2 last:border-0"
                >
                    <span class="w-16 shrink-0 text-xs font-medium text-gray-500">Hari {{ item.day }}</span>
                    <span class="text-sm text-gray-700">{{ item.label }}</span>
                    <span v-if="item.time" class="ml-auto text-xs text-gray-400">{{ item.time }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
