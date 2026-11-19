<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import PatrolPointFilter from './Partials/PatrolPointFilter.vue';
import PatrolPointTable from './Partials/PatrolPointTable.vue';

const props = defineProps({
    securityPosts: { type: Array, default: () => [] },
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
    router.get('/admin/patrol-point', {
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
    <AdminLayout title="Titik Patroli">
        <div class="space-y-6">
            <PageHeader
                title="Titik Patroli"
                :breadcrumb="[
                    { label: 'Titik Patroli', href: '/admin/patrol-point' },
                    { label: 'Main' },
                ]"
            >
                <template #actions>
                    <Button variant="secondary">Ekspor</Button>
                    <Link
                        href="/admin/patrol-point/create"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                    >
                        + Tambah Titik Patroli
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <Card>
                <PatrolPointFilter
                    v-model="search"
                    v-model:sort="sort"
                    v-model:unit="unitId"
                    :units="units"
                    :count="meta.total"
                    placeholder="Cari pos jaga atau titik patroli..."
                />
                <PatrolPointTable :rows="securityPosts" :meta="meta" @page-change="go" />
            </Card>
        </div>
    </AdminLayout>
</template>
