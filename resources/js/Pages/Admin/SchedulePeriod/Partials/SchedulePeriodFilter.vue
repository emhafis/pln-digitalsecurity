<script setup>
import { computed, ref } from 'vue';
import { ArrowsUpDownIcon, ArrowUpIcon, ArrowDownIcon, ChevronDownIcon } from '@heroicons/vue/24/outline';
import SearchInput from '@/Components/Admin/SearchInput.vue';
import SearchSelect from '@/Components/Admin/SearchSelect.vue';
import Select from '@/Components/Admin/Select.vue';
import TextInput from '@/Components/Admin/TextInput.vue';

const search = defineModel({ type: String, default: '' });
const sort = defineModel('sort', { type: Object, default: () => ({ by: 'start_date', dir: 'desc' }) });
const unit = defineModel('unit', { type: [String, Number], default: '' });
const post = defineModel('post', { type: [String, Number], default: '' });
const team = defineModel('team', { type: [String, Number], default: '' });
const status = defineModel('status', { type: String, default: '' });
const startDate = defineModel('startDate', { type: String, default: '' });
const endDate = defineModel('endDate', { type: String, default: '' });

const props = defineProps({
    count: { type: Number, default: 0 },
    units: { type: Array, default: () => [] },
    posts: { type: Array, default: () => [] },
    teams: { type: Array, default: () => [] },
});

const sortOpen = ref(false);

const attributes = [
    { value: 'name', label: 'Nama Periode' },
    { value: 'unit', label: 'Unit' },
    { value: 'start_date', label: 'Tanggal Mulai' },
    { value: 'end_date', label: 'Tanggal Selesai' },
    { value: 'status', label: 'Status' },
    { value: 'dibuat', label: 'Dibuat' },
];

const statusOptions = [
    { value: '', label: 'Semua Status' },
    { value: 'draft', label: 'Draft' },
    { value: 'active', label: 'Aktif' },
    { value: 'completed', label: 'Selesai' },
];

const postOptions = computed(() => props.posts.filter(
    (item) => !unit.value || String(item.unit_id) === String(unit.value)
));

const teamOptions = computed(() => props.teams.filter(
    (item) => !post.value || String(item.security_post_id) === String(post.value)
));

function toggleSort(value) {
    if (sort.value.by === value) {
        sort.value = { by: value, dir: sort.value.dir === 'asc' ? 'desc' : 'asc' };
    } else {
        sort.value = { by: value, dir: 'asc' };
    }
    sortOpen.value = false;
}
</script>

<template>
    <div class="mb-4 space-y-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:flex-wrap">
            <SearchInput v-model="search" placeholder="Cari nama periode..." />

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

            <div class="sm:w-36">
                <Select v-model="status" :options="statusOptions" />
            </div>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <TextInput v-model="startDate" type="date" class="sm:w-40" />
                <span class="hidden text-xs text-gray-400 sm:inline">s/d</span>
                <TextInput v-model="endDate" type="date" class="sm:w-40" />
            </div>

            <div class="flex items-center gap-3">
                <p class="text-xs text-gray-500">{{ count }} data</p>

                <div class="relative">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-600 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-1"
                        @click="sortOpen = !sortOpen"
                    >
                        <ArrowsUpDownIcon class="h-3.5 w-3.5" />
                        Urutkan
                        <ChevronDownIcon class="h-3.5 w-3.5 transition-transform" :class="{ 'rotate-180': sortOpen }" />
                    </button>

                    <div v-if="sortOpen" class="fixed inset-0 z-10" @click="sortOpen = false" />

                    <div
                        v-if="sortOpen"
                        class="absolute right-0 z-20 mt-1.5 w-44 rounded-xl border border-gray-200 bg-white py-1.5 shadow-lg"
                    >
                        <button
                            v-for="attr in attributes"
                            :key="attr.value"
                            type="button"
                            class="flex w-full items-center justify-between px-4 py-2 text-left text-sm transition hover:bg-gray-50"
                            :class="sort.by === attr.value ? 'text-blue-600' : 'text-gray-700'"
                            @click="toggleSort(attr.value)"
                        >
                            <span>{{ attr.label }}</span>
                            <ArrowUpIcon
                                v-if="sort.by === attr.value && sort.dir === 'asc'"
                                class="h-4 w-4 shrink-0"
                            />
                            <ArrowDownIcon
                                v-else-if="sort.by === attr.value && sort.dir === 'desc'"
                                class="h-4 w-4 shrink-0"
                            />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
