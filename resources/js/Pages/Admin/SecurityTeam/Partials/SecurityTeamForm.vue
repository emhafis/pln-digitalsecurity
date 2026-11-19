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
  posts: { type: Array, default: () => [] },
  unitName: { type: String, default: '' },
  postLocked: { type: Boolean, default: false },
  identityLocked: { type: Boolean, default: false },
});

const statusOptions = [
  { value: '1', label: 'Aktif' },
  { value: '0', label: 'Nonaktif' },
];

const teamNameOptions = ['A', 'B', 'C', 'D', 'E', 'F'].map((code) => ({
  value: `Regu ${code}`,
  label: `Regu ${code}`,
}));

const filteredPosts = computed(() => props.posts.filter(
  (item) => !props.form.unit_id || String(item.unit_id) === String(props.form.unit_id),
));

const postLabel = computed(
  () => props.posts.find((item) => String(item.value) === String(props.form.security_post_id))?.label ?? '-',
);

watch(() => props.form.unit_id, (value, previous) => {
  if (props.postLocked || previous === undefined || String(value) === String(previous)) {
    return;
  }

  const exists = filteredPosts.value.some(
    (item) => String(item.value) === String(props.form.security_post_id),
  );

  if (!exists) {
    props.form.security_post_id = '';
  }
});
</script>

<template>
  <div class="grid gap-4 sm:grid-cols-2">
    <div v-if="postLocked" class="sm:col-span-2">
      <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-700" role="alert">
        Pos jaga tidak dapat diubah karena regu sudah memiliki riwayat anggota atau periode jadwal.
      </div>
    </div>

    <div>
      <InputLabel value="Unit" />
      <TextInput v-if="postLocked" :model-value="unitName || '-'" disabled />
      <SearchSelect v-else v-model="form.unit_id" :options="units" placeholder="Pilih unit..."
        search-placeholder="Cari unit..." />
      <InputError :messages="form.errors?.unit_id" />
    </div>

    <div>
      <InputLabel value="Pos Keamanan" :required="true" />
      <TextInput v-if="postLocked" :model-value="postLabel" disabled />
      <SearchSelect v-else v-model="form.security_post_id" :options="filteredPosts" placeholder="Pilih pos keamanan..."
        search-placeholder="Cari pos..." />
      <InputError :messages="form.errors?.security_post_id" />
    </div>

    <div>
      <InputLabel value="Nama Regu" :required="true" />
      <TextInput v-if="identityLocked" :model-value="form.name" disabled />
      <SearchSelect v-else v-model="form.name" :options="teamNameOptions" placeholder="Pilih regu..."
        search-placeholder="Cari regu..." />
      <p class="mt-1 text-xs text-gray-500">
        Kode regu dibuat otomatis oleh sistem berdasarkan pilihan nama.
      </p>
      <InputError :messages="form.errors?.name" />
    </div>

    <div>
      <InputLabel value="Status" />
      <Select id="is_active" :model-value="form.is_active ? '1' : '0'" :options="statusOptions"
        @update:model-value="form.is_active = $event === '1'" />
      <p class="mt-1 text-xs text-gray-500">
        Regu nonaktif tidak dapat menerima anggota baru atau digunakan untuk periode jadwal baru.
      </p>
      <InputError :messages="form.errors?.is_active" />
    </div>

    <div class="sm:col-span-2">
      <InputLabel value="Deskripsi" />
      <textarea id="description" v-model="form.description" rows="3" maxlength="2000"
        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-[#155e75]"
        placeholder="Keterangan tambahan tentang regu (opsional)" />
      <InputError :messages="form.errors?.description" />
    </div>
  </div>
</template>
