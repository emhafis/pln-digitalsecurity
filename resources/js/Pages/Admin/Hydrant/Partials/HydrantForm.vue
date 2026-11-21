<script setup>
import InputLabel from '@/Components/Admin/InputLabel.vue';
import InputError from '@/Components/Admin/InputError.vue';
import TextInput from '@/Components/Admin/TextInput.vue';
import SearchSelect from '@/Components/Admin/SearchSelect.vue';
import ImageWithFallback from '@/Components/Admin/ImageWithFallback.vue';

const props = defineProps({
    form: { type: Object, required: true },
    units: { type: Array, default: () => [] },
    isEdit: { type: Boolean, default: false },
    existingPhotoUrl: { type: String, default: null },
});

const typeOptions = [
    { value: 'fire_pump_diesel', label: 'Fire Pump Diesel', disabled: false },
    { value: 'fire_pump_electric', label: 'Fire Pump Electric', disabled: true },
    { value: 'jockey_pump', label: 'Jockey Pump', disabled: true },
    { value: 'hydrant_box', label: 'Hydrant Box', disabled: true },
    { value: 'hydrant_pillar', label: 'Hydrant Pillar', disabled: true },
    { value: 'siamese_connection', label: 'Siamese Connection', disabled: true },
];

function typeLabel(value) {
    return typeOptions.find((opt) => opt.value === value)?.label ?? value;
}

function onPhotoChange(e) {
    const file = e.target.files?.[0] ?? null;
    props.form.photo = file;
}
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <InputLabel value="Unit Pemilik" :required="true" />
            <SearchSelect v-model="form.unit_id" :options="units" placeholder="Pilih unit..." />
            <InputError :messages="form.errors?.unit_id" />
        </div>



        <div>
            <InputLabel value="Nama Hydrant" :required="true" />
            <TextInput id="name" v-model="form.name" placeholder="Hydrant Pump Gedung UID" />
            <InputError :messages="form.errors?.name" />
        </div>

        <div>
            <InputLabel value="Jenis Hydrant" :required="!isEdit" />
            <select
                v-model="form.hydrant_type"
                :disabled="isEdit"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-600"
            >
                <option value="" disabled>Pilih jenis...</option>
                <option
                    v-for="opt in typeOptions"
                    :key="opt.value"
                    :value="opt.value"
                    :disabled="opt.disabled"
                >
                    {{ opt.label }}{{ opt.disabled ? ' (Coming Soon)' : '' }}
                </option>
            </select>
            <p v-if="isEdit" class="mt-1 text-xs text-gray-500">
                Jenis Hydrant terkunci: {{ typeLabel(form.hydrant_type) }}. Perubahan jenis belum tersedia pada MVP.
            </p>
            <p v-else class="mt-1 text-xs text-gray-500">
                MVP hanya mendukung Fire Pump Diesel; jenis lain tersedia pada pengembangan berikutnya.
            </p>
            <InputError :messages="form.errors?.hydrant_type" />
        </div>

        <div>
            <InputLabel value="Merek" :required="true" />
            <TextInput id="brand" v-model="form.brand" placeholder="Grundfos, Flowserve..." />
            <InputError :messages="form.errors?.brand" />
        </div>

        <div>
            <InputLabel value="Model" :required="true" />
            <TextInput id="model" v-model="form.model" placeholder="TD-400, FT-200..." />
            <InputError :messages="form.errors?.model" />
        </div>

        <div>
            <InputLabel value="Nomor Seri" />
            <TextInput id="serial_number" v-model="form.serial_number" placeholder="SN-2024-001" />
            <InputError :messages="form.errors?.serial_number" />
        </div>

        <div>
            <InputLabel value="Lokasi Penempatan" :required="true" />
            <TextInput id="placement" v-model="form.placement" placeholder="Pump Room Gedung UID..." />
            <InputError :messages="form.errors?.placement" />
        </div>

        <div>
            <InputLabel value="Tanggal Produksi/Pengadaan" />
            <input
                v-model="form.procurement_date"
                type="date"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
            <InputError :messages="form.errors?.procurement_date" />
        </div>

        <div>
            <InputLabel value="Tanggal Instalasi" />
            <input
                v-model="form.installation_date"
                type="date"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
            <InputError :messages="form.errors?.installation_date" />
        </div>

        <div>
            <InputLabel value="Tanggal Perawatan Terakhir" />
            <input
                v-model="form.last_maintenance_date"
                type="date"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
            <InputError :messages="form.errors?.last_maintenance_date" />
        </div>

        <div class="sm:col-span-2">
            <InputLabel value="Foto Hydrant" />
            <div v-if="isEdit && existingPhotoUrl && !form.remove_photo" class="mb-2">
                <ImageWithFallback :src="existingPhotoUrl" alt="Foto Hydrant" class="h-28 w-28 rounded-md border object-cover" />
                <label class="mt-2 flex items-center gap-2 text-sm text-gray-600">
                    <input v-model="form.remove_photo" type="checkbox" class="rounded border-gray-300 text-blue-600" />
                    Hapus foto saat disimpan
                </label>
            </div>
            <input
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:text-gray-700 hover:file:bg-gray-200"
                @change="onPhotoChange"
            />
            <p class="mt-1 text-xs text-gray-500">Foto Hydrant (Opsional). JPG/PNG/WebP, maks 2 MB.</p>
            <InputError :messages="form.errors?.photo" />
        </div>

        <div class="sm:col-span-2">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                Hydrant aktif
            </label>
            <p class="mt-1 text-xs text-gray-500">Hydrant nonaktif tidak dapat diinspeksi.</p>
        </div>
    </div>
</template>
