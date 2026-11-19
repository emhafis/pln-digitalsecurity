<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import HydrantFilter from './Partials/HydrantFilter.vue';
import HydrantTable from './Partials/HydrantTable.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 }) },
    search: { type: String, default: '' },
    status: { type: String, default: '' },
    sort: { type: String, default: 'hydrant_code' },
    dir: { type: String, default: 'asc' },
    units: { type: Array, default: () => [] },
    unit_id: { type: [String, Number], default: '' },
});

const search = ref(props.search);
const status = ref(props.status ?? '');
const sort = ref({ by: props.sort, dir: props.dir });
const unitId = ref(props.unit_id ?? '');
let debounce = null;

function applyQuery(page = 1) {
    router.get('/admin/hydrant', {
        search: search.value,
        status: status.value,
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

watch([status, sort, unitId], () => applyQuery(1));
</script>

<template>
    <AdminLayout title="Data Hydrant">
        <div class="space-y-6">
            <PageHeader
                title="Data Hydrant"
                description="Kelola master data Hydrant per unit."
                :breadcrumb="[
                    { label: 'Data Hydrant', href: '/admin/hydrant' },
                    { label: 'Main' },
                ]"
            >
                <template #actions>
                    <Link
                        href="/admin/hydrant/create"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs text-white transition hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                    >
                        + Tambah Hydrant
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <Card>
                <HydrantFilter
                    v-model="search"
                    v-model:status="status"
                    v-model:sort="sort"
                    v-model:unit="unitId"
                    :units="units"
                    :count="meta.total"
                    placeholder="Cari kode, nama, merek, lokasi..."
                />
                <HydrantTable :rows="items" :meta="meta" @page-change="go" />
            </Card>
        </div>
    </AdminLayout>
</template>
