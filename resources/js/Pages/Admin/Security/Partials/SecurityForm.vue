<script setup>
import InputLabel from '@/Components/Admin/InputLabel.vue';
import InputError from '@/Components/Admin/InputError.vue';
import TextInput from '@/Components/Admin/TextInput.vue';
import SearchSelect from '@/Components/Admin/SearchSelect.vue';
import FileInput from '@/Components/Admin/FileInput.vue';

const props = defineProps({
    form: { type: Object, required: true },
    units: { type: Array, default: () => [] },
    canChooseUnit: { type: Boolean, default: false },
    existingPhotoUrl: { type: String, default: '' },
    existingKtaUrl: { type: String, default: '' },
});
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <InputLabel value="Nama Lengkap" :required="true" />
            <TextInput
                id="full_name"
                v-model="form.full_name"
                placeholder="Nama lengkap satpam"
                />
            <InputError :messages="form.errors?.full_name" />
        </div>

        <div v-if="canChooseUnit">
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
            <InputLabel value="No. HP" />
            <TextInput
                id="phone_number"
                v-model="form.phone_number"
                placeholder="08xxxxxxxxxx"
                />
            <InputError :messages="form.errors?.phone_number" />
        </div>

        <div>
            <InputLabel value="No. KTA" />
            <TextInput
                id="kta_number"
                v-model="form.kta_number"
                placeholder="Nomor KTA"
                />
            <InputError :messages="form.errors?.kta_number" />
        </div>

        <div>
            <InputLabel value="Tempat Lahir" />
            <TextInput
                id="birth_place"
                v-model="form.birth_place"
                placeholder="Banda Aceh"
                />
            <InputError :messages="form.errors?.birth_place" />
        </div>

        <div>
            <InputLabel value="Tanggal Lahir" />
            <TextInput
                id="birth_date"
                v-model="form.birth_date"
                type="date"
                />
            <InputError :messages="form.errors?.birth_date" />
        </div>

        <div>
            <InputLabel value="Tanggal Terbit KTA" :required="true" />
            <TextInput
                id="kta_issued_date"
                v-model="form.kta_issued_date"
                type="date"
                />
            <InputError :messages="form.errors?.kta_issued_date" />
        </div>

        <div>
            <InputLabel value="Tanggal Kedaluwarsa KTA" />
            <TextInput
                id="kta_expired_date"
                v-model="form.kta_expired_date"
                type="date"
                />
            <InputError :messages="form.errors?.kta_expired_date" />
        </div>

        <div class="sm:col-span-2">
            <InputLabel value="Alamat" />
            <TextInput
                id="address"
                v-model="form.address"
                placeholder="Alamat lengkap"
                />
            <InputError :messages="form.errors?.address" />
        </div>

        <div>
            <InputLabel value="Foto Profil" />
            <FileInput
                v-model="form.photo"
                accept="image/*"
                :existing-url="existingPhotoUrl"
                hint="Gambar saja, maksimal 2 MB."
                />
            <InputError :messages="form.errors?.photo" />
        </div>

        <div>
            <InputLabel value="File KTA" />
            <FileInput
                v-model="form.kta_file"
                accept="image/*"
                :existing-url="existingKtaUrl"
                hint="Gambar saja, maksimal 2 MB."
                />
            <InputError :messages="form.errors?.kta_file" />
        </div>
    </div>
</template>
