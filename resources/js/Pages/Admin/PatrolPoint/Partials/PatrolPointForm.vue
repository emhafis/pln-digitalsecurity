<script setup>
import InputLabel from '@/Components/Admin/InputLabel.vue';
import InputError from '@/Components/Admin/InputError.vue';
import TextInput from '@/Components/Admin/TextInput.vue';
import SearchSelect from '@/Components/Admin/SearchSelect.vue';

defineProps({
    form: { type: Object, required: true },
    securityPosts: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <InputLabel value="Nama Titik" :required="true" />
            <TextInput
                id="name"
                v-model="form.name"
                placeholder="TITIK A"
                />
            <InputError :messages="form.errors?.name" />
        </div>

        <div>
            <InputLabel value="Pos Keamanan" :required="true" />
            <SearchSelect
                v-model="form.security_post_id"
                :options="securityPosts"
                placeholder="Pilih pos keamanan..."
                search-placeholder="Cari pos keamanan..."
                />
            <InputError :messages="form.errors?.security_post_id" />
        </div>
    </div>
</template>
