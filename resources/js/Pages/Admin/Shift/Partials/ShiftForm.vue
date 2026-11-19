<script setup>
import InputLabel from '@/Components/Admin/InputLabel.vue';
import InputError from '@/Components/Admin/InputError.vue';
import TextInput from '@/Components/Admin/TextInput.vue';
import SearchSelect from '@/Components/Admin/SearchSelect.vue';
import CheckboxInput from '@/Components/Admin/CheckboxInput.vue';
import Select from '@/Components/Admin/Select.vue';
import { computed, watch } from 'vue';

const typeOptions = [
    { value: 'working', label: 'Shift Kerja' },
    { value: 'day_off', label: 'Shift Libur' },
];

const nameOptions = [
    { value: 'Pagi', label: 'Pagi' },
    { value: 'Siang', label: 'Siang' },
    { value: 'Malam', label: 'Malam' },
    { value: 'Stand By', label: 'Stand By' },
    { value: 'Lepas Dinas', label: 'Lepas Dinas' },
];

const codeByName = {
    Pagi: 'P',
    Siang: 'S',
    Malam: 'M',
    'Stand By': 'SB',
    'Lepas Dinas': 'L',
};

const typeByName = {
    Pagi: 'working',
    Siang: 'working',
    Malam: 'working',
    'Stand By': 'working',
    'Lepas Dinas': 'day_off',
};

const props = defineProps({
    form: { type: Object, required: true },
    units: { type: Array, default: () => [] },
});

const form = props.form;
const units = props.units;

const derivedCode = computed(() => codeByName[form.name] || '-');

watch(() => form.name, (value) => {
    if (typeByName[value]) {
        form.type = typeByName[value];
    }

    if (form.type === 'day_off') {
        form.start_time = '';
        form.end_time = '';
        form.end_day_offset = false;
    }
}, { immediate: true });

const statusOptions = [
    { value: '1', label: 'Aktif' },
    { value: '0', label: 'Nonaktif' },
];

watch(() => form.type, (value) => {
    if (value === 'day_off') {
        form.start_time = '';
        form.end_time = '';
        form.end_day_offset = false;
    }
}, { immediate: true });
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <InputLabel value="Unit" :required="true" />
            <SearchSelect
                v-model="form.unit_id"
                :options="units"
                placeholder="Pilih unit..."
                search-placeholder="Cari unit..."
            />
            <InputError :messages="form.errors?.unit_id" />
        </div>

        <div>
            <InputLabel value="Nama Shift" :required="true" />
            <Select
                id="name"
                :model-value="form.name"
                :options="nameOptions"
                @update:model-value="form.name = $event"
            />
            <p class="mt-1 text-xs text-gray-500">
                Kode Shift otomatis: {{ derivedCode }}
            </p>
            <InputError :messages="form.errors?.name" />
            <InputError :messages="form.errors?.code" />
        </div>

        <div>
            <InputLabel value="Jenis Shift" :required="true" />
            <Select
                id="type"
                :model-value="form.type"
                :options="typeOptions"
                @update:model-value="form.type = $event"
            />
            <InputError :messages="form.errors?.type" />
        </div>

        <template v-if="form.type === 'working'">
            <div>
                <InputLabel value="Jam Mulai" :required="true" />
                <TextInput id="start_time" v-model="form.start_time" type="time" />
                <InputError :messages="form.errors?.start_time" />
            </div>

            <div>
                <InputLabel value="Jam Selesai" :required="true" />
                <TextInput id="end_time" v-model="form.end_time" type="time" />
                <InputError :messages="form.errors?.end_time" />
            </div>

            <div class="sm:col-span-2">
                <CheckboxInput id="end_day_offset" v-model="form.end_day_offset">
                    Shift selesai pada hari berikutnya (lintas tengah malam)
                </CheckboxInput>
                <p class="mt-1 text-xs text-gray-500">
                    Contoh: mulai 19:00 dan selesai 07:00 dengan opsi ini aktif.
                </p>
                <InputError :messages="form.errors?.end_day_offset" />
            </div>
        </template>

        <template v-else>
            <div class="sm:col-span-2 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-700">
                Shift Libur tidak memiliki jam kerja. Jam mulai, jam selesai, dan opsi lintas tengah malam akan disimpan sebagai kosong.
            </div>
        </template>

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
    </div>
</template>
