<script setup>
import InputLabel from '@/Components/Admin/InputLabel.vue';
import InputError from '@/Components/Admin/InputError.vue';
import TextInput from '@/Components/Admin/TextInput.vue';
import SearchSelect from '@/Components/Admin/SearchSelect.vue';

const props = defineProps({
    form: { type: Object, required: true },
    units: { type: Array, default: () => [] },
    isEdit: { type: Boolean, default: false },
    existingPhotoUrl: { type: String, default: null },
});

const typeOptions = [
    { value: 'dry_chemical', label: 'Dry Chemical' },
    { value: 'co2', label: 'CO2' },
    { value: 'foam', label: 'Foam' },
    { value: 'air', label: 'Air' },
    { value: 'powder', label: 'Powder' },
];

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
            <InputLabel value="Jenis APAR" :required="true" />
            <select
                v-model="form.apar_type"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            >
                <option value="" disabled>Pilih jenis...</option>
                <option v-for="opt in typeOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
            </select>
            <InputError :messages="form.errors?.apar_type" />
        </div>

        <div>
            <InputLabel value="Merek" :required="true" />
            <TextInput id="brand" v-model="form.brand" placeholder="Viking, Fuji, Guardian..." />
            <InputError :messages="form.errors?.brand" />
        </div>

        <div>
            <InputLabel value="Kapasitas" :required="true" />
            <TextInput id="capacity" v-model="form.capacity" placeholder="Contoh: 6 atau 6 Kg" />
            <InputError :messages="form.errors?.capacity" />
        </div>

        <div>
            <InputLabel value="Lokasi Penempatan" :required="true" />
            <TextInput id="placement" v-model="form.placement" placeholder="Ruang Server, Lobby Lantai 1..." />
            <InputError :messages="form.errors?.placement" />
        </div>

        <div>
            <InputLabel value="Nomor Seri" />
            <TextInput id="serial_number" v-model="form.serial_number" placeholder="VK-2024-001" />
            <InputError :messages="form.errors?.serial_number" />
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
            <InputLabel value="Tanggal Pemasangan" />
            <input
                v-model="form.installation_date"
                type="date"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
            <InputError :messages="form.errors?.installation_date" />
        </div>

        <div>
            <InputLabel value="Tanggal Isi Ulang" />
            <input
                v-model="form.recharge_date"
                type="date"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
            <InputError :messages="form.errors?.recharge_date" />
        </div>

        <div>
            <InputLabel value="Tanggal Kedaluwarsa" />
            <input
                v-model="form.expiry_date"
                type="date"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
            />
            <InputError :messages="form.errors?.expiry_date" />
        </div>

        <div class="sm:col-span-2">
            <InputLabel value="Foto APAR" />
            <div v-if="isEdit && existingPhotoUrl && !form.remove_photo" class="mb-2">
                <img :src="existingPhotoUrl" alt="Foto APAR" class="h-28 w-28 rounded-md border object-cover" />
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
            <p class="mt-1 text-xs text-gray-500">Foto APAR (Opsional). JPG/PNG/WebP, maks 2 MB.</p>
            <InputError :messages="form.errors?.photo" />
        </div>

        <div class="sm:col-span-2">
            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                APAR aktif
            </label>
            <p class="mt-1 text-xs text-gray-500">APAR nonaktif tidak dapat diinspeksi.</p>
        </div>
    </div>
</template>
