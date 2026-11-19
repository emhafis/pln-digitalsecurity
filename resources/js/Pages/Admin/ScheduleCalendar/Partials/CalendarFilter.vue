<script setup>
import { computed } from 'vue';
import SearchSelect from '@/Components/Admin/SearchSelect.vue';
import Select from '@/Components/Admin/Select.vue';
import TextInput from '@/Components/Admin/TextInput.vue';

const month = defineModel('month', { type: String, default: '' });
const unit = defineModel('unit', { type: [String, Number], default: '' });
const post = defineModel('post', { type: [String, Number], default: '' });
const team = defineModel('team', { type: [String, Number], default: '' });
const security = defineModel('security', { type: [String, Number], default: '' });
const status = defineModel('status', { type: String, default: '' });

const props = defineProps({
    count: { type: Number, default: 0 },
    units: { type: Array, default: () => [] },
    posts: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
    securities: { type: Array, default: () => [] },
    statusOptions: { type: Array, default: () => [] },
});

const postOptions = computed(() => props.posts.filter(
    (item) => !unit.value || String(item.unit_id) === String(unit.value)
));

const teamOptions = computed(() => props.teams.filter((item) => {
    if (post.value && String(item.security_post_id) !== String(post.value)) {
        return false;
    }

    if (!post.value && unit.value && String(item.unit_id) !== String(unit.value)) {
        return false;
    }

    return true;
}));

const securityOptions = computed(() => props.securities.filter(
    (item) => !unit.value || String(item.unit_id) === String(unit.value)
));
</script>

<template>
    <div class="mb-4 space-y-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:flex-wrap">
            <div class="flex items-center gap-2">
                <TextInput v-model="month" type="month" class="w-40" />
                <span class="text-xs text-gray-500">{{ count }} jadwal</span>
            </div>

            <SearchSelect
                v-model="unit"
                :options="units"
                placeholder="Semua Unit"
                none-label="Semua Unit"
                search-placeholder="Cari unit..."
                class="sm:w-52"
            />

            <SearchSelect
                v-model="post"
                :options="postOptions"
                placeholder="Semua Pos"
                none-label="Semua Pos"
                search-placeholder="Cari pos..."
                class="sm:w-52"
            />

            <SearchSelect
                v-model="team"
                :options="teamOptions"
                placeholder="Semua Regu"
                none-label="Semua Regu"
                search-placeholder="Cari regu..."
                class="sm:w-48"
            />

            <SearchSelect
                v-model="security"
                :options="securityOptions"
                placeholder="Semua Satpam"
                none-label="Semua Satpam"
                search-placeholder="Cari satpam..."
                class="sm:w-56"
            />

            <div class="sm:w-36">
                <Select v-model="status" :options="statusOptions" />
            </div>
        </div>
    </div>
</template>
