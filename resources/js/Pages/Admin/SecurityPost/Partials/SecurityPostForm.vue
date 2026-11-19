<script setup>
import InputLabel from '@/Components/Admin/InputLabel.vue';
import InputError from '@/Components/Admin/InputError.vue';
import TextInput from '@/Components/Admin/TextInput.vue';
import SearchSelect from '@/Components/Admin/SearchSelect.vue';
import LocationPickerMap from '@/Components/Admin/LocationPickerMap.vue';

defineProps({
    form: { type: Object, required: true },
    units: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <InputLabel value="Nama Pos" :required="true" />
            <TextInput
                id="name"
                v-model="form.name"
                placeholder="POS UTAMA"
                />
            <InputError :messages="form.errors?.name" />
        </div>

        <div>
            <InputLabel value="Unit" :required="true" />
            <SearchSelect
                v-model="form.unit_id"
                :options="units"
                placeholder="Pilih unit..."
                />
            <InputError :messages="form.errors?.unit_id" />
        </div>

        <div class="sm:col-span-2">
            <InputLabel value="Alamat" />
            <TextInput
                id="address"
                v-model="form.address"
                placeholder="Jl. Contoh No. 1, Banda Aceh"
                />
            <InputError :messages="form.errors?.address" />
        </div>

        <div class="sm:col-span-2 relative z-[10]">
            <InputLabel value="Lokasi Peta" />
            <LocationPickerMap
                v-model:latitude="form.latitude"
                v-model:longitude="form.longitude"
            />
        </div>

        <div>
            <InputLabel value="Latitude" />
            <TextInput
                id="latitude"
                v-model="form.latitude"
                type="number"
                step="any"
                placeholder="5.5622464"
                />
            <InputError :messages="form.errors?.latitude" />
        </div>

        <div>
            <InputLabel value="Longitude" />
            <TextInput
                id="longitude"
                v-model="form.longitude"
                type="number"
                step="any"
                placeholder="95.3348073"
                />
            <InputError :messages="form.errors?.longitude" />
        </div>
    </div>
</template>
