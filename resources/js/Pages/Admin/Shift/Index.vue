<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import ShiftFilter from './Partials/ShiftFilter.vue';
import ShiftTable from './Partials/ShiftTable.vue';

const props = defineProps({
    shifts: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 }) },
    search: { type: String, default: '' },
    sort: { type: String, default: 'name' },
    dir: { type: String, default: 'asc' },
    units: { type: Array, default: () => [] },
    unit_id: { type: [String, Number], default: '' },
});

const search = ref(props.search);
const sort = ref({ by: props.sort, dir: props.dir });
const unitId = ref(props.unit_id ?? '');
let debounce = null;

function applyQuery(page = 1) {
    router.get('/admin/shift', {
        search: search.value,
        unit_id: unitId.value,
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
</script>

<template>
    <AdminLayout title="Shift Kerja">
        <div class="space-y-6">
            <PageHeader
                title="Shift Kerja"
                description="Kelola master shift kerja satpam berdasarkan unit."
                :breadcrumb="[
                    { label: 'Shift Kerja', href: '/admin/shift' },
                    { label: 'Main' },
                ]"
            >
                <template #actions>
                    <Link
                        href="/admin/shift/create"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs text-white transition hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                    >
                        + Tambah Shift
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <Card>
                <ShiftFilter
                    v-model="search"
                    v-model:sort="sort"
                    v-model:unit="unitId"
                    :units="units"
                    :count="meta.total"
                    placeholder="Cari shift..."
                />
                <ShiftTable :rows="shifts" :meta="meta" @page-change="go" />
            </Card>
        </div>
    </AdminLayout>
</template>
