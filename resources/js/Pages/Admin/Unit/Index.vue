<script setup>
import { ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PageHeader from '@/Components/Admin/PageHeader.vue';
import Card from '@/Components/Admin/Card.vue';
import Button from '@/Components/Admin/Button.vue';
import FlashMessage from '@/Components/Admin/FlashMessage.vue';
import UnitFilter from './Partials/UnitFilter.vue';
import UnitTable from './Partials/UnitTable.vue';

const props = defineProps({
    units: { type: Array, default: () => [] },
    meta: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 }) },
    search: { type: String, default: '' },
    sort: { type: String, default: 'name' },
    dir: { type: String, default: 'asc' },
    can_manage: { type: Boolean, default: false },
});

const search = ref(props.search);
const sort = ref({ by: props.sort, dir: props.dir });
let debounce = null;

function applyQuery(page = 1) {
    router.get('/admin/unit', {
        search: search.value,
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
</script>

<template>
    <AdminLayout title="Unit">
        <div class="space-y-6">
            <PageHeader
                title="Unit"
                :breadcrumb="[
                    { label: 'Unit', href: '/admin/unit' },
                    { label: 'Main' },
                ]"
            >
                <template v-if="can_manage" #actions>
                    <Button variant="secondary">Ekspor</Button>
                    <Link
                        href="/admin/unit/create"
                        class="inline-flex items-center justify-center gap-2 rounded-md bg-blue-600 px-4 py-2 text-xs text-white transition hover:bg-blue-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2"
                    >
                        + Tambah Unit
                    </Link>
                </template>
            </PageHeader>

            <FlashMessage />

            <Card>
                <UnitFilter v-model="search" v-model:sort="sort" :count="meta.total" placeholder="Cari unit..." />
                <UnitTable :rows="units" :meta="meta" :can-manage="can_manage" @page-change="go" />
            </Card>
        </div>
    </AdminLayout>
</template>
