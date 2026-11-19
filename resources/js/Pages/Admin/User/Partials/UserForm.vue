<script setup>
import { computed } from 'vue';
import InputLabel from '@/Components/Admin/InputLabel.vue';
import InputError from '@/Components/Admin/InputError.vue';
import TextInput from '@/Components/Admin/TextInput.vue';
import Select from '@/Components/Admin/Select.vue';
import SearchSelect from '@/Components/Admin/SearchSelect.vue';

const props = defineProps({
    form: { type: Object, required: true },
    group: { type: String, default: 'security' },
    units: { type: Array, default: () => [] },
    showStatus: { type: Boolean, default: false },
});

const gridClass = computed(() => (props.showStatus ? 'sm:grid-cols-3' : 'sm:grid-cols-2'));

const roleOptions = computed(() => (props.group === 'admin'
    ? [
        { value: 'admin', label: 'Admin' },
        { value: 'superadmin', label: 'Superadmin' },
    ]
    : [{ value: 'security', label: 'Security' }]));

const statusOptions = [
    { value: '1', label: 'Aktif' },
    { value: '0', label: 'Nonaktif' },
];
</script>

<template>
    <div class="grid gap-4" :class="gridClass">
        <div>
            <InputLabel value="Username" :required="true" />
            <TextInput
                id="username"
                v-model="form.username"
                placeholder="john.doe"
                />
            <InputError :messages="form.errors?.username" />
        </div>

        <div>
            <InputLabel value="Nama" :required="true" />
            <TextInput
                id="name"
                v-model="form.name"
                placeholder="John Doe"
                />
            <InputError :messages="form.errors?.name" />
        </div>

        <div>
            <InputLabel value="Email" />
            <TextInput
                id="email"
                v-model="form.email"
                type="email"
                placeholder="security@pln.co.id"
                />
            <InputError :messages="form.errors?.email" />
        </div>

        <div>
            <InputLabel value="Unit" />
            <SearchSelect
                v-model="form.unit_id"
                :options="units"
                placeholder="Pilih unit..."
                none-label="— Tanpa Unit —"
                />
            <InputError :messages="form.errors?.unit_id" />
        </div>

        <div>
            <InputLabel value="Password" :required="!showStatus" />
            <TextInput
                id="password"
                v-model="form.password"
                type="password"
                :placeholder="showStatus ? 'Kosongkan jika tidak diubah' : 'Minimal 8 karakter'"
                autocomplete="new-password"
                />
            <InputError :messages="form.errors?.password" />
        </div>

        <div>
            <InputLabel value="Role" :required="true" />
            <Select
                id="role"
                v-model="form.role"
                :options="roleOptions"
                />
            <InputError :messages="form.errors?.role" />
        </div>

        <div v-if="showStatus">
            <InputLabel value="Status" />
            <Select
                id="is_active"
                :model-value="form.is_active ? '1' : '0'"
                :options="statusOptions"
                @update:model-value="form.is_active = $event === '1'"
                />
        </div>
    </div>
</template>
