<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import SchedulePatternFilter from './Partials/SchedulePatternFilter.vue';
import SchedulePatternTable from './Partials/SchedulePatternTable.vue';

const props = defineProps({
    patterns: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 }) },
    search: { type: String, default: '' },
    sort: { type: String, default: 'name' },
    dir: { type: String, default: 'asc' },
    is_active: { type: String, default: '' },
    units: { type: Array, default: () => [] },
    unit_id: { type: [String, Number], default: '' },
});

const search = ref(props.search);
const sort = ref({ by: props.sort, dir: props.dir });
const unitId = ref(props.unit_id ?? '');
const activeStatus = ref(props.is_active ?? '');
let debounce = null;

function applyQuery(page = 1) {
    router.get('/admin/schedule-pattern', {
        search: search.value,
        unit_id: unitId.value,
        is_active: activeStatus.value,
        sort: sort.value.by,
        dir: sort.value.dir,
        page,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function go(page) {
    applyQuery(page);
}

watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => applyQuery(1), 300);
});

watch(sort, () => applyQuery(1));

watch(unitId, () => applyQuery(1));

watch(activeStatus, () => applyQuery(1));
</script>

<template>
    <AdminLayout title="Pola Rotasi">
        <div class="space-y-6">
            <PageHeader
                title="Pola Rotasi"
                description="Kelola template siklus shift satpam berdasarkan unit."
                :breadcrumb="[
                    { label: 'Pola Rotasi', href: '/admin/schedule-pattern' },
                    { label: 'Main' },
                ]"
            >
                <template #actions>
                    <Link
                        href="/admin/schedule-pattern/create"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs text-white transition hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                    >
                        + Tambah Pola Rotasi
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <Card>
                <SchedulePatternFilter
                    v-model="search"
                    v-model:sort="sort"
                    v-model:unit="unitId"
                    v-model:status="activeStatus"
                    :units="units"
                    :count="meta.total"
                    placeholder="Cari nama pola..."
                />
                <SchedulePatternTable :rows="patterns" :meta="meta" @page-change="go" />
            </Card>
        </div>
    </AdminLayout>
</template>
