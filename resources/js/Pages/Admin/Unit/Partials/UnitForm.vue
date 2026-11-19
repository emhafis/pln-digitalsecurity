<script setup>
import { computed, watch } from 'vue';
import InputLabel from '@/Components/Admin/InputLabel.vue';
import InputError from '@/Components/Admin/InputError.vue';
import TextInput from '@/Components/Admin/TextInput.vue';
import Select from '@/Components/Admin/Select.vue';

const props = defineProps({
    form: { type: Object, required: true },
    showStatus: { type: Boolean, default: false },
});

const levels = [
    { value: 'uid', label: 'UID' },
    { value: 'up', label: 'UP' },
    { value: 'ulp', label: 'ULP' },
];

const statuses = [
    { value: '1', label: 'Aktif' },
    { value: '0', label: 'Nonaktif' },
];

const gridClass = computed(() => (props.showStatus ? 'sm:grid-cols-3' : 'sm:grid-cols-2'));

function onNameInput(value) {
    props.form.unit_name = value.toUpperCase();
}

watch(
    () => props.form.unit_level,
    (level) => {
        const prefix = String(level ?? '').toUpperCase();
        if (!prefix) {
            return;
        }

        const rest = String(props.form.unit_name ?? '')
            .replace(/^(UID|UP|ULP)\s+/i, '')
            .trimStart();

        props.form.unit_name = rest ? `${prefix} ${rest}` : `${prefix} `;
    },
);
</script>

<template>
    <div class="grid gap-4" :class="gridClass">
        <div>
            <InputLabel :value=" 'Level Unit'"/>
            <Select
                id="unit_level"
                v-model="form.unit_level"
                :options="levels"
                />
        </div>

        <div>
            <InputLabel :value=" 'Nama Unit'" :required="true"/>
            <TextInput
                id="unit_name"
                :model-value="form.unit_name"
                placeholder="UID ACEH BESAR"
                @update:model-value="onNameInput"
                />
            <InputError :messages="form.errors?.unit_name"/>
        </div>

        <div v-if="showStatus">
            <InputLabel value="Status" />
            <Select
                id="is_active"
                :model-value="form.is_active ? '1' : '0'"
                :options="statuses"
                @update:model-value="form.is_active = $event === '1'"
                />
        </div>
    </div>
</template>
